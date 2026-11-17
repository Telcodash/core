<?php

namespace App\Services\TaskQueue;

use App\Enums\QueueTaskStatus;
use App\Events\QueueTaskCompleted;
use App\Events\QueueTaskFailed;
use App\Events\QueueTaskProgressed;
use App\Models\QueueTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Core's entry point to the BullMQ queue service.
 *
 *     $task = app(TaskQueue::class)->dispatch('reports.generate', ['month' => '2026-11'], subject: $team);
 *
 * Listen for QueueTaskCompleted / QueueTaskFailed to react to results.
 */
class TaskQueue
{
    /**
     * @param  array{default_queue: string, callback_url: string|null}  $config
     */
    public function __construct(
        private readonly QueueServiceClient $client,
        private readonly array $config,
    ) {}

    /**
     * Record a task and send it to the queue service.
     *
     * Never throws for transport errors: if the service is unreachable the task is stored as
     * `dispatch_failed` and re-sent by `tasks:sync`. When called inside a DB transaction the
     * HTTP call waits for commit, so a fast webhook can never race an uncommitted row.
     *
     * @param  array<string, mixed>  $payload  Must be JSON-serialisable.
     * @param  array{attempts?: int, backoff_ms?: int, delay_ms?: int, priority?: int}  $options
     */
    public function dispatch(
        string $name,
        array $payload = [],
        ?string $queue = null,
        ?Model $subject = null,
        ?User $user = null,
        array $options = [],
    ): QueueTask {
        $task = new QueueTask([
            'queue' => $queue ?? $this->config['default_queue'],
            'name' => $name,
            'payload' => $payload,
            'options' => $options ?: null,
            'status' => QueueTaskStatus::Pending,
            'user_id' => $user?->id,
        ]);

        if ($subject !== null) {
            $task->subject()->associate($subject);
        }

        $task->save();

        DB::afterCommit(fn () => $this->send($task));

        return $task;
    }

    /**
     * Send (or re-send) a recorded task. Safe to repeat: the queue service de-duplicates by id.
     */
    public function send(QueueTask $task): QueueTask
    {
        if (! $task->status->awaitsDispatch()) {
            return $task;
        }

        try {
            $remote = $this->client->dispatch([
                'id' => $task->id,
                'queue' => $task->queue,
                'name' => $task->name,
                'payload' => $task->payload,
                'callback_url' => $this->callbackUrl(),
                'meta' => ['source' => 'core'],
                'options' => $task->options ?? [],
            ]);
        } catch (QueueServiceException $e) {
            $transient = $e->isTransient();

            $updated = $this->updateIfAwaitingDispatch($task, [
                'status' => $transient ? QueueTaskStatus::DispatchFailed : QueueTaskStatus::Failed,
                'error' => $e->getMessage(),
                'finished_at' => $transient ? null : now(),
            ]);

            $transient
                ? Log::warning("Queue task [{$task->id}] dispatch failed; will retry.", ['error' => $e->getMessage()])
                : report($e);

            if ($updated && ! $transient) {
                QueueTaskFailed::dispatch($task);
            }

            return $task;
        }

        $this->updateIfAwaitingDispatch($task, [
            'status' => QueueTaskStatus::Queued,
            'error' => null,
            'dispatched_at' => now(),
        ]);

        // An id that already existed remotely may be further along than "queued".
        if (is_string($remote['state'] ?? null) && $remote['state'] !== 'waiting') {
            $this->applyRemoteState($task, $remote);
        }

        return $task;
    }

    /**
     * Webhooks for a task can land before the dispatch request returns (a worker may pick it up
     * instantly), so only move the row out of pending/dispatch_failed if nothing else already has.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function updateIfAwaitingDispatch(QueueTask $task, array $attributes): bool
    {
        $updated = QueueTask::query()
            ->whereKey($task->id)
            ->whereIn('status', [QueueTaskStatus::Pending, QueueTaskStatus::DispatchFailed])
            ->update($attributes + ['updated_at' => now()]) > 0;

        $task->refresh();

        return $updated;
    }

    /**
     * Cancel a task that has not started yet. Returns false if it is already running or finished.
     */
    public function cancel(QueueTask $task): bool
    {
        if ($task->status->isTerminal() || $task->status === QueueTaskStatus::Active) {
            return false;
        }

        if (! $task->status->awaitsDispatch()) {
            try {
                $this->client->cancel($task->queue, $task->id);
            } catch (QueueServiceException $e) {
                if ($e->getCode() === 409) {
                    return false;
                }

                throw $e;
            }
        }

        $task->forceFill(['status' => QueueTaskStatus::Cancelled, 'finished_at' => now()])->save();

        return true;
    }

    /**
     * Poll the queue service and reconcile local state. Used as a fallback for missed webhooks.
     */
    public function refresh(QueueTask $task): QueueTask
    {
        if ($task->status->isTerminal()) {
            return $task;
        }

        if ($task->status->awaitsDispatch()) {
            return $this->send($task);
        }

        $remote = $this->client->find($task->queue, $task->id);

        if ($remote === null) {
            $this->record($task, 'failed', ['error' => 'Task no longer exists in the queue service.']);

            return $task;
        }

        $this->applyRemoteState($task, $remote);

        return $task;
    }

    /**
     * Apply a lifecycle event (from a webhook or a poll). Returns false if the event was ignored
     * because the task already finished. Events can arrive out of order, so terminal states win.
     *
     * @param  'active'|'progress'|'completed'|'failed'  $event
     * @param  array<string, mixed>  $data
     */
    public function record(QueueTask $task, string $event, array $data = []): bool
    {
        $now = now();

        $changes = match ($event) {
            'active' => [
                'status' => QueueTaskStatus::Active,
            ],
            'progress' => [
                'status' => QueueTaskStatus::Active,
                'progress' => json_encode($data['progress'] ?? null),
            ],
            'completed' => [
                'status' => QueueTaskStatus::Completed,
                'result' => json_encode($data['result'] ?? null),
                'error' => null,
                'finished_at' => $now,
            ],
            'failed' => [
                'status' => QueueTaskStatus::Failed,
                'error' => is_string($data['error'] ?? null) ? $data['error'] : 'Unknown error',
                'finished_at' => $now,
            ],
        };

        // Conditional UPDATEs: atomic without row locks, and a finished task can never be reopened.
        $applied = QueueTask::query()
            ->whereKey($task->id)
            ->whereNotIn('status', [QueueTaskStatus::Completed, QueueTaskStatus::Failed, QueueTaskStatus::Cancelled])
            ->update($changes + ['updated_at' => $now]) > 0;

        if ($applied) {
            // Monotonic fields: only ever set once / only ever increase, whatever order events arrive in.
            QueueTask::query()->whereKey($task->id)->whereNull('started_at')->update(['started_at' => $now]);

            if (is_int($data['attempts_made'] ?? null)) {
                QueueTask::query()->whereKey($task->id)
                    ->where('attempts_made', '<', $data['attempts_made'])
                    ->update(['attempts_made' => $data['attempts_made']]);
            }
        }

        $task->refresh();

        if ($applied) {
            match ($event) {
                'progress' => QueueTaskProgressed::dispatch($task),
                'completed' => QueueTaskCompleted::dispatch($task),
                'failed' => QueueTaskFailed::dispatch($task),
                default => null,
            };
        }

        return $applied;
    }

    /**
     * @param  array<string, mixed>  $remote  A task as serialised by the queue service API.
     */
    private function applyRemoteState(QueueTask $task, array $remote): void
    {
        $data = ['attempts_made' => $remote['attempts_made'] ?? null];

        match ($remote['state'] ?? null) {
            'active' => $this->record($task, 'progress', $data + ['progress' => $remote['progress'] ?? null]),
            'completed' => $this->record($task, 'completed', $data + ['result' => $remote['result'] ?? null]),
            'failed' => $this->record($task, 'failed', $data + ['error' => $remote['error'] ?? null]),
            default => null, // waiting / delayed / prioritized: still queued
        };
    }

    private function callbackUrl(): string
    {
        return $this->config['callback_url'] ?: route('webhooks.queue');
    }
}

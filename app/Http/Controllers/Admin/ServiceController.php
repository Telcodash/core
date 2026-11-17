<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QueueTaskStatus;
use App\Http\Controllers\Controller;
use App\Models\QueueTask;
use App\Services\Billing\BillingDirectory;
use App\Services\TaskQueue\QueueServiceClient;
use App\Services\TaskQueue\QueueServiceException;
use App\Services\TaskQueue\TaskFailedException;
use App\Services\TaskQueue\TaskQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Status and diagnostics for the external services Core depends on (administrators only).
 */
class ServiceController extends Controller
{
    /** The task the "Test" button runs: reports progress in 4 steps, then returns. */
    private const TEST_TASK = 'example.sleep';

    public function index(QueueServiceClient $client, BillingDirectory $billing): Response
    {
        return Inertia::render('admin/services/index', [
            'queue' => fn () => $this->queueStatus($client),
            // Proof the Billing Daemon's daily run is happening: null if it can't be reached.
            'billingRuns' => function () use ($billing): ?array {
                try {
                    return $billing->recentRuns(7);
                } catch (QueueServiceException|TaskFailedException) {
                    return null;
                }
            },
        ]);
    }

    /**
     * Health-check the queue service, then dispatch a test task. The page polls `task` to follow it.
     */
    public function testQueue(Request $request, QueueServiceClient $client, TaskQueue $tasks): JsonResponse
    {
        $health = $client->health();

        if (! $health['reachable']) {
            return response()->json(['health' => $health, 'task' => null]);
        }

        $task = $tasks->dispatch(self::TEST_TASK, ['ms' => 2000], user: $request->user());

        return response()->json([
            'health' => $health,
            'callback_url' => $this->callbackUrl(),
            'task' => $this->presentTask($task),
        ]);
    }

    /**
     * Current state of a task as Core sees it. With `?remote=1`, also the queue service's own view,
     * which shows whether a stuck task was processed but its webhook never reached Core.
     */
    public function task(Request $request, QueueTask $task, QueueServiceClient $client): JsonResponse
    {
        $remote = null;

        if ($request->boolean('remote')) {
            try {
                $remote = $client->find($task->queue, $task->id) ?? ['state' => 'missing'];
            } catch (QueueServiceException $e) {
                $remote = ['state' => 'unreachable', 'error' => $e->getMessage()];
            }
        }

        return response()->json(['task' => $this->presentTask($task), 'remote' => $remote]);
    }

    /**
     * @return array<string, mixed>
     */
    private function queueStatus(QueueServiceClient $client): array
    {
        $counts = QueueTask::query()
            ->where('created_at', '>=', now()->subDay())
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            ...$client->health(),
            'checked_at' => now()->toIso8601String(),
            'url' => config('services.queue.url'),
            'callback_url' => $this->callbackUrl(),
            'token_configured' => filled(config('services.queue.token')),
            'webhook_secret_configured' => filled(config('services.queue.webhook_secret')),
            'tasks_24h' => collect(QueueTaskStatus::cases())
                ->mapWithKeys(fn (QueueTaskStatus $status) => [$status->value => (int) ($counts[$status->value] ?? 0)])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTask(QueueTask $task): array
    {
        return [
            'id' => $task->id,
            'queue' => $task->queue,
            'name' => $task->name,
            'status' => $task->status->value,
            'progress' => $task->progress,
            'result' => $task->result,
            'error' => $task->error,
            'attempts_made' => $task->attempts_made,
            'dispatched_at' => $task->dispatched_at?->toIso8601String(),
            'started_at' => $task->started_at?->toIso8601String(),
            'finished_at' => $task->finished_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];
    }

    private function callbackUrl(): string
    {
        $configured = config('services.queue.callback_url');

        return is_string($configured) && $configured !== '' ? $configured : route('webhooks.queue');
    }
}

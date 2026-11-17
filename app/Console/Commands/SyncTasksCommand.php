<?php

namespace App\Console\Commands;

use App\Enums\QueueTaskStatus;
use App\Models\QueueTask;
use App\Services\TaskQueue\QueueServiceException;
use App\Services\TaskQueue\TaskQueue;
use Illuminate\Console\Command;

class SyncTasksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:sync
        {--stale=10 : Minutes without an update before a queued/active task is polled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-send undelivered tasks and reconcile tasks whose webhooks may have been missed';

    public function handle(TaskQueue $tasks): int
    {
        $stale = now()->subMinutes((int) $this->option('stale'));
        $resent = $polled = $errors = 0;

        // Undelivered tasks: anything still pending after a minute, or that failed to dispatch.
        QueueTask::query()
            ->where(fn ($q) => $q
                ->where('status', QueueTaskStatus::DispatchFailed)
                ->orWhere(fn ($q) => $q->where('status', QueueTaskStatus::Pending)->where('created_at', '<', now()->subMinute())))
            ->lazyById()
            ->each(function (QueueTask $task) use ($tasks, &$resent) {
                $tasks->send($task);
                $resent++;
            });

        QueueTask::query()
            ->whereIn('status', [QueueTaskStatus::Queued, QueueTaskStatus::Active])
            ->where('updated_at', '<', $stale)
            ->lazyById()
            ->each(function (QueueTask $task) use ($tasks, &$polled, &$errors) {
                try {
                    $tasks->refresh($task);
                    $polled++;
                } catch (QueueServiceException $e) {
                    $errors++;
                    $this->components->warn("[{$task->id}] {$e->getMessage()}");
                }
            });

        $this->components->info("Re-sent {$resent}, polled {$polled}, errors {$errors}.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}

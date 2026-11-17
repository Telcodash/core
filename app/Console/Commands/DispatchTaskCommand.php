<?php

namespace App\Console\Commands;

use App\Enums\QueueTaskStatus;
use App\Services\TaskQueue\TaskQueue;
use Illuminate\Console\Command;

class DispatchTaskCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:dispatch
        {name : Task name, e.g. example.sleep}
        {--payload= : JSON object payload}
        {--queue= : Queue name (defaults to services.queue.default_queue)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a task to the BullMQ queue service';

    public function handle(TaskQueue $tasks): int
    {
        $payload = json_decode((string) ($this->option('payload') ?: '{}'), true);

        if (! is_array($payload) || (array_is_list($payload) && $payload !== [])) {
            $this->components->error('--payload must be a JSON object.');

            return self::FAILURE;
        }

        $queue = $this->option('queue');

        /** @var array<string, mixed> $payload */
        $task = $tasks->dispatch((string) $this->argument('name'), $payload, is_string($queue) ? $queue : null);

        if ($task->status->awaitsDispatch() || $task->status === QueueTaskStatus::Failed) {
            $this->components->error("Task [{$task->id}] is {$task->status->value}: {$task->error}");

            return self::FAILURE;
        }

        $this->components->info("Task [{$task->id}] is {$task->status->value} on [{$task->queue}].");

        return self::SUCCESS;
    }
}

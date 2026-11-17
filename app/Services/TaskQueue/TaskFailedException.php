<?php

namespace App\Services\TaskQueue;

use RuntimeException;

/**
 * A task ran but its worker reported failure (e.g. a daemon rejected an invalid payload).
 */
class TaskFailedException extends RuntimeException
{
    public function __construct(
        public readonly string $queue,
        public readonly string $task,
        public readonly string $reason,
    ) {
        parent::__construct("Task [{$task}] on queue [{$queue}] failed: {$reason}");
    }
}

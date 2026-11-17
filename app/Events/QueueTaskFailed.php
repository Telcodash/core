<?php

namespace App\Events;

use App\Models\QueueTask;
use Illuminate\Foundation\Events\Dispatchable;

class QueueTaskFailed
{
    use Dispatchable;

    public function __construct(public readonly QueueTask $task) {}
}

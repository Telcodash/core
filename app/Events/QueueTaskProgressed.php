<?php

namespace App\Events;

use App\Models\QueueTask;
use Illuminate\Foundation\Events\Dispatchable;

class QueueTaskProgressed
{
    use Dispatchable;

    public function __construct(public readonly QueueTask $task) {}
}

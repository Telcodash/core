<?php

namespace App\Enums;

enum QueueTaskStatus: string
{
    /** Recorded in Core but not yet accepted by the queue service. */
    case Pending = 'pending';

    /** Dispatch to the queue service failed; `tasks:sync` will retry it. */
    case DispatchFailed = 'dispatch_failed';

    /** Accepted by the queue service and waiting for a worker. */
    case Queued = 'queued';

    case Active = 'active';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * Whether the task has finished and must not change status again.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }

    /**
     * Whether the task still needs to be (re)sent to the queue service.
     */
    public function awaitsDispatch(): bool
    {
        return in_array($this, [self::Pending, self::DispatchFailed], true);
    }
}

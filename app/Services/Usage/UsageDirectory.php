<?php

namespace App\Services\Usage;

use App\Services\TaskQueue\QueueServiceException;
use App\Services\TaskQueue\TaskFailedException;
use App\Services\TaskQueue\TaskQueue;
use App\Services\Usage\Data\NumberUsage;
use UnexpectedValueException;

/**
 * Core's access to the Usage Daemon (../usage-daemon), via the queue service: calls, texts and
 * data used by a number, per billing period. The daemon generates dummy usage; it's the same
 * usage the Billing Daemon charges out-of-allowance extras from.
 *
 * @throws QueueServiceException when the queue service or daemon is unavailable
 * @throws TaskFailedException when the daemon rejects the request
 */
class UsageDirectory
{
    public const QUEUE = 'usage';

    public function __construct(
        private readonly TaskQueue $tasks,
        private readonly int $timeoutMs = 5000,
    ) {}

    /**
     * A number's usage for its last `$periods` billing periods (oldest first, the current one last),
     * with daily detail. Periods start on the account's billing day.
     */
    public function forNumber(string $msisdn, int $billingDay, int $periods = 6): NumberUsage
    {
        $result = $this->tasks->call('usage.for_number', [
            'msisdn' => $msisdn,
            'billing_day' => $billingDay,
            'periods' => $periods,
        ], self::QUEUE, $this->timeoutMs);

        if (! is_array($result)) {
            throw new UnexpectedValueException('Expected an object in usage daemon response.');
        }

        /** @var array<string, mixed> $result */
        return NumberUsage::fromArray($result);
    }
}

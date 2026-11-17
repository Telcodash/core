<?php

namespace App\Services\Numbers;

use App\Services\Customers\Data\Payload;
use App\Services\Numbers\Data\Line;
use App\Services\TaskQueue\QueueServiceException;
use App\Services\TaskQueue\TaskFailedException;
use App\Services\TaskQueue\TaskQueue;
use UnexpectedValueException;

/**
 * Core's read access to the MSISDN Daemon (../msisdn-daemon), via the queue service.
 *
 * The daemon owns the number inventory and which account each number is assigned to; it bars
 * numbers itself when billing suspends an account (via billing's event stream).
 *
 * @throws QueueServiceException from every method when the queue service or daemon is unavailable
 * @throws TaskFailedException from every method when the daemon rejects the request
 */
class NumberDirectory
{
    public const QUEUE = 'msisdn';

    /** Accepts 07700 910123, +44 7700 910123, 447700910123. */
    private const UK_MOBILE = '/^(?:\+?44|0)(7\d{9})$/';

    public function __construct(
        private readonly TaskQueue $tasks,
        private readonly int $timeoutMs = 5000,
    ) {}

    /**
     * Whether a value looks like a UK mobile number with its 0 / 44 prefix. (A bare 10-digit value
     * is treated as a billing account number instead.)
     */
    public static function isMobileNumber(string $value): bool
    {
        return (bool) preg_match(self::UK_MOBILE, preg_replace('/[\s()-]/', '', $value) ?? '');
    }

    /**
     * Live numbers per account.
     *
     * @param  list<string>  $accountIds
     * @return array<string, list<Line>> keyed by account id
     */
    public function linesForAccounts(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $result = $this->call('numbers.for_accounts', ['account_ids' => array_values(array_unique($accountIds))]);

        if (! is_array($result)) {
            throw new UnexpectedValueException('Malformed numbers.for_accounts response.');
        }

        $lines = [];

        foreach ($result as $accountId => $rows) {
            if (! is_array($rows) || ! array_is_list($rows)) {
                throw new UnexpectedValueException('Malformed numbers.for_accounts response.');
            }

            /** @var list<array<string, mixed>> $rows */
            $lines[(string) $accountId] = array_map(Line::fromArray(...), $rows);
        }

        return $lines;
    }

    /**
     * Who a number is currently assigned to, or null if it isn't ours / isn't assigned.
     *
     * @return array{target_type: string, target_id: string}|null
     */
    public function ownerOf(string $msisdn): ?array
    {
        $result = $this->call('numbers.get', ['msisdn' => $msisdn]);

        if (! is_array($result) || ! is_array($result['assignment'] ?? null)) {
            return null;
        }

        /** @var array<string, mixed> $assignment */
        $assignment = $result['assignment'];

        return [
            'target_type' => Payload::string($assignment, 'target_type'),
            'target_id' => Payload::string($assignment, 'target_id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function call(string $task, array $payload): mixed
    {
        return $this->tasks->call($task, $payload, self::QUEUE, $this->timeoutMs);
    }
}

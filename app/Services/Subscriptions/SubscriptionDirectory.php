<?php

namespace App\Services\Subscriptions;

use App\Services\Subscriptions\Data\SubscriptionLine;
use App\Services\Subscriptions\Data\Tariff;
use App\Services\TaskQueue\QueueServiceException;
use App\Services\TaskQueue\TaskFailedException;
use App\Services\TaskQueue\TaskQueue;
use UnexpectedValueException;

/**
 * Core's access to the Subscription Daemon (../subscription-daemon), via the queue service:
 * the tariff catalogue (managed by administrators) and lines (a number on a tariff).
 *
 * @throws QueueServiceException from every method when the queue service or daemon is unavailable
 * @throws TaskFailedException from every method when the daemon rejects the request; for tariff
 *                             writes the reason is meant for the administrator
 */
class SubscriptionDirectory
{
    public const QUEUE = 'subscriptions';

    public function __construct(
        private readonly TaskQueue $tasks,
        private readonly int $timeoutMs = 5000,
    ) {}

    /**
     * @return list<Tariff>
     */
    public function tariffs(bool $availableOnly = false): array
    {
        return array_map(Tariff::fromArray(...), $this->list($this->call('tariffs.list', ['available_only' => $availableOnly])));
    }

    /**
     * @param  array{name: string, description: ?string, monthly_price_pence: int, minutes: ?int, texts: ?int, data_mb: ?int, eu_roaming: bool, international_roaming: bool, five_g_plus: bool, available: bool}  $fields
     */
    public function createTariff(array $fields): Tariff
    {
        return Tariff::fromArray($this->object($this->call('tariffs.create', $fields)));
    }

    /**
     * Change some fields of a tariff. Price and allowances are refused while it has active lines.
     *
     * @param  array<string, mixed>  $changes
     */
    public function updateTariff(string $id, array $changes): Tariff
    {
        return Tariff::fromArray($this->object($this->call('tariffs.update', ['id' => $id, 'changes' => (object) $changes])));
    }

    /**
     * Active lines per account.
     *
     * @param  list<string>  $accountIds
     * @return array<string, list<SubscriptionLine>> keyed by account id
     */
    public function linesForAccounts(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $result = $this->object($this->call('lines.for_accounts', ['account_ids' => array_values(array_unique($accountIds))]));
        $lines = [];

        foreach ($result as $accountId => $rows) {
            $lines[(string) $accountId] = array_map(SubscriptionLine::fromArray(...), $this->list($rows));
        }

        return $lines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new UnexpectedValueException('Expected a list in subscription daemon response.');
        }

        /** @var list<array<string, mixed>> $value */
        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value): array
    {
        if (! is_array($value)) {
            throw new UnexpectedValueException('Expected an object in subscription daemon response.');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function call(string $task, array $payload): mixed
    {
        return $this->tasks->call($task, $payload, self::QUEUE, $this->timeoutMs);
    }
}

<?php

namespace App\Services\Billing;

use App\Services\Billing\Data\AccountSummary;
use App\Services\Billing\Data\BillingAccount;
use App\Services\Billing\Data\Invoice;
use App\Services\TaskQueue\QueueServiceException;
use App\Services\TaskQueue\TaskFailedException;
use App\Services\TaskQueue\TaskQueue;
use Illuminate\Pagination\LengthAwarePaginator;
use UnexpectedValueException;

/**
 * Core's read access to the Billing Daemon (../billing-daemon), via the queue service.
 *
 * The daemon owns billing accounts, invoices and payments, and runs billing daily on its own.
 * Accounts reference customers by their Customer Account Daemon id (cus_…).
 *
 * @throws QueueServiceException from every method when the queue service or daemon is unavailable
 * @throws TaskFailedException from every method when the daemon rejects the request
 */
class BillingDirectory
{
    public const QUEUE = 'billing';

    public function __construct(
        private readonly TaskQueue $tasks,
        private readonly int $timeoutMs = 5000,
    ) {}

    /**
     * Look up an account by its id (`acc_…`) or 10-digit account number.
     */
    public function findAccount(string $idOrNumber): ?BillingAccount
    {
        $key = str_starts_with($idOrNumber, 'acc_') ? 'id' : 'account_number';
        $result = $this->call('accounts.get', [$key => $idOrNumber]);

        /** @var array<string, mixed>|null $result */
        return is_array($result) ? BillingAccount::fromArray($result) : null;
    }

    /**
     * @return list<BillingAccount>
     */
    public function accountsForCustomer(string $customerId): array
    {
        return array_map(BillingAccount::fromArray(...), $this->list($this->call('accounts.for_customer', ['customer_id' => $customerId])));
    }

    /**
     * Account summaries for many customers at once (for search results).
     *
     * @param  list<string>  $customerIds
     * @return array<string, list<AccountSummary>> keyed by customer id
     */
    public function summariesForCustomers(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $result = $this->call('accounts.for_customers', ['customer_ids' => array_values(array_unique($customerIds))]);

        if (! is_array($result)) {
            throw new UnexpectedValueException('Malformed accounts.for_customers response.');
        }

        $summaries = [];

        foreach ($result as $customerId => $accounts) {
            $summaries[(string) $customerId] = array_map(AccountSummary::fromArray(...), $this->list($accounts));
        }

        return $summaries;
    }

    /**
     * Invoices newest first, each with its payment attempts.
     *
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function invoices(string $accountId, int $page = 1, int $perPage = 12): LengthAwarePaginator
    {
        $result = $this->call('invoices.list', ['account_id' => $accountId, 'page' => $page, 'per_page' => $perPage]);

        if (! is_array($result) || ! is_int($result['total'] ?? null)) {
            throw new UnexpectedValueException('Malformed invoices.list response.');
        }

        return new LengthAwarePaginator(
            array_map(Invoice::fromArray(...), $this->list($result['data'] ?? null)),
            $result['total'],
            $perPage,
            $page,
        );
    }

    /**
     * The most recent daily billing runs, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function recentRuns(int $limit = 7): array
    {
        return $this->list($this->call('billing.runs', ['limit' => $limit]));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new UnexpectedValueException('Expected a list in billing daemon response.');
        }

        /** @var list<array<string, mixed>> $value */
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

<?php

namespace App\Services\Customers;

use App\Services\Customers\Data\Customer;
use App\Services\TaskQueue\QueueServiceException;
use App\Services\TaskQueue\TaskFailedException;
use App\Services\TaskQueue\TaskQueue;
use Illuminate\Pagination\LengthAwarePaginator;
use UnexpectedValueException;

/**
 * Core's read access to the Customer Account Daemon (../customer-daemon), via the queue service.
 * It knows people only; billing accounts live in the Billing Daemon (see BillingDirectory), and
 * CustomerAccountSearch combines the two for the UI.
 *
 * Results are fetched on demand and never stored in Core: the daemon is the only system holding
 * customer personal data.
 */
class CustomerDirectory
{
    public const QUEUE = 'customers';

    public function __construct(
        private readonly TaskQueue $tasks,
        private readonly int $timeoutMs = 5000,
    ) {}

    /**
     * Search customers. `$query` matches a name ("jo smi"), postcode or outward code, email, or
     * cus_ id. Filters narrow it further (all must match).
     *
     * @param  array{first_name?: string, last_name?: string, postcode?: string, date_of_birth?: string, email?: string, ids?: list<string>}  $filters
     * @return LengthAwarePaginator<int, Customer>
     *
     * @throws QueueServiceException when the queue service or daemon is unavailable
     * @throws TaskFailedException when the daemon rejects the criteria (e.g. none given)
     */
    public function search(?string $query = null, array $filters = [], int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $result = $this->call('customers.search', array_filter([
            'query' => $query === null ? null : (trim($query) ?: null),
            ...$filters,
            'page' => $page,
            'per_page' => $perPage,
        ], fn ($value) => $value !== null && $value !== ''));

        if (! is_array($result) || ! is_array($result['data'] ?? null) || ! is_int($result['total'] ?? null)) {
            throw new UnexpectedValueException('Malformed customers.search response.');
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $result['data'];

        return new LengthAwarePaginator(
            array_map(Customer::fromArray(...), $rows),
            $result['total'],
            $perPage,
            $page,
        );
    }

    public function find(string $id): ?Customer
    {
        $result = $this->call('customers.get', ['id' => $id]);

        /** @var array<string, mixed>|null $result */
        return is_array($result) ? Customer::fromArray($result) : null;
    }

    /**
     * Several customers by id, in the order given. Unknown ids are skipped.
     *
     * @param  list<string>  $ids
     * @return list<Customer>
     */
    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $result = $this->call('customers.get_many', ['ids' => array_values(array_unique($ids))]);

        if (! is_array($result) || ! array_is_list($result)) {
            throw new UnexpectedValueException('Malformed customers.get_many response.');
        }

        /** @var list<array<string, mixed>> $result */
        return array_map(Customer::fromArray(...), $result);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function call(string $task, array $payload): mixed
    {
        return $this->tasks->call($task, $payload, self::QUEUE, $this->timeoutMs);
    }
}

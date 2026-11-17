<?php

namespace App\Services\Customers;

use App\Services\Billing\Data\AccountSummary;
use App\Services\Customers\Data\Customer;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class CustomerSearchResult
{
    /**
     * @param  LengthAwarePaginator<int, Customer>  $customers
     * @param  array<string, list<AccountSummary>>|null  $accounts  keyed by customer id; null if billing was unavailable
     */
    public function __construct(
        public LengthAwarePaginator $customers,
        public ?array $accounts,
    ) {}

    /**
     * @return list<AccountSummary>|null
     */
    public function accountsFor(Customer $customer): ?array
    {
        return $this->accounts === null ? null : ($this->accounts[$customer->id] ?? []);
    }
}

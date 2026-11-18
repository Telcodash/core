<?php

namespace App\Services\Customers;

use App\Services\Billing\BillingDirectory;
use App\Services\Billing\Data\AccountSummary;
use App\Services\Customers\Data\Customer;
use App\Services\Numbers\NumberDirectory;
use App\Services\TaskQueue\QueueServiceException;
use App\Services\TaskQueue\TaskFailedException;
use App\Support\Section;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Customer search across the daemons, for the UI. Each daemon answers what it owns:
 *
 *  - Mobile number → MSISDN Daemon (which account holds it) → Billing Daemon (whose account it is).
 *  - Account number / acc_ id → Billing Daemon (whose account it is).
 *  - Everything else → Customer Daemon. Any owner resolved above is passed as `ids: [customer]`,
 *    so all criteria still have to match.
 *  - The page's billing accounts are then fetched in one batch.
 *
 * If billing is down, name/postcode/email searches still work; accounts are reported as unavailable.
 */
class CustomerAccountSearch
{
    public function __construct(
        private readonly CustomerDirectory $customers,
        private readonly BillingDirectory $billing,
        private readonly NumberDirectory $numbers,
    ) {}

    /**
     * @param  array{last_name?: string, postcode?: string, date_of_birth?: string, account_number?: string, msisdn?: string}  $filters
     *
     * @throws QueueServiceException|TaskFailedException when a daemon needed to answer the search fails
     */
    public function search(?string $query, array $filters, int $page = 1, int $perPage = 20): CustomerSearchResult
    {
        $accountRef = $filters['account_number'] ?? null;
        $msisdn = $filters['msisdn'] ?? null;
        unset($filters['account_number'], $filters['msisdn']);

        // The free-text box also accepts a mobile or account number.
        if ($query !== null && NumberDirectory::isMobileNumber($query) && $msisdn === null) {
            [$msisdn, $query] = [$query, null];
        } elseif ($query !== null && self::isAccountReference($query) && $accountRef === null) {
            [$accountRef, $query] = [$query, null];
        }

        $owners = [];

        if ($msisdn !== null) {
            $owners[] = $this->ownerOfNumber($msisdn);
        }

        if ($accountRef !== null) {
            $owners[] = $this->billing->findAccount($accountRef)?->customerId;
        }

        if ($owners !== []) {
            $owners = array_unique($owners);

            // Unknown number/account, or two identifiers pointing at different customers.
            if (in_array(null, $owners, true) || count($owners) > 1) {
                return new CustomerSearchResult(new LengthAwarePaginator([], 0, $perPage, $page), []);
            }

            /** @var list<string> $owners */
            $filters['ids'] = $owners;
        }

        $customers = $this->customers->search($query, $filters, $page, $perPage);

        return new CustomerSearchResult($customers, $this->accountsFor($customers->items()));
    }

    public static function isAccountReference(string $value): bool
    {
        return (bool) preg_match('/^(\d{10}|acc_[0-9A-Za-z]{26})$/', trim($value));
    }

    /**
     * The customer a mobile number currently belongs to, or null.
     */
    private function ownerOfNumber(string $msisdn): ?string
    {
        $owner = $this->numbers->ownerOf($msisdn);

        // Numbers are assigned to billing accounts today; subscriptions will be added later.
        if ($owner === null || $owner['target_type'] !== 'account') {
            return null;
        }

        return $this->billing->findAccount($owner['target_id'])?->customerId;
    }

    /**
     * @param  array<int, Customer>  $customers
     * @return array<string, list<AccountSummary>>|null null when billing is unavailable
     */
    private function accountsFor(array $customers): ?array
    {
        return Section::load(
            fn () => $this->billing->summariesForCustomers(array_values(array_map(fn (Customer $c) => $c->id, $customers))),
            'Billing accounts are unavailable right now.',
            'Billing accounts unavailable for search results',
        )[0];
    }
}

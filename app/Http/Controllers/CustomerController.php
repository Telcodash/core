<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\Billing\BillingDirectory;
use App\Services\Billing\Data\BillingAccount;
use App\Services\Billing\Data\Invoice;
use App\Services\Customers\CustomerDirectory;
use App\Services\Customers\Data\Customer;
use App\Services\Numbers\NumberDirectory;
use App\Services\Subscriptions\SubscriptionDirectory;
use App\Services\TaskQueue\QueueServiceException;
use App\Services\TaskQueue\TaskFailedException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A customer, as four views sharing one header and sub-navigation:
 *
 *   Overview  a summary of everything below (customer, billing accounts, numbers)
 *   Details   personal details                  (Customer Account Daemon)
 *   Numbers   mobile numbers with tariffs       (MSISDN + Subscription Daemons)
 *   Billing   accounts, invoices and payments   (Billing Daemon)
 *
 * Each view asks only the daemons it shows. Every view needs the customer for its header; if the
 * Customer Account Daemon is down the view shows an error, and any other part degrades on its own.
 */
class CustomerController extends Controller
{
    /** Invoices shown per billing account on the Billing view. */
    private const RECENT_INVOICES = 6;

    public function __construct(
        private readonly CustomerDirectory $customers,
        private readonly BillingDirectory $billing,
        private readonly NumberDirectory $numbers,
        private readonly SubscriptionDirectory $subscriptions,
    ) {}

    public function show(Request $request, Team $current_team, string $customer): Response
    {
        return $this->view('customers/show', 'overview', $request, $current_team, $customer, function (Customer $record) {
            [$accounts, $billingError] = $this->accounts($record->id);

            $numbers = null;
            $numbersError = null;

            if ($accounts !== null) {
                try {
                    $numbers = [];
                    foreach ($this->numbers->linesForAccounts(array_column($accounts, 'id')) as $accountId => $lines) {
                        foreach ($lines as $line) {
                            $numbers[] = [...$line->toArray(), 'account_id' => $accountId];
                        }
                    }
                } catch (QueueServiceException|TaskFailedException $e) {
                    Log::warning('Numbers unavailable for customer overview', ['customer_id' => $record->id, 'error' => $e->getMessage()]);
                    [$numbers, $numbersError] = [null, 'Mobile numbers are unavailable right now.'];
                }
            }

            return [
                'accounts' => $accounts,
                'billingError' => $billingError,
                'numbers' => $numbers,
                'numbersError' => $numbersError,
            ];
        });
    }

    public function details(Request $request, Team $current_team, string $customer): Response
    {
        return $this->view('customers/details', 'details', $request, $current_team, $customer, fn () => []);
    }

    public function numbers(Request $request, Team $current_team, string $customer): Response
    {
        return $this->view('customers/numbers', 'numbers', $request, $current_team, $customer, function (Customer $record) {
            [$accounts, $billingError] = $this->accounts($record->id);

            [$numbers, $numbersError, $subscriptionsError] = $accounts === null
                ? [null, null, null]
                : $this->numberRows($record->id, $accounts);

            return [
                'billingError' => $billingError,
                'numbers' => $numbers,
                'numbersError' => $numbersError,
                'subscriptionsError' => $subscriptionsError,
            ];
        });
    }

    public function billing(Request $request, Team $current_team, string $customer): Response
    {
        return $this->view('customers/billing', 'billing', $request, $current_team, $customer, function (Customer $record) {
            try {
                $accounts = array_map(fn (BillingAccount $account) => [
                    ...$account->toArray(),
                    'invoices' => array_map(
                        fn (Invoice $invoice) => $invoice->toArray(),
                        $this->billing->invoices($account->id, perPage: self::RECENT_INVOICES)->items(),
                    ),
                ], $this->billing->accountsForCustomer($record->id));

                return ['accounts' => $accounts, 'billingError' => null];
            } catch (QueueServiceException|TaskFailedException $e) {
                Log::warning('Billing unavailable for customer page', ['customer_id' => $record->id, 'error' => $e->getMessage()]);

                return ['accounts' => null, 'billingError' => 'Billing information is unavailable right now.'];
            }
        });
    }

    /**
     * Render one of the customer's views: load the customer for the header (404 if unknown), audit
     * the access, then add the view's own props.
     *
     * @param  Closure(Customer): array<string, mixed>  $props
     */
    private function view(string $component, string $section, Request $request, Team $team, string $id, Closure $props): Response
    {
        try {
            $record = $this->customers->find($id);
        } catch (QueueServiceException $e) {
            Log::warning('Customer lookup unavailable', ['customer_id' => $id, 'error' => $e->getMessage()]);

            return Inertia::render($component, [
                'customer' => null,
                'customerId' => $id,
                'error' => 'Customer details are unavailable right now. Please try again shortly.',
            ]);
        }

        abort_if($record === null, 404);

        // Access to personal data is auditable: who opened which customer record, and which part.
        Log::info('Customer record viewed', [
            'customer_id' => $record->id,
            'section' => $section,
            'user_id' => $request->user()?->id,
            'team_id' => $team->id,
        ]);

        return Inertia::render($component, [
            'customer' => [...$record->toArray(), 'age' => $record->age()],
            'customerId' => $record->id,
            'error' => null,
            ...$props($record),
        ]);
    }

    /**
     * The customer's billing accounts (without invoices), or an error message if billing is down.
     *
     * @return array{0: list<array<string, mixed>>|null, 1: string|null}
     */
    private function accounts(string $customerId): array
    {
        try {
            return [array_map(fn (BillingAccount $account) => $account->toArray(), $this->billing->accountsForCustomer($customerId)), null];
        } catch (QueueServiceException|TaskFailedException $e) {
            Log::warning('Billing unavailable for customer page', ['customer_id' => $customerId, 'error' => $e->getMessage()]);

            return [null, 'Billing information is unavailable right now.'];
        }
    }

    /**
     * One row per live number: the number itself (MSISDN Daemon) joined with its line, meaning
     * tariff, locked-in price and allowances (Subscription Daemon). If subscriptions are down the
     * numbers still show, without tariffs.
     *
     * @param  list<array<string, mixed>>  $accounts
     * @return array{0: list<array<string, mixed>>|null, 1: string|null, 2: string|null}
     */
    private function numberRows(string $customerId, array $accounts): array
    {
        /** @var list<string> $accountIds */
        $accountIds = array_column($accounts, 'id');
        $accountNumbers = array_column($accounts, 'account_number', 'id');

        try {
            $byAccount = $this->numbers->linesForAccounts($accountIds);
        } catch (QueueServiceException|TaskFailedException $e) {
            Log::warning('Numbers unavailable for customer page', ['customer_id' => $customerId, 'error' => $e->getMessage()]);

            return [null, 'Mobile numbers are unavailable right now.', null];
        }

        $subscriptionsError = null;

        try {
            $lines = [];
            foreach ($this->subscriptions->linesForAccounts($accountIds) as $accountLines) {
                foreach ($accountLines as $line) {
                    $lines[$line->msisdn] = $line;
                }
            }
        } catch (QueueServiceException|TaskFailedException $e) {
            Log::warning('Subscriptions unavailable for customer page', ['customer_id' => $customerId, 'error' => $e->getMessage()]);
            $lines = null;
            $subscriptionsError = 'Tariffs are unavailable right now.';
        }

        $rows = [];

        foreach ($byAccount as $accountId => $accountLines) {
            foreach ($accountLines as $number) {
                $rows[] = [
                    ...$number->toArray(),
                    'account_id' => $accountId,
                    'account_number' => $accountNumbers[$accountId] ?? null,
                    // null when subscriptions are down; false when the number has no line (shouldn't happen).
                    'subscription' => $lines === null ? null : (isset($lines[$number->msisdn]) ? $lines[$number->msisdn]->toArray() : false),
                ];
            }
        }

        return [$rows, null, $subscriptionsError];
    }
}

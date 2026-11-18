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
use App\Services\TaskQueue\TaskFailedException;
use App\Support\Section;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * A customer, as four views sharing one header and sub-navigation:
 *
 *   Overview  a summary of everything below (customer, billing accounts, numbers)
 *   Details   personal details                  (Customer Account Daemon)
 *   Numbers   mobile numbers with tariffs       (MSISDN + Subscription Daemons); numbers can be given
 *             a friendly name here
 *   Billing   accounts, invoices and payments   (Billing Daemon)
 *
 * Each view asks only the daemons it shows. Every view needs the customer for its header; if the
 * Customer Account Daemon is down the view shows an error, and any other part degrades on its own.
 * Each part loads inside a Section boundary: whatever goes wrong there, whether an outage, a
 * refusal, a malformed response or a bug, only that part shows an error, never the whole page.
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
                [$numbers, $numbersError] = Section::load(function () use ($accounts) {
                    $numbers = [];
                    $accountNumbers = array_column($accounts, 'account_number', 'id');
                    foreach ($this->numbers->linesForAccounts(array_column($accounts, 'id')) as $accountId => $lines) {
                        foreach ($lines as $line) {
                            $numbers[] = [...$line->toArray(), 'account_id' => $accountId, 'account_number' => $accountNumbers[$accountId] ?? null];
                        }
                    }

                    return $numbers;
                }, 'Mobile numbers are unavailable right now.', 'Numbers unavailable for customer overview', ['customer_id' => $record->id]);
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

    /**
     * Give one of the customer's numbers a friendly name (or clear it). The MSISDN Daemon checks the
     * number is live on one of this customer's accounts, so a number can't be renamed from another
     * customer's page.
     */
    public function renameNumber(Request $request, Team $current_team, string $customer, string $msisdn): RedirectResponse
    {
        /** @var array{friendly_name: string|null} $validated */
        $validated = $request->validate([
            'friendly_name' => ['present', 'nullable', 'string', 'max:40'],
        ]);

        try {
            $accountIds = array_map(fn (BillingAccount $account) => $account->id, $this->billing->accountsForCustomer($customer));
            abort_if($accountIds === [], 404);

            $name = $this->numbers->rename($msisdn, $accountIds, $validated['friendly_name']);
        } catch (TaskFailedException $e) {
            return back()->withErrors(['friendly_name' => preg_replace('/^Invalid payload: /', '', $e->reason)]);
        } catch (Throwable $e) {
            Section::absorb($e, 'Number rename unavailable', ['customer_id' => $customer]); // rethrows the 404

            return back()->withErrors(['friendly_name' => 'Numbers can\'t be renamed right now. Please try again shortly.']);
        }

        // Changes to a customer's record are auditable, like views. The name itself isn't logged.
        Log::info('Customer number renamed', [
            'customer_id' => $customer,
            'msisdn' => $msisdn,
            'cleared' => $name === null,
            'user_id' => $request->user()?->id,
            'team_id' => $current_team->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $name === null ? __('Friendly name removed.') : __('Number renamed.')]);

        return back();
    }

    public function billing(Request $request, Team $current_team, string $customer): Response
    {
        return $this->view('customers/billing', 'billing', $request, $current_team, $customer, function (Customer $record) {
            [$accounts, $billingError] = Section::load(
                fn () => array_map(fn (BillingAccount $account) => [
                    ...$account->toArray(),
                    'invoices' => array_map(
                        fn (Invoice $invoice) => $invoice->toArray(),
                        $this->billing->invoices($account->id, perPage: self::RECENT_INVOICES)->items(),
                    ),
                ], $this->billing->accountsForCustomer($record->id)),
                'Billing information is unavailable right now.',
                'Billing unavailable for customer page',
                ['customer_id' => $record->id],
            );

            return ['accounts' => $accounts, 'billingError' => $billingError];
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
        // Not found is a 404; anything else wrong with the customer shows an error in place of the view.
        [$found, $error] = Section::load(function () use ($id) {
            $record = $this->customers->find($id);

            return $record === null ? false : [$record, [...$record->toArray(), 'age' => $record->age()]];
        }, 'Customer details are unavailable right now. Please try again shortly.', 'Customer lookup unavailable', ['customer_id' => $id]);

        abort_if($found === false, 404);

        if ($found === null) {
            return Inertia::render($component, [
                'customer' => null,
                'customerId' => $id,
                'error' => $error,
            ]);
        }

        [$record, $header] = $found;

        // Access to personal data is auditable: who opened which customer record, and which part.
        Log::info('Customer record viewed', [
            'customer_id' => $record->id,
            'section' => $section,
            'user_id' => $request->user()?->id,
            'team_id' => $team->id,
        ]);

        return Inertia::render($component, [
            'customer' => $header,
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
        return Section::load(
            fn () => array_map(fn (BillingAccount $account) => $account->toArray(), $this->billing->accountsForCustomer($customerId)),
            'Billing information is unavailable right now.',
            'Billing unavailable for customer page',
            ['customer_id' => $customerId],
        );
    }

    /**
     * One row per live number: the number itself (MSISDN Daemon) joined with its line, meaning
     * tariff, locked-in price and allowances (Subscription Daemon). If subscriptions fail the
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

        [$numbers, $numbersError] = Section::load(function () use ($accountIds, $accountNumbers) {
            $numbers = [];
            foreach ($this->numbers->linesForAccounts($accountIds) as $accountId => $accountLines) {
                foreach ($accountLines as $number) {
                    $numbers[] = [...$number->toArray(), 'account_id' => $accountId, 'account_number' => $accountNumbers[$accountId] ?? null];
                }
            }

            return $numbers;
        }, 'Mobile numbers are unavailable right now.', 'Numbers unavailable for customer page', ['customer_id' => $customerId]);

        if ($numbers === null) {
            return [null, $numbersError, null];
        }

        /** @var array<string, array<string, mixed>>|null $lines tariff line per msisdn */
        [$lines, $subscriptionsError] = Section::load(function () use ($accountIds) {
            $lines = [];
            foreach ($this->subscriptions->linesForAccounts($accountIds) as $accountLines) {
                foreach ($accountLines as $line) {
                    $lines[$line->msisdn] = $line->toArray();
                }
            }

            return $lines;
        }, 'Tariffs are unavailable right now.', 'Subscriptions unavailable for customer page', ['customer_id' => $customerId]);

        $rows = array_map(fn (array $number) => [
            ...$number,
            // null when subscriptions are unavailable; false when the number has no line (shouldn't happen).
            'subscription' => $lines === null ? null : ($lines[$number['msisdn']] ?? false),
        ], $numbers);

        return [$rows, null, $subscriptionsError];
    }
}

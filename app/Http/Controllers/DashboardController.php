<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerSearchRequest;
use App\Models\Team;
use App\Services\Billing\Data\AccountSummary;
use App\Services\Customers\CustomerAccountSearch;
use App\Services\Customers\CustomerSearchResult;
use App\Services\Customers\Data\Customer;
use App\Services\TaskQueue\QueueServiceException;
use App\Services\TaskQueue\TaskFailedException;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The team dashboard: customer search across the Customer and Billing daemons.
 */
class DashboardController extends Controller
{
    private const PER_PAGE = 20;

    public function __invoke(CustomerSearchRequest $request, Team $current_team, CustomerAccountSearch $search): Response
    {
        $results = null;
        $error = null;
        $billingUnavailable = false;

        if ($request->hasCriteria()) {
            try {
                $found = $search->search($request->searchTerm(), $request->filters(), (int) ($request->validated('page') ?? 1), self::PER_PAGE);
                $billingUnavailable = $found->accounts === null;
                $results = $found->customers
                    ->withPath($request->url())
                    ->withQueryString()
                    ->through(fn (Customer $customer) => self::summary($customer, $found));
            } catch (QueueServiceException $e) {
                Log::warning('Customer search unavailable', ['error' => $e->getMessage()]);
                $error = 'Customer search is unavailable right now. Please try again shortly.';
            } catch (TaskFailedException $e) {
                $error = 'The customer service rejected this search. Check the search terms and try again.';
                Log::notice('Customer search rejected', ['reason' => $e->reason]);
            }
        }

        return Inertia::render('dashboard', [
            'search' => [
                'q' => $request->searchTerm() ?? '',
                ...array_fill_keys(CustomerSearchRequest::FILTERS, ''),
                ...$request->filters(),
            ],
            'results' => $results,
            'error' => $error,
            'billingUnavailable' => $billingUnavailable,
        ]);
    }

    /**
     * The fields shown in the results table: enough to identify the right customer, no more.
     *
     * @return array<string, mixed>
     */
    private static function summary(Customer $customer, CustomerSearchResult $found): array
    {
        $accounts = $found->accountsFor($customer);

        return [
            'id' => $customer->id,
            'name' => $customer->fullName(),
            'date_of_birth' => $customer->dateOfBirth->toDateString(),
            'status' => $customer->status,
            'address' => implode(', ', array_filter([$customer->address->line1, $customer->address->line2, $customer->address->town])),
            'postcode' => $customer->address->postcode,
            'accounts' => $accounts === null ? null : array_map(fn (AccountSummary $account) => [
                'account_number' => $account->accountNumber,
                'status' => $account->status,
                'overdue_pence' => $account->overduePence,
            ], $accounts),
        ];
    }
}

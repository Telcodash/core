<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\Billing\BillingDirectory;
use App\Services\Customers\CustomerDirectory;
use App\Services\Numbers\NumberDirectory;
use App\Services\TaskQueue\QueueServiceException;
use App\Services\TaskQueue\TaskFailedException;
use App\Services\Usage\UsageDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class NumberUsageController extends Controller
{
    /** Billing periods shown, including the current one. */
    private const PERIODS = 6;

    /**
     * A customer's number's usage (calls, texts, data) per billing period, from the Usage Daemon.
     *
     * Usage is shown only for a number currently assigned to one of this customer's accounts: the
     * MSISDN Daemon says which account holds the number, and the Billing Daemon says whose account
     * that is and when its billing periods start. If either can't confirm it, nothing is shown.
     */
    public function show(
        Request $request,
        Team $current_team,
        string $customer,
        string $msisdn,
        NumberDirectory $numbers,
        BillingDirectory $billing,
        CustomerDirectory $customers,
        UsageDirectory $usage,
    ): Response {
        $number = [
            'msisdn' => $msisdn,
            'national' => '0'.substr($msisdn, 2, 4).' '.substr($msisdn, 6),
        ];

        try {
            $owner = $numbers->ownerOf($msisdn);
            $account = $owner !== null && $owner['target_type'] === 'account' ? $billing->findAccount($owner['target_id']) : null;
        } catch (QueueServiceException|TaskFailedException $e) {
            Log::warning('Number ownership unavailable for usage page', ['msisdn' => $msisdn, 'error' => $e->getMessage()]);

            return $this->render(['id' => $customer, 'full_name' => null, 'status' => null, 'created_at' => null], $number, null, 'Usage is unavailable right now: this number’s account couldn’t be checked. Please try again shortly.');
        }

        abort_if($account === null || $account->customerId !== $customer, 404);

        // Usage is communications data about a person: who looked at whose is auditable.
        Log::info('Number usage viewed', [
            'customer_id' => $customer,
            'msisdn' => $msisdn,
            'user_id' => $request->user()?->id,
            'team_id' => $current_team->id,
        ]);

        $number += ['account_number' => $account->accountNumber, 'billing_day' => $account->billingDay];

        try {
            $record = $customers->find($customer);
        } catch (QueueServiceException|TaskFailedException) {
            $record = null; // Only used for the header and breadcrumb.
        }

        $header = [
            'id' => $customer,
            'full_name' => $record?->fullName(),
            'status' => $record?->status,
            'created_at' => $record?->createdAt->toIso8601String(),
        ];

        try {
            $report = $usage->forNumber($msisdn, $account->billingDay, self::PERIODS)->toArray();
        } catch (QueueServiceException|TaskFailedException $e) {
            Log::warning('Usage unavailable', ['msisdn' => $msisdn, 'error' => $e->getMessage()]);

            return $this->render($header, $number, null, 'Usage is unavailable right now. Please try again shortly.');
        }

        return $this->render($header, $number, $report, null);
    }

    /**
     * @param  array{id: string, full_name: ?string, status: ?string, created_at: ?string}  $customer  the shared customer header
     * @param  array<string, mixed>  $number
     * @param  array<string, mixed>|null  $usage
     */
    private function render(array $customer, array $number, ?array $usage, ?string $error): Response
    {
        return Inertia::render('customers/usage', [
            'customer' => $customer,
            'customerId' => $customer['id'],
            'number' => $number,
            'usage' => $usage,
            'error' => $error,
        ]);
    }
}

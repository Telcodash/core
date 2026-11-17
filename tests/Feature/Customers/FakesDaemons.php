<?php

namespace Tests\Feature\Customers;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Fake the queue service as if the Customer and Billing daemons were behind it.
 * Each task name maps to its result, a callable(payload) returning one, or a failure.
 */
trait FakesDaemons
{
    /**
     * @param  array<string, mixed>  $results  task name => result | callable(array $payload): mixed | self::down()
     */
    protected function fakeDaemons(array $results): void
    {
        Http::fake(function (Request $request) use ($results) {
            $body = json_decode($request->body(), true);
            $task = $body['name'] ?? null;

            if (! array_key_exists($task, $results)) {
                return Http::response(['state' => 'failed', 'error' => "Unexpected task [{$task}]", 'result' => null]);
            }

            $result = $results[$task];

            if ($result === 'DOWN') {
                return Http::response(['error' => "No workers are consuming queue [{$body['queue']}]"], 503);
            }

            $value = is_callable($result) ? $result($body['payload'] ?? []) : $result;

            return Http::response(['state' => 'completed', 'result' => $value, 'error' => null]);
        });
    }

    protected static function down(): string
    {
        return 'DOWN';
    }

    /**
     * @return list<string>
     */
    protected function sentTasks(): array
    {
        return Http::recorded()
            ->map(fn (array $pair) => json_decode($pair[0]->body(), true)['name'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected static function customerFixture(string $id = 'cus_01A24B7MY1G5VD193QWE5EZXAV'): array
    {
        return [
            'id' => $id, 'title' => 'Dr', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
            'date_of_birth' => '1978-05-03', 'email' => 'ada@example.co.uk', 'contact_phone' => '07700 900255',
            'address' => ['line_1' => 'Flat 4', 'line_2' => '12 Station Road', 'town' => 'Manchester', 'county' => 'Greater Manchester', 'postcode' => 'M14 5RG', 'country' => 'GB'],
            'status' => 'active', 'created_at' => '2015-10-21T04:41:37Z', 'updated_at' => '2019-06-22T12:08:26Z',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function accountFixture(string $customerId = 'cus_01A24B7MY1G5VD193QWE5EZXAV', array $overrides = []): array
    {
        return $overrides + [
            'id' => 'acc_01A7DM1QT4J21DF5TF9A6EH132',
            'customer_id' => $customerId,
            'account_number' => '4883209140',
            'status' => 'suspended',
            'suspension_reason' => 'arrears',
            'monthly_charge_pence' => 2500,
            'billing_day' => 12,
            'next_bill_on' => '2026-12-12',
            'balance_pence' => 5400,
            'overdue_pence' => 2900,
            'payment_method' => [
                'type' => 'card', 'label' => 'Visa ****4242', 'sort_code' => null, 'account_last4' => null,
                'mandate_reference' => null, 'brand' => 'Visa', 'last4' => '4242', 'expiry' => '2026-03', 'expired' => true,
            ],
            'billing_address' => null,
            'created_at' => '2015-12-25T22:27:20Z',
            'updated_at' => '2015-12-25T22:27:20Z',
            'closed_at' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function lineFixture(string $national = '07700 910123', string $status = 'barred'): array
    {
        return [
            'msisdn' => '44'.substr(str_replace(' ', '', $national), 1),
            'national' => $national,
            'classification' => 'gold',
            'status' => $status,
            'barred_reason' => $status === 'barred' ? 'arrears' : null,
            'assignment_id' => 'asg_01A7DM1QT4J21DF5TF9A6EH132',
            'assigned_at' => '2020-03-01T10:00:00.000Z',
        ];
    }

    /**
     * A subscription line as the Subscription Daemon returns it.
     *
     * @return array<string, mixed>
     */
    protected static function subscriptionFixture(string $msisdn = '447700910123', string $accountId = 'acc_01A7DM1QT4J21DF5TF9A6EH132'): array
    {
        return [
            'id' => 'lin_01A7DM1QT4J21DF5TF9A6EH132', 'msisdn' => $msisdn, 'account_id' => $accountId,
            'tariff_id' => 'trf_01A7DM1QT4J21DF5TF9A6EH132', 'tariff_name' => 'Everyday 30GB', 'monthly_price_pence' => 1600,
            'status' => 'active', 'started_on' => '2020-03-01', 'ceased_on' => null,
            'minutes' => null, 'texts' => null, 'data_mb' => 30720,
            'eu_roaming' => true, 'international_roaming' => false, 'five_g_plus' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function tariffFixture(array $overrides = []): array
    {
        return $overrides + [
            'id' => 'trf_01A7DM1QT4J21DF5TF9A6EH132', 'name' => 'Everyday 30GB', 'description' => null,
            'monthly_price_pence' => 1600, 'minutes' => null, 'texts' => null, 'data_mb' => 30720,
            'eu_roaming' => true, 'international_roaming' => false, 'five_g_plus' => true,
            'available' => true, 'active_lines' => 120, 'created_at' => '2026-01-01T00:00:00Z', 'updated_at' => '2026-01-01T00:00:00Z',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function invoiceFixture(): array
    {
        return [
            'id' => 'inv_01A7DM1QT4J21DF5TF9A6EH132',
            'account_id' => 'acc_01A7DM1QT4J21DF5TF9A6EH132',
            'invoice_number' => 'INV-00000042',
            'period_start' => '2026-10-12', 'period_end' => '2026-11-11',
            'issued_on' => '2026-10-12', 'due_on' => '2026-10-26',
            'line_items' => [
                ['description' => '07700 910123 · Unlimited · 2026-05-12 to 2026-06-11', 'amount_pence' => 2500, 'kind' => 'plan', 'msisdn' => '447700910123'],
                ['description' => '07700 910123 · Calls to international numbers: 3 mins at £1.20', 'amount_pence' => 360, 'kind' => 'extra', 'msisdn' => '447700910123'],
                ['description' => 'Out-of-bundle usage', 'amount_pence' => 40],
            ],
            'amount_pence' => 2900,
            'status' => 'overdue',
            'paid_on' => null,
            'payments' => [[
                'id' => 'pay_01A7DM1QT4J21DF5TF9A6EH132', 'invoice_id' => 'inv_01A7DM1QT4J21DF5TF9A6EH132', 'attempt' => 1,
                'initiated_by' => 'scheduled', 'method' => 'card', 'amount_pence' => 2900, 'status' => 'failed',
                'failure_code' => 'expired_card', 'failure_message' => 'Declined: expired card',
                'processor_reference' => 'ch_abc', 'attempted_on' => '2026-10-26',
            ]],
        ];
    }
}

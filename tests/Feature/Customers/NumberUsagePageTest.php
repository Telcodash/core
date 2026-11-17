<?php

namespace Tests\Feature\Customers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NumberUsagePageTest extends TestCase
{
    use FakesDaemons, RefreshDatabase;

    private const CUSTOMER_ID = 'cus_01A24B7MY1G5VD193QWE5EZXAV';

    private const ACCOUNT_ID = 'acc_01A7DM1QT4J21DF5TF9A6EH132';

    private const MSISDN = '447700910123';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config([
            'inertia.ssr.enabled' => false,
            'services.queue.url' => 'http://queue.test',
            'services.queue.token' => 'test-token',
        ]);
        $this->user = User::factory()->advisor()->create();
    }

    private function usageUrl(string $customer = self::CUSTOMER_ID, string $msisdn = self::MSISDN): string
    {
        return route('customers.usage', ['current_team' => $this->user->team->slug, 'customer' => $customer, 'msisdn' => $msisdn]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function owner(string $accountId = self::ACCOUNT_ID): array
    {
        return ['msisdn' => self::MSISDN, 'assignment' => ['target_type' => 'account', 'target_id' => $accountId, 'status' => 'active']];
    }

    /**
     * @return array<string, mixed>
     */
    private static function period(string $start, string $end, int $elapsed, int $dataMb): array
    {
        return [
            'start' => $start, 'end' => $end, 'days_in_period' => 30, 'days_elapsed' => $elapsed,
            'tariff_name' => 'Essentials 2GB',
            'allowances' => ['minutes' => 250, 'texts' => 250, 'data_mb' => 2048, 'eu_roaming' => false, 'international_roaming' => false],
            'totals' => ['minutes' => 120, 'texts' => 40, 'data_mb' => $dataMb, 'eu_roaming_days' => 2, 'world_roaming_days' => 0, 'international_minutes' => 0],
            'days' => [
                ['date' => $start, 'minutes' => 120, 'texts' => 40, 'data_mb' => $dataMb, 'roaming' => 'eu', 'international_minutes' => 0],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function usageFixture(): array
    {
        return [
            'msisdn' => self::MSISDN,
            'through' => '2026-11-16',
            'line' => ['line_id' => 'lin_x', 'account_id' => self::ACCOUNT_ID, 'tariff_name' => 'Essentials 2GB', 'started_on' => '2020-03-01', 'allowances' => []],
            'periods' => [
                self::period('2026-09-12', '2026-10-11', 30, 2500),
                self::period('2026-10-12', '2026-11-11', 30, 1900),
                self::period('2026-11-12', '2026-12-11', 5, 400),
            ],
        ];
    }

    public function test_shows_a_customers_number_usage_per_billing_period(): void
    {
        Log::spy();
        $this->fakeDaemons([
            'numbers.get' => self::owner(),
            'accounts.get' => self::accountFixture(),
            'customers.get' => self::customerFixture(),
            'usage.for_number' => self::usageFixture(),
        ]);

        $this->actingAs($this->user)->get($this->usageUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/usage')
                ->where('customer.id', self::CUSTOMER_ID)
                ->where('customer.full_name', 'Dr Ada Lovelace')
                ->where('customer.status', 'active')
                ->where('number.national', '07700 910123')
                ->where('number.account_number', '4883209140')
                ->where('number.billing_day', 12)
                ->where('usage.tariff_name', 'Essentials 2GB')
                ->has('usage.periods', 3)
                ->where('usage.periods.0.totals.data_mb', 2500)
                ->where('usage.periods.0.allowances.data_mb', 2048)
                ->where('usage.periods.2.days.0.roaming', 'eu')
                ->where('error', null));

        // Periods follow the account's billing day.
        Http::assertSent(fn (Request $r) => $r['name'] === 'usage.for_number'
            && $r['queue'] === 'usage'
            && json_decode($r->body(), true)['payload'] === ['msisdn' => self::MSISDN, 'billing_day' => 12, 'periods' => 6]);

        Log::shouldHaveReceived('info')->with('Number usage viewed', \Mockery::on(fn (array $context) => $context['msisdn'] === self::MSISDN));
    }

    public function test_a_number_not_on_this_customers_accounts_is_not_found(): void
    {
        $this->fakeDaemons([
            'numbers.get' => self::owner(),
            'accounts.get' => self::accountFixture('cus_01A24B7MY1G5VD193QWE5EZXZZ'),
        ]);

        $this->actingAs($this->user)->get($this->usageUrl())->assertNotFound();
        $this->assertNotContains('usage.for_number', $this->sentTasks());
    }

    public function test_an_unassigned_number_is_not_found(): void
    {
        $this->fakeDaemons(['numbers.get' => ['msisdn' => self::MSISDN, 'assignment' => null]]);

        $this->actingAs($this->user)->get($this->usageUrl())->assertNotFound();
        $this->assertSame(['numbers.get'], $this->sentTasks());
    }

    public function test_usage_is_withheld_when_ownership_cant_be_checked(): void
    {
        $this->fakeDaemons(['numbers.get' => self::down()]);

        $this->actingAs($this->user)->get($this->usageUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage', null)
                ->where('error', fn (string $error) => str_contains($error, 'couldn’t be checked')));

        $this->assertNotContains('usage.for_number', $this->sentTasks());
    }

    public function test_the_page_degrades_when_the_usage_daemon_is_down(): void
    {
        $this->fakeDaemons([
            'numbers.get' => self::owner(),
            'accounts.get' => self::accountFixture(),
            'customers.get' => self::down(),
            'usage.for_number' => self::down(),
        ]);

        $this->actingAs($this->user)->get($this->usageUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage', null)
                ->where('customer.full_name', null)
                ->where('number.account_number', '4883209140')
                ->where('error', 'Usage is unavailable right now. Please try again shortly.'));
    }

    public function test_guests_other_teams_and_malformed_numbers_are_refused(): void
    {
        $other = User::factory()->create();

        $this->get($this->usageUrl())->assertRedirect(route('login'));
        $this->actingAs($other)->get($this->usageUrl())->assertForbidden();
        $this->actingAs($this->user)
            ->get("/{$this->user->team->slug}/customers/".self::CUSTOMER_ID.'/numbers/07700910123/usage')
            ->assertNotFound();
    }
}

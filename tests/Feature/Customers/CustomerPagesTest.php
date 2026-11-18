<?php

namespace Tests\Feature\Customers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerPagesTest extends TestCase
{
    use FakesDaemons, RefreshDatabase;

    private const CUSTOMER_ID = 'cus_01A24B7MY1G5VD193QWE5EZXAV';

    private const ACCOUNT_ID = 'acc_01A7DM1QT4J21DF5TF9A6EH132';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config([
            'inertia.ssr.enabled' => false, // keeps Http fakes/assertions to queue-service calls only
            'services.queue.url' => 'http://queue.test',
            'services.queue.token' => 'test-token',
        ]);
        $this->user = User::factory()->advisor()->create();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function searchUrl(array $query = []): string
    {
        return route('dashboard', ['current_team' => $this->user->team->slug, ...$query]);
    }

    private function customerUrl(string $id = self::CUSTOMER_ID, string $view = 'show'): string
    {
        return route("customers.{$view}", ['current_team' => $this->user->team->slug, 'customer' => $id]);
    }

    /**
     * @param  list<array<string, mixed>>  $customers
     * @return array<string, mixed>
     */
    private static function page(array $customers, int $total = 21): array
    {
        return ['data' => $customers, 'total' => $total, 'page' => 1, 'per_page' => 20];
    }

    public function test_guests_and_other_teams_cannot_search_or_view_customers(): void
    {
        $other = User::factory()->create();

        $this->get($this->searchUrl(['q' => 'ada']))->assertRedirect(route('login'));
        $this->get($this->customerUrl())->assertRedirect(route('login'));
        $this->actingAs($other)->get($this->searchUrl(['q' => 'ada']))->assertForbidden();
        $this->actingAs($other)->get($this->customerUrl())->assertForbidden();
    }

    public function test_dashboard_without_criteria_calls_nothing(): void
    {
        Http::fake();

        $this->actingAs($this->user)->get($this->searchUrl())
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('results', null)
                ->where('billingUnavailable', false));

        Http::assertNothingSent();
    }

    public function test_name_search_combines_customers_with_their_billing_accounts(): void
    {
        $this->fakeDaemons([
            'customers.search' => self::page([self::customerFixture()]),
            'accounts.for_customers' => fn (array $p) => [$p['customer_ids'][0] => [[
                'id' => 'acc_01A7DM1QT4J21DF5TF9A6EH132', 'account_number' => '4883209140',
                'status' => 'suspended', 'balance_pence' => 5400, 'overdue_pence' => 2900,
            ]]],
        ]);

        $this->actingAs($this->user)->get($this->searchUrl(['q' => 'ada lov', 'postcode' => 'm14']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('results.total', 21)
                ->where('results.data.0.name', 'Dr Ada Lovelace')
                ->where('results.data.0.address', 'Flat 4, 12 Station Road, Manchester')
                ->where('results.data.0.accounts.0', ['account_number' => '4883209140', 'status' => 'suspended', 'overdue_pence' => 2900])
                ->missing('results.data.0.email')
                ->where('results.links.1.url', fn (string $url) => str_contains($url, 'q=ada') && str_contains($url, 'page=1'))
                ->where('billingUnavailable', false));

        $this->assertSame(['customers.search', 'accounts.for_customers'], $this->sentTasks());
    }

    public function test_account_number_search_resolves_the_owner_via_billing_then_applies_other_filters(): void
    {
        $this->fakeDaemons([
            'accounts.get' => self::accountFixture(),
            'customers.search' => self::page([self::customerFixture()], 1),
            'accounts.for_customers' => [self::CUSTOMER_ID => []],
        ]);

        $this->actingAs($this->user)->get($this->searchUrl(['q' => '4883209140', 'last_name' => 'Lov']))
            ->assertInertia(fn (Assert $page) => $page->where('results.data.0.id', self::CUSTOMER_ID));

        Http::assertSent(fn (Request $r) => $r['name'] === 'accounts.get'
            && json_decode($r->body(), true)['payload'] === ['account_number' => '4883209140']);
        Http::assertSent(fn (Request $r) => $r['name'] === 'customers.search'
            && json_decode($r->body(), true)['payload'] === ['last_name' => 'Lov', 'ids' => [self::CUSTOMER_ID], 'page' => 1, 'per_page' => 20]);
    }

    public function test_unknown_account_number_returns_no_results_without_asking_the_customer_daemon(): void
    {
        $this->fakeDaemons(['accounts.get' => null]);

        $this->actingAs($this->user)->get($this->searchUrl(['account_number' => '0000000000']))
            ->assertInertia(fn (Assert $page) => $page->where('results.total', 0)->where('error', null));

        $this->assertSame(['accounts.get'], $this->sentTasks());
    }

    public function test_search_still_works_when_billing_is_down(): void
    {
        $this->fakeDaemons([
            'customers.search' => self::page([self::customerFixture()]),
            'accounts.for_customers' => self::down(),
        ]);

        $this->actingAs($this->user)->get($this->searchUrl(['q' => 'ada']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('results.data.0.name', 'Dr Ada Lovelace')
                ->where('results.data.0.accounts', null)
                ->where('billingUnavailable', true)
                ->where('error', null));
    }

    public function test_invalid_filters_are_rejected_before_reaching_any_daemon(): void
    {
        Http::fake();

        $this->actingAs($this->user)
            ->from($this->searchUrl())
            ->get($this->searchUrl(['postcode' => 'not a postcode', 'account_number' => '123']))
            ->assertRedirect($this->searchUrl())
            ->assertSessionHasErrors(['postcode', 'account_number']);

        Http::assertNothingSent();
    }

    public function test_search_shows_a_friendly_error_when_the_customer_daemon_is_down(): void
    {
        $this->fakeDaemons(['customers.search' => self::down()]);

        $this->actingAs($this->user)->get($this->searchUrl(['q' => 'ada']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('results', null)
                ->where('error', 'Customer search is unavailable right now. Please try again shortly.'));
    }

    public function test_a_malformed_search_response_shows_an_error_not_a_500(): void
    {
        $this->fakeDaemons(['customers.search' => ['data' => 'nonsense']]);

        $this->actingAs($this->user)->get($this->searchUrl(['q' => 'ada']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('results', null)
                ->where('error', 'Customer search failed. Please try again shortly.'));
    }

    public function test_overview_summarises_details_accounts_and_numbers_and_audits_access(): void
    {
        Log::spy();
        $this->fakeDaemons([
            'customers.get' => self::customerFixture(),
            'accounts.for_customer' => [self::accountFixture()],
            'numbers.for_accounts' => fn (array $p) => [$p['account_ids'][0] => [self::lineFixture()]],
        ]);

        $this->actingAs($this->user)->get($this->customerUrl())
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/show')
                ->where('customerId', self::CUSTOMER_ID)
                ->where('customer.full_name', 'Dr Ada Lovelace')
                ->where('customer.age', fn (int $age) => $age >= 48)
                ->where('accounts.0.account_number', '4883209140')
                ->where('accounts.0.overdue_pence', 2900)
                ->where('accounts.0.suspension_reason', 'arrears')
                ->missing('accounts.0.invoices')
                ->where('numbers.0.national', '07700 910123')
                ->where('numbers.0.status', 'barred')
                ->where('numbers.0.account_id', 'acc_01A7DM1QT4J21DF5TF9A6EH132')
                ->where('numbers.0.account_number', '4883209140')
                ->where('numbers.0.friendly_name', 'Work phone')
                ->missing('numbers.0.subscription')
                ->where('billingError', null)
                ->where('numbersError', null));

        // A summary: no invoices or tariffs are fetched for it.
        $this->assertSame(['customers.get', 'accounts.for_customer', 'numbers.for_accounts'], $this->sentTasks());

        Log::shouldHaveReceived('info')->with('Customer record viewed', [
            'customer_id' => self::CUSTOMER_ID,
            'section' => 'overview',
            'user_id' => $this->user->id,
            'team_id' => $this->user->team_id,
        ]);
    }

    public function test_details_view_shows_personal_details_only(): void
    {
        $this->fakeDaemons(['customers.get' => self::customerFixture()]);

        $this->actingAs($this->user)->get($this->customerUrl(view: 'details'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/details')
                ->where('customer.email', 'ada@example.co.uk')
                ->where('customer.address.postcode', 'M14 5RG')
                ->missing('accounts')
                ->missing('numbers'));

        $this->assertSame(['customers.get'], $this->sentTasks());
    }

    public function test_numbers_view_joins_numbers_with_their_tariffs(): void
    {
        $this->fakeDaemons([
            'customers.get' => self::customerFixture(),
            'accounts.for_customer' => [self::accountFixture()],
            'numbers.for_accounts' => fn (array $p) => [$p['account_ids'][0] => [self::lineFixture()]],
            'lines.for_accounts' => fn (array $p) => [$p['account_ids'][0] => [self::subscriptionFixture()]],
        ]);

        $this->actingAs($this->user)->get($this->customerUrl(view: 'numbers'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/numbers')
                ->where('numbers.0.national', '07700 910123')
                ->where('numbers.0.status', 'barred')
                ->where('numbers.0.classification', 'gold')
                ->where('numbers.0.account_number', '4883209140')
                ->where('numbers.0.friendly_name', 'Work phone')
                ->where('numbers.0.subscription.tariff_name', 'Everyday 30GB')
                ->where('numbers.0.subscription.monthly_price_pence', 1600)
                ->where('numbers.0.subscription.allowances', [
                    'minutes' => null, 'texts' => null, 'data_mb' => 30720,
                    'eu_roaming' => true, 'international_roaming' => false, 'five_g_plus' => true,
                ])
                ->missing('accounts')
                ->where('numbersError', null)
                ->where('subscriptionsError', null));

        $this->assertNotContains('invoices.list', $this->sentTasks());
    }

    public function test_billing_view_shows_accounts_with_invoices_and_payments(): void
    {
        $this->fakeDaemons([
            'customers.get' => self::customerFixture(),
            'accounts.for_customer' => [self::accountFixture()],
            'invoices.list' => ['data' => [self::invoiceFixture()], 'total' => 1, 'page' => 1, 'per_page' => 6],
        ]);

        $this->actingAs($this->user)->get($this->customerUrl(view: 'billing'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/billing')
                ->where('accounts.0.payment_method.label', 'Visa ****4242')
                ->where('accounts.0.invoices.0.invoice_number', 'INV-00000042')
                ->where('accounts.0.invoices.0.payments.0.failure_message', 'Declined: expired card')
                ->missing('numbers')
                ->where('billingError', null));

        Http::assertSent(fn (Request $r) => $r['name'] === 'invoices.list'
            && json_decode($r->body(), true)['payload'] === ['account_id' => 'acc_01A7DM1QT4J21DF5TF9A6EH132', 'page' => 1, 'per_page' => 6]);
        $this->assertNotContains('numbers.for_accounts', $this->sentTasks());
    }

    public function test_overview_and_numbers_still_load_when_the_msisdn_daemon_is_down(): void
    {
        $this->fakeDaemons([
            'customers.get' => self::customerFixture(),
            'accounts.for_customer' => [self::accountFixture()],
            'numbers.for_accounts' => self::down(),
        ]);

        foreach (['show', 'numbers'] as $view) {
            $this->actingAs($this->user)->get($this->customerUrl(view: $view))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('numbers', null)
                    ->where('billingError', null)
                    ->whereType('numbersError', 'string'));
        }
    }

    public function test_a_malformed_daemon_response_degrades_only_its_section_and_is_reported(): void
    {
        Log::spy();
        $this->fakeDaemons([
            'customers.get' => self::customerFixture(),
            'accounts.for_customer' => [self::accountFixture()],
            // Not a map of account id => lines: a bug or a contract change in the daemon.
            'numbers.for_accounts' => fn () => [self::ACCOUNT_ID => 'nonsense'],
            'lines.for_accounts' => fn (array $p) => [$p['account_ids'][0] => [self::subscriptionFixture()]],
        ]);

        foreach (['show', 'numbers'] as $view) {
            $this->actingAs($this->user)->get($this->customerUrl(view: $view))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('customer.full_name', 'Dr Ada Lovelace')
                    ->where('numbers', null)
                    ->where('numbersError', 'Mobile numbers are unavailable right now.')
                    ->where('billingError', null));
        }

        Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context) => $message === 'Numbers unavailable for customer overview'
            && str_starts_with($context['error'], 'UnexpectedValueException'));
    }

    public function test_tariffs_failing_unexpectedly_still_shows_the_numbers(): void
    {
        $this->fakeDaemons([
            'customers.get' => self::customerFixture(),
            'accounts.for_customer' => [self::accountFixture()],
            'numbers.for_accounts' => fn (array $p) => [$p['account_ids'][0] => [self::lineFixture()]],
            'lines.for_accounts' => fn () => 'nonsense',
        ]);

        $this->actingAs($this->user)->get($this->customerUrl(view: 'numbers'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('numbers.0.national', '07700 910123')
                ->where('numbers.0.subscription', null)
                ->where('subscriptionsError', 'Tariffs are unavailable right now.'));
    }

    public function test_a_malformed_customer_shows_an_error_in_place_of_the_view_not_a_500(): void
    {
        $this->fakeDaemons(['customers.get' => ['id' => self::CUSTOMER_ID]]); // missing every field

        foreach (['show', 'details', 'numbers', 'billing'] as $view) {
            $this->actingAs($this->user)->get($this->customerUrl(view: $view))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('customer', null)
                    ->where('error', 'Customer details are unavailable right now. Please try again shortly.'));
        }
    }

    public function test_search_by_mobile_number_resolves_number_then_account_then_customer(): void
    {
        $this->fakeDaemons([
            'numbers.get' => ['msisdn' => '447700910123', 'assignment' => ['target_type' => 'account', 'target_id' => 'acc_01A7DM1QT4J21DF5TF9A6EH132', 'status' => 'barred']],
            'accounts.get' => self::accountFixture(),
            'customers.search' => self::page([self::customerFixture()], 1),
            'accounts.for_customers' => [self::CUSTOMER_ID => []],
        ]);

        $this->actingAs($this->user)->get($this->searchUrl(['q' => '07700 910123']))
            ->assertInertia(fn (Assert $page) => $page->where('results.data.0.id', self::CUSTOMER_ID));

        $this->assertSame(['numbers.get', 'accounts.get', 'customers.search', 'accounts.for_customers'], $this->sentTasks());
        Http::assertSent(fn (Request $r) => $r['name'] === 'numbers.get' && json_decode($r->body(), true)['payload'] === ['msisdn' => '07700 910123']);
        Http::assertSent(fn (Request $r) => $r['name'] === 'accounts.get' && json_decode($r->body(), true)['payload'] === ['id' => 'acc_01A7DM1QT4J21DF5TF9A6EH132']);
        Http::assertSent(fn (Request $r) => $r['name'] === 'customers.search' && json_decode($r->body(), true)['payload']['ids'] === [self::CUSTOMER_ID]);
    }

    public function test_unassigned_numbers_and_conflicting_identifiers_find_nobody(): void
    {
        $this->fakeDaemons([
            'numbers.get' => fn (array $p) => $p['msisdn'] === '07700 910000'
                ? ['msisdn' => '447700910000', 'assignment' => null]
                : ['msisdn' => '447700910123', 'assignment' => ['target_type' => 'account', 'target_id' => 'acc_01A7DM1QT4J21DF5TF9A6EH132']],
            'accounts.get' => fn (array $p) => isset($p['id'])
                ? self::accountFixture()
                : self::accountFixture('cus_01ARZ3NDEKTSV4RRFFQ69G5FAV', ['account_number' => '1111111111']),
        ]);

        $this->actingAs($this->user)->get($this->searchUrl(['msisdn' => '07700 910000']))
            ->assertInertia(fn (Assert $page) => $page->where('results.total', 0));

        // The number belongs to one customer, the account number to another: no match.
        $this->actingAs($this->user)->get($this->searchUrl(['msisdn' => '07700 910123', 'account_number' => '1111111111']))
            ->assertInertia(fn (Assert $page) => $page->where('results.total', 0)->where('error', null));

        $this->assertNotContains('customers.search', $this->sentTasks());
    }

    public function test_invalid_mobile_filter_is_rejected(): void
    {
        Http::fake();

        $this->actingAs($this->user)->from($this->searchUrl())
            ->get($this->searchUrl(['msisdn' => '020 7946 0000']))
            ->assertSessionHasErrors(['msisdn']);

        Http::assertNothingSent();
    }

    public function test_numbers_still_show_without_tariffs_when_the_subscription_daemon_is_down(): void
    {
        $this->fakeDaemons([
            'customers.get' => self::customerFixture(),
            'accounts.for_customer' => [self::accountFixture()],
            'numbers.for_accounts' => fn (array $p) => [$p['account_ids'][0] => [self::lineFixture()]],
            'lines.for_accounts' => self::down(),
        ]);

        $this->actingAs($this->user)->get($this->customerUrl(view: 'numbers'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('numbers.0.national', '07700 910123')
                ->where('numbers.0.subscription', null)
                ->whereType('subscriptionsError', 'string'));
    }

    public function test_every_view_still_loads_when_billing_is_down(): void
    {
        $this->fakeDaemons([
            'customers.get' => self::customerFixture(),
            'accounts.for_customer' => self::down(),
        ]);

        // Each view drops what it can't show without billing: numbers are listed by account.
        foreach (['show' => ['accounts', 'numbers'], 'numbers' => ['numbers'], 'billing' => ['accounts']] as $view => $missing) {
            $this->actingAs($this->user)->get($this->customerUrl(view: $view))
                ->assertOk()
                ->assertInertia(function (Assert $page) use ($missing) {
                    $page->where('customer.full_name', 'Dr Ada Lovelace')->whereType('billingError', 'string');
                    foreach ($missing as $prop) {
                        $page->where($prop, null);
                    }
                });
        }
    }

    public function test_unknown_customers_404_and_malformed_ids_never_reach_a_daemon(): void
    {
        $this->fakeDaemons(['customers.get' => null]);

        foreach (['show', 'details', 'numbers', 'billing'] as $view) {
            $this->actingAs($this->user)->get($this->customerUrl('cus_01ARZ3NDEKTSV4RRFFQ69G5FAV', $view))->assertNotFound();
        }

        Http::fake();
        $this->actingAs($this->user)->get('/'.$this->user->team->slug.'/customers/not-an-id')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_every_view_shows_an_error_when_the_customer_daemon_is_down(): void
    {
        $this->fakeDaemons(['customers.get' => self::down()]);

        foreach (['show', 'details', 'numbers', 'billing'] as $view) {
            $this->actingAs($this->user)->get($this->customerUrl(view: $view))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('customer', null)
                    ->where('customerId', self::CUSTOMER_ID)
                    ->whereType('error', 'string'));
        }

        // Nothing else is asked for when the customer can't be shown.
        $this->assertSame(['customers.get', 'customers.get', 'customers.get', 'customers.get'], $this->sentTasks());
    }
}

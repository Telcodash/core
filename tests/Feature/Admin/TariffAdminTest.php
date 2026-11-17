<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Customers\FakesDaemons;
use Tests\TestCase;

class TariffAdminTest extends TestCase
{
    use FakesDaemons, RefreshDatabase;

    private const TARIFF_ID = 'trf_01A7DM1QT4J21DF5TF9A6EH132';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config([
            'inertia.ssr.enabled' => false,
            'services.queue.url' => 'http://queue.test',
            'services.queue.token' => 'test-token',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function sentPayload(string $task): array
    {
        $request = Http::recorded()->first(fn (array $pair) => $pair[0]['name'] === $task)[0];

        return json_decode($request->body(), true)['payload'];
    }

    public function test_only_administrators_can_manage_tariffs(): void
    {
        $manager = User::factory()->create();

        $this->get(route('tariffs.index'))->assertRedirect(route('login'));
        $this->actingAs($manager)->get(route('tariffs.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('tariffs.store'), [])->assertForbidden();
        $this->actingAs($manager)->patch(route('tariffs.update', self::TARIFF_ID), [])->assertForbidden();
    }

    public function test_lists_the_catalogue_with_lock_state(): void
    {
        $this->fakeDaemons(['tariffs.list' => [
            self::tariffFixture(),
            self::tariffFixture(['id' => 'trf_01A7DM1QT4J21DF5TF9A6EH133', 'name' => 'New plan', 'active_lines' => 0, 'available' => false]),
        ]]);

        $this->actingAs($this->admin())->get(route('tariffs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/tariffs/index')
                ->has('tariffs', 2)
                ->where('tariffs.0.locked', true)
                ->where('tariffs.0.allowances.data_mb', 30720)
                ->where('tariffs.1.locked', false)
                ->where('tariffs.1.available', false)
                ->where('error', null));
    }

    public function test_create_converts_pounds_and_gigabytes_and_handles_unlimited(): void
    {
        $this->fakeDaemons(['tariffs.create' => fn (array $p) => self::tariffFixture([...$p, 'active_lines' => 0])]);

        $this->actingAs($this->admin())
            ->post(route('tariffs.store'), [
                'name' => ' Family 40GB ',
                'description' => '',
                'monthly_price' => '19.99',
                'minutes' => '', 'minutes_unlimited' => '1',
                'texts' => '500', 'texts_unlimited' => '0',
                'data_gb' => '40', 'data_unlimited' => '0',
                'eu_roaming' => '1', 'international_roaming' => '0', 'five_g_plus' => 'on',
                'available' => '1',
            ])
            ->assertRedirect(route('tariffs.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame([
            'name' => 'Family 40GB',
            'description' => null,
            'monthly_price_pence' => 1999,
            'minutes' => null,
            'texts' => 500,
            'data_mb' => 40960,
            'eu_roaming' => true,
            'international_roaming' => false,
            'five_g_plus' => true,
            'available' => true,
        ], $this->sentPayload('tariffs.create'));
    }

    public function test_create_validates_before_calling_the_daemon(): void
    {
        Http::fake();

        $this->actingAs($this->admin())
            ->from(route('tariffs.index'))
            ->post(route('tariffs.store'), ['name' => 'X', 'monthly_price' => '-1', 'minutes' => '', 'data_gb' => '1.234'])
            ->assertSessionHasErrors(['name', 'monthly_price', 'minutes', 'texts', 'data_gb']);

        Http::assertNothingSent();
    }

    public function test_editing_a_subscribed_tariff_sends_only_the_fields_the_form_showed(): void
    {
        $this->fakeDaemons(['tariffs.update' => fn (array $p) => self::tariffFixture($p['changes'])]);

        // A locked tariff's form has no price/allowance inputs.
        $this->actingAs($this->admin())
            ->patch(route('tariffs.update', self::TARIFF_ID), ['name' => 'Everyday 30GB (2025)', 'description' => 'Legacy', 'available' => '0'])
            ->assertRedirect(route('tariffs.index'));

        $this->assertSame(
            ['id' => self::TARIFF_ID, 'changes' => ['name' => 'Everyday 30GB (2025)', 'description' => 'Legacy', 'available' => false]],
            $this->sentPayload('tariffs.update'),
        );
    }

    public function test_daemon_rule_violations_come_back_as_form_errors(): void
    {
        Http::fake(['queue.test/tasks*' => Http::response([
            'state' => 'failed', 'result' => null,
            'error' => 'Everyday 30GB has 120 active line(s), so monthly_price_pence can\'t change. Create a new tariff and retire this one instead.',
        ])]);

        $this->actingAs($this->admin())
            ->from(route('tariffs.index'))
            ->patch(route('tariffs.update', self::TARIFF_ID), ['monthly_price' => '20.00'])
            ->assertRedirect(route('tariffs.index'))
            ->assertSessionHasErrors(['tariff' => 'Everyday 30GB has 120 active line(s), so monthly_price_pence can\'t change. Create a new tariff and retire this one instead.']);
    }

    public function test_catalogue_unavailable(): void
    {
        $this->fakeDaemons(['tariffs.list' => self::down()]);

        $this->actingAs($this->admin())->get(route('tariffs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tariffs', [])->whereType('error', 'string'));
    }
}

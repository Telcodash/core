<?php

namespace Tests\Feature\Customers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class NumberRenameTest extends TestCase
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
            'inertia.ssr.enabled' => false,
            'services.queue.url' => 'http://queue.test',
            'services.queue.token' => 'test-token',
        ]);
        $this->user = User::factory()->advisor()->create();
    }

    private function url(string $msisdn = '447700910123', ?Team $team = null): string
    {
        return route('customers.rename-number', [
            'current_team' => ($team ?? $this->user->team)->slug,
            'customer' => self::CUSTOMER_ID,
            'msisdn' => $msisdn,
        ]);
    }

    public function test_an_advisor_names_a_number_scoped_to_the_customers_accounts_and_it_is_audited_without_the_name(): void
    {
        Log::spy();
        $this->fakeDaemons([
            'accounts.for_customer' => [self::accountFixture()],
            'numbers.rename' => fn (array $p) => ['msisdn' => $p['msisdn'], 'friendly_name' => trim($p['friendly_name'])],
        ]);

        $this->actingAs($this->user)
            ->from($this->url())
            ->patch($this->url(), ['friendly_name' => "  Dad's phone "])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(['accounts.for_customer', 'numbers.rename'], $this->sentTasks());
        Http::assertSent(fn (Request $r) => $r['name'] === 'numbers.rename'
            && json_decode($r->body(), true)['payload'] === ['msisdn' => '447700910123', 'account_ids' => [self::ACCOUNT_ID], 'friendly_name' => "Dad's phone"]);

        Log::shouldHaveReceived('info')->with('Customer number renamed', [
            'customer_id' => self::CUSTOMER_ID,
            'msisdn' => '447700910123',
            'cleared' => false,
            'user_id' => $this->user->id,
            'team_id' => $this->user->team_id,
        ]);
    }

    public function test_a_blank_name_clears_it(): void
    {
        $this->fakeDaemons([
            'accounts.for_customer' => [self::accountFixture()],
            'numbers.rename' => fn (array $p) => ['msisdn' => $p['msisdn'], 'friendly_name' => $p['friendly_name']],
        ]);

        $this->actingAs($this->user)->patch($this->url(), ['friendly_name' => ''])->assertSessionHasNoErrors();

        Http::assertSent(fn (Request $r) => $r['name'] === 'numbers.rename' && json_decode($r->body(), true)['payload']['friendly_name'] === null);
    }

    public function test_the_daemons_refusal_goes_back_to_the_form(): void
    {
        $this->fakeDaemons([
            'accounts.for_customer' => [self::accountFixture()],
            // fakeDaemons fails any task it doesn't know, which is how a refusal looks to Core.
        ]);

        $this->actingAs($this->user)->patch($this->url(), ['friendly_name' => 'Mine'])
            ->assertSessionHasErrors('friendly_name');
    }

    public function test_billing_or_msisdn_being_down_is_a_form_error(): void
    {
        foreach ([
            ['accounts.for_customer' => self::down()],
            ['accounts.for_customer' => [self::accountFixture()], 'numbers.rename' => self::down()],
        ] as $daemons) {
            $this->fakeDaemons($daemons);

            $this->actingAs($this->user)->patch($this->url(), ['friendly_name' => 'Mine'])
                ->assertSessionHasErrors(['friendly_name' => "Numbers can't be renamed right now. Please try again shortly."]);
        }
    }

    public function test_a_customer_with_no_accounts_404s_without_reaching_the_msisdn_daemon(): void
    {
        $this->fakeDaemons(['accounts.for_customer' => []]);

        $this->actingAs($this->user)->patch($this->url(), ['friendly_name' => 'Mine'])->assertNotFound();

        $this->assertNotContains('numbers.rename', $this->sentTasks());
    }

    public function test_invalid_input_and_outsiders_never_reach_a_daemon(): void
    {
        Http::fake();

        $this->patch($this->url(), ['friendly_name' => 'Mine'])->assertRedirect(route('login'));

        $this->actingAs($this->user)->patch($this->url(), ['friendly_name' => str_repeat('x', 41)])->assertSessionHasErrors('friendly_name');
        $this->actingAs($this->user)->patch($this->url(), [])->assertSessionHasErrors('friendly_name');
        $this->actingAs($this->user)->patch($this->url('442079460000'), ['friendly_name' => 'Mine'])->assertNotFound();

        $other = Team::factory()->create();
        $this->actingAs($this->user)->patch($this->url(team: $other), ['friendly_name' => 'Mine'])->assertForbidden();

        Http::assertNothingSent();
    }
}

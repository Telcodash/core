<?php

namespace Tests\Feature\Admin;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_lists_teams_without_a_manager_and_advisors_without_a_team(): void
    {
        $admin = User::factory()->administrator()->create();

        $managed = Team::factory()->create(['name' => 'Managed Squad']);
        User::factory()->manager()->forTeam($managed)->create();

        Team::factory()->create(['name' => 'Orphan Squad']);

        $loneAdvisor = User::factory()->advisor()->create(['name' => 'Lone Advisor']);
        $loneAdvisor->teams()->detach();

        $this->actingAs($admin);

        Livewire::test('pages::admin.overview')
            ->assertSee('Orphan Squad')
            ->assertDontSee('Managed Squad')
            ->assertSee('Lone Advisor')
            ->assertDontSeeHtml('data-test="admin-overview-empty"');
    }

    public function test_overview_shows_an_all_clear_state_when_nothing_needs_attention(): void
    {
        $team = Team::factory()->create();
        $admin = User::factory()->administrator()->forTeam($team)->create();
        User::factory()->manager()->forTeam($team)->create();

        $this->actingAs($admin);

        Livewire::test('pages::admin.overview')
            ->assertSeeHtml('data-test="admin-overview-empty"');
    }
}

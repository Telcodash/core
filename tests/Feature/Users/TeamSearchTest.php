<?php

namespace Tests\Feature\Users;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_search_teams()
    {
        $manager = User::factory()->create();
        $advisor = User::factory()->advisor()->create();

        $this->get(route('teams.index'))->assertRedirect(route('login'));
        $this->actingAs($manager)->get(route('teams.index'))->assertForbidden();
        $this->actingAs($advisor)->get(route('teams.index'))->assertForbidden();
    }

    public function test_administrators_see_all_teams_without_a_query()
    {
        $admin = User::factory()->administrator()->create();
        User::factory()->count(2)->create();

        $this->actingAs($admin)->get(route('teams.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('teams/index')
                ->where('teams.total', 3));
    }

    public function test_search_by_team_name_member_email_and_member_name()
    {
        $admin = User::factory()->administrator()->create();
        $alpha = Team::factory()->create(['name' => 'Alpha Advisers']);
        $alphaManager = User::factory()->forTeam($alpha)->create(['name' => 'Zed Zebra', 'email' => 'zed@example.com']);
        $beta = Team::factory()->create(['name' => 'Beta Group']);
        User::factory()->forTeam($beta)->create(['name' => 'Quentin Quokka', 'email' => 'quentin@somewhere.test']);

        $search = fn (string $q) => $this->actingAs($admin)->get(route('teams.index', ['q' => $q]));

        $search('alpha adv')->assertInertia(fn (Assert $page) => $page
            ->where('teams.total', 1)->where('teams.data.0.id', $alpha->id));

        $search('QUENTIN@SOMEWHERE')->assertInertia(fn (Assert $page) => $page
            ->where('teams.total', 1)->where('teams.data.0.id', $beta->id)
            ->where('teams.data.0.members.0.matched', true));

        $search('zebra')->assertInertia(fn (Assert $page) => $page
            ->where('teams.total', 1)->where('teams.data.0.id', $alpha->id)
            ->where('teams.data.0.members.0.matched', true));

        $search('nothing-matches-this')->assertInertia(fn (Assert $page) => $page
            ->where('teams.total', 0));

        $this->assertModelExists($alphaManager);
    }

    public function test_like_wildcards_in_the_query_are_treated_literally()
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get(route('teams.index', ['q' => '%']))
            ->assertInertia(fn (Assert $page) => $page->where('teams.total', 0));
    }
}

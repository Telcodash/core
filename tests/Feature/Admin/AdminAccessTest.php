<?php

namespace Tests\Feature\Admin;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function adminUrls(Team $team, User $user): array
    {
        return [
            route('admin.overview'),
            route('admin.teams.index'),
            route('admin.teams.create'),
            route('admin.teams.edit', $team),
            route('admin.users.index'),
            route('admin.users.create'),
            route('admin.users.edit', $user),
        ];
    }

    public function test_administrators_can_open_every_admin_page(): void
    {
        $admin = User::factory()->administrator()->create();
        $manager = User::factory()->manager()->create();
        User::factory()->advisor()->forTeam($manager->currentTeam)->create();

        $this->actingAs($admin);

        foreach ($this->adminUrls($manager->currentTeam, $manager) as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_other_roles_cannot_access_the_admin_area(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->manager()->forTeam($team)->create();
        $advisorPlus = User::factory()->advisorPlus()->forTeam($team)->create();

        foreach ([$manager, $advisorPlus] as $user) {
            $this->actingAs($user);

            foreach ($this->adminUrls($team, $manager) as $url) {
                $this->get($url)->assertForbidden();
            }
        }
    }

    public function test_admin_components_reject_non_administrators_even_without_routes(): void
    {
        $this->actingAs(User::factory()->manager()->create());

        Livewire::test('pages::admin.users.index')->assertForbidden();
        Livewire::test('pages::admin.teams.create')->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.overview'))->assertRedirect(route('login'));
    }

    public function test_admin_link_is_only_shown_to_administrators(): void
    {
        $admin = User::factory()->administrator()->create();
        $manager = User::factory()->manager()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertSee('data-test="nav-admin"', false);
        $this->actingAs($manager)->get(route('dashboard'))->assertDontSee('data-test="nav-admin"', false);
    }

    public function test_admin_pages_work_for_administrators_when_no_teams_exist(): void
    {
        $admin = User::factory()->administrator()->create();

        Team::query()->each(fn (Team $team) => $team->forceDelete());

        $this->actingAs($admin->fresh());

        $this->get(route('admin.overview'))->assertOk();
        $this->get(route('app.home'))->assertRedirect(route('teams.index', absolute: false));
        $this->get(route('teams.index'))->assertOk();
    }

    public function test_app_home_redirects_to_the_current_team_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.home'))
            ->assertRedirect("/{$user->currentTeam->slug}/dashboard");
    }
}

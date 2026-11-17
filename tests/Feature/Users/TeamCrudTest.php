<?php

namespace Tests\Feature\Users;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_use_team_crud_routes()
    {
        $manager = User::factory()->create();
        $team = User::factory()->create()->team;

        $this->actingAs($manager)->get(route('teams.show', $team))->assertForbidden();
        $this->actingAs($manager)->post(route('teams.store'), [])->assertForbidden();
        $this->actingAs($manager)->patch(route('teams.update', $team), ['name' => 'X'])->assertForbidden();
        $this->actingAs($manager)->delete(route('teams.destroy', $team))->assertForbidden();
        $this->actingAs($manager)->post(route('teams.members.store', $team), [])->assertForbidden();
        $this->assertModelExists($team);
    }

    public function test_administrators_can_create_a_team_with_its_first_manager()
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('teams.store'), [
            'name' => 'Brand New Team',
            'manager_name' => 'Nina Manager',
            'manager_email' => 'Nina@Example.com',
            'manager_password' => 'password',
            'manager_role' => 'manager',
        ])->assertSessionHasNoErrors()->assertRedirect(route('teams.show', 'brand-new-team'));

        $team = Team::where('name', 'Brand New Team')->firstOrFail();
        $nina = User::where('email', 'nina@example.com')->firstOrFail();

        $this->assertSame($team->id, $nina->team_id);
        $this->assertSame(Role::Manager, $nina->role);
    }

    public function test_a_team_cannot_be_created_without_a_manager_or_with_a_non_manager_role_or_reserved_name()
    {
        $admin = User::factory()->administrator()->create();
        $base = [
            'name' => 'Okay Team', 'manager_name' => 'N', 'manager_email' => 'n@example.com',
            'manager_password' => 'password', 'manager_role' => 'manager',
        ];

        $this->actingAs($admin)->post(route('teams.store'), ['name' => 'Okay Team'])
            ->assertSessionHasErrors(['manager_name', 'manager_email', 'manager_password', 'manager_role']);

        $this->actingAs($admin)->post(route('teams.store'), [...$base, 'manager_role' => 'advisor'])
            ->assertSessionHasErrors('manager_role');

        foreach (['Admin', 'Settings', 'Login', 'Build'] as $reserved) {
            $this->actingAs($admin)->post(route('teams.store'), [...$base, 'name' => $reserved])
                ->assertSessionHasErrors('name');
        }

        $this->assertSame(1, Team::count());
    }

    public function test_administrators_can_view_and_rename_any_team()
    {
        $admin = User::factory()->administrator()->create();
        $other = User::factory()->create();

        $this->actingAs($admin)->get(route('teams.show', $other->team))
            ->assertInertia(fn (Assert $page) => $page
                ->component('teams/show')
                ->where('canManage', true)
                ->where('team.id', $other->team_id)
                ->has('roles', 4));

        $this->actingAs($admin)->patch(route('teams.update', $other->team), ['name' => 'Renamed Team'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('teams.show', 'renamed-team'));

        $this->assertSame('Renamed Team', $other->team->fresh()->name);

        $this->actingAs($admin)->patch(route('teams.update', 'renamed-team'), ['name' => $admin->team->name])
            ->assertSessionHasErrors('name');
    }

    public function test_administrators_can_manage_members_of_any_team()
    {
        $admin = User::factory()->administrator()->create();
        $other = User::factory()->create();
        $slug = $other->team->slug;

        $this->actingAs($admin)->post(route('teams.members.store', $slug), [
            'name' => 'Added', 'email' => 'added@example.com', 'role' => 'advisor', 'password' => 'password',
        ])->assertSessionHasNoErrors();

        $added = User::where('email', 'added@example.com')->firstOrFail();
        $this->assertSame($other->team_id, $added->team_id);

        $this->actingAs($admin)->patch(route('teams.members.update', [$slug, $added]), [
            'name' => 'Edited', 'email' => 'added@example.com', 'role' => 'advisor_plus',
        ])->assertSessionHasNoErrors();
        $this->assertSame(Role::AdvisorPlus, $added->fresh()->role);

        // The other team's only manager is protected.
        $this->actingAs($admin)->delete(route('teams.members.destroy', [$slug, $other]))
            ->assertSessionHasErrors('member');

        $this->actingAs($admin)->delete(route('teams.members.destroy', [$slug, $added]));
        $this->assertModelMissing($added);

        // A member of a different team cannot be reached through this team's URL.
        $stranger = User::factory()->create();
        $this->actingAs($admin)->delete(route('teams.members.destroy', [$slug, $stranger]))->assertNotFound();
    }

    public function test_administrators_can_delete_another_team_and_its_accounts_but_not_their_own()
    {
        $admin = User::factory()->administrator()->create();
        $other = User::factory()->create();
        $advisor = User::factory()->advisor()->forTeam($other->team)->create();

        $this->actingAs($admin)->delete(route('teams.destroy', $admin->team))->assertForbidden();
        $this->assertNull($admin->team->fresh()->deleted_at);

        $this->actingAs($admin)->delete(route('teams.destroy', $other->team))
            ->assertRedirect(route('teams.index'));

        $this->assertSoftDeleted($other->team);
        $this->assertModelMissing($other);
        $this->assertModelMissing($advisor);
        $this->assertModelExists($admin);
    }

    public function test_cli_rejects_reserved_team_names()
    {
        $this->artisan('user:create', [
            '--name' => 'X', '--email' => 'x@example.com', '--password' => 'password',
            '--role' => 'manager', '--new-team' => 'Admin', '--no-interaction' => true,
        ])->assertFailed();
    }
}

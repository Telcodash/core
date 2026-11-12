<?php

namespace Tests\Feature\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_teams_index_page_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('teams.index'));

        $response->assertOk();
    }

    public function test_managers_can_create_teams(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager);

        Livewire::test('pages::teams.index')
            ->set('name', 'Test Team')
            ->call('createTeam')
            ->assertHasNoErrors();

        $team = Team::where('name', 'Test Team')->firstOrFail();

        $this->assertTrue($manager->fresh()->belongsToTeam($team));
        $this->assertTrue($manager->fresh()->isCurrentTeam($team));
    }

    public function test_administrators_can_create_teams(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test('pages::teams.index')
            ->set('name', 'Test Team')
            ->call('createTeam')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teams', ['name' => 'Test Team']);
    }

    public function test_advisors_cannot_create_teams(): void
    {
        foreach (['advisor', 'advisorPlus'] as $state) {
            $this->actingAs(User::factory()->{$state}()->create());

            Livewire::test('pages::teams.index')
                ->assertDontSeeHtml('data-test="teams-new-team-button"')
                ->set('name', 'Test Team')
                ->call('createTeam')
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('teams', ['name' => 'Test Team']);
    }

    public function test_teams_cannot_be_created_when_the_policy_denies_it(): void
    {
        $user = User::factory()->manager()->create();

        Gate::before(fn ($user, string $ability) => $ability === 'create' ? false : null);

        $this->actingAs($user);

        Livewire::test('pages::teams.index')
            ->set('name', 'Test Team')
            ->call('createTeam')
            ->assertForbidden();

        $this->assertDatabaseMissing('teams', [
            'name' => 'Test Team',
        ]);
    }

    public function test_team_slug_uses_next_available_suffix(): void
    {
        $user = User::factory()->manager()->create();

        Team::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
        Team::factory()->create(['name' => 'Acme One', 'slug' => 'acme-1']);
        Team::factory()->create(['name' => 'Acme Ten', 'slug' => 'acme-10']);

        $this->actingAs($user);

        Livewire::test('pages::teams.index')
            ->set('name', 'Acme')
            ->call('createTeam')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teams', [
            'name' => 'Acme',
            'slug' => 'acme-11',
        ]);
    }

    public function test_team_edit_page_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('teams.edit', $user->currentTeam));

        $response->assertOk();
    }

    public function test_only_administrators_see_all_teams(): void
    {
        $team = Team::factory()->create(['name' => 'Alpha']);
        $otherTeam = Team::factory()->create(['name' => 'Bravo']);

        $manager = User::factory()->manager()->forTeam($team)->create();
        $administrator = User::factory()->administrator()->forTeam($team)->create();

        $this->actingAs($manager);
        Livewire::test('pages::teams.index')->assertSee('Alpha')->assertDontSee('Bravo');
        $this->get(route('teams.edit', $otherTeam))->assertForbidden();
        $this->get(route('dashboard', ['current_team' => $otherTeam->slug]))->assertForbidden();

        $this->actingAs($administrator);
        Livewire::test('pages::teams.index')->assertSee('Alpha')->assertSee('Bravo');
        $this->get(route('teams.edit', $otherTeam))->assertOk();
        $this->get(route('dashboard', ['current_team' => $otherTeam->slug]))->assertOk();
    }

    public function test_teams_can_be_updated_by_their_managers(): void
    {
        $team = Team::factory()->create(['name' => 'Original Name']);
        $manager = User::factory()->manager()->forTeam($team)->create();

        $this->actingAs($manager);

        Livewire::test('pages::teams.edit', ['team' => $team])
            ->set('teamName', 'Updated Name')
            ->call('updateTeam')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_teams_can_be_updated_by_administrators_who_are_not_members(): void
    {
        $team = Team::factory()->create(['name' => 'Original Name']);

        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test('pages::teams.edit', ['team' => $team])
            ->set('teamName', 'Updated Name')
            ->call('updateTeam')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Updated Name']);
    }

    public function test_teams_cannot_be_updated_by_advisors(): void
    {
        $team = Team::factory()->create();
        User::factory()->manager()->forTeam($team)->create();
        $advisor = User::factory()->advisorPlus()->forTeam($team)->create();

        $this->actingAs($advisor);

        Livewire::test('pages::teams.edit', ['team' => $team])
            ->set('teamName', 'Updated Name')
            ->call('updateTeam')
            ->assertForbidden();
    }

    public function test_teams_cannot_be_updated_by_managers_of_other_teams(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager);

        Livewire::test('pages::teams.edit', ['team' => $team])
            ->assertForbidden();
    }

    public function test_teams_can_be_deleted_by_their_managers(): void
    {
        $manager = User::factory()->manager()->create();
        $team = $manager->currentTeam;

        $this->actingAs($manager);

        Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
            ->set('deleteName', $team->name)
            ->call('deleteTeam')
            ->assertHasNoErrors();

        $this->assertSoftDeleted('teams', ['id' => $team->id]);
        $this->assertNull($manager->fresh()->current_team_id);
    }

    public function test_team_deletion_requires_name_confirmation(): void
    {
        $manager = User::factory()->manager()->create();
        $team = $manager->currentTeam;

        $this->actingAs($manager);

        Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
            ->set('deleteName', 'Wrong Name')
            ->call('deleteTeam')
            ->assertHasErrors(['deleteName']);

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'deleted_at' => null,
        ]);
    }

    public function test_teams_cannot_be_deleted_while_advisors_depend_on_them(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->manager()->forTeam($team)->create();
        User::factory()->advisor()->forTeam($team)->create();

        $this->actingAs($manager);

        Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
            ->set('deleteName', $team->name)
            ->call('deleteTeam')
            ->assertForbidden();

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'deleted_at' => null]);
    }

    public function test_teams_can_be_deleted_when_advisors_have_another_managed_team(): void
    {
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        $manager = User::factory()->manager()->forTeam($team)->create();
        User::factory()->manager()->forTeam($otherTeam)->create();
        $advisor = User::factory()->advisor()->forTeam($team)->create();
        $otherTeam->members()->attach($advisor);

        $this->actingAs($manager);

        Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
            ->set('deleteName', $team->name)
            ->call('deleteTeam')
            ->assertHasNoErrors();

        $this->assertSoftDeleted('teams', ['id' => $team->id]);
        $this->assertEquals($otherTeam->id, $advisor->fresh()->current_team_id);
    }

    public function test_deleting_current_team_switches_to_alphabetically_first_remaining_team(): void
    {
        $zuluTeam = Team::factory()->create(['name' => 'Zulu Team']);
        $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
        $betaTeam = Team::factory()->create(['name' => 'Beta Team']);

        $user = User::factory()->manager()->forTeam($zuluTeam)->create();
        $alphaTeam->members()->attach($user);
        $betaTeam->members()->attach($user);

        $this->actingAs($user);

        Livewire::test('pages::teams.delete-team-modal', ['team' => $zuluTeam])
            ->set('deleteName', $zuluTeam->name)
            ->call('deleteTeam')
            ->assertHasNoErrors();

        $this->assertEquals($alphaTeam->id, $user->fresh()->current_team_id);
    }

    public function test_deleting_non_current_team_leaves_current_team_unchanged(): void
    {
        $user = User::factory()->manager()->create();
        $currentTeam = $user->currentTeam;
        $team = Team::factory()->create();
        $team->members()->attach($user);

        $this->actingAs($user);

        Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
            ->set('deleteName', $team->name)
            ->call('deleteTeam')
            ->assertHasNoErrors();

        $this->assertEquals($currentTeam->id, $user->fresh()->current_team_id);
    }

    public function test_teams_cannot_be_deleted_by_advisors(): void
    {
        $advisor = User::factory()->advisor()->create();
        $team = $advisor->currentTeam;

        $this->actingAs($advisor);

        Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
            ->set('deleteName', $team->name)
            ->call('deleteTeam')
            ->assertForbidden();
    }

    public function test_advisors_cannot_leave_their_only_managed_team(): void
    {
        $team = Team::factory()->create();
        User::factory()->manager()->forTeam($team)->create();
        $advisor = User::factory()->advisor()->forTeam($team)->create();

        $this->actingAs($advisor);

        Livewire::test('pages::teams.index')
            ->assertDontSeeHtml('data-test="team-leave-button"')
            ->call('leaveTeam', $team->id)
            ->assertForbidden();

        $this->assertTrue($advisor->fresh()->belongsToTeam($team));
    }

    public function test_advisors_can_leave_a_team_when_they_have_another_managed_team(): void
    {
        $zuluTeam = Team::factory()->create(['name' => 'Zulu Team']);
        $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
        User::factory()->manager()->forTeam($zuluTeam)->create();
        User::factory()->manager()->forTeam($alphaTeam)->create();

        $advisor = User::factory()->advisor()->forTeam($zuluTeam)->create();
        $alphaTeam->members()->attach($advisor);

        $this->actingAs($advisor);

        Livewire::test('pages::teams.index')
            ->assertSeeHtml('data-test="team-leave-button"')
            ->call('leaveTeam', $zuluTeam->id)
            ->assertHasNoErrors();

        $this->assertFalse($advisor->fresh()->belongsToTeam($zuluTeam));
        $this->assertEquals($alphaTeam->id, $advisor->fresh()->current_team_id);
    }

    public function test_the_last_manager_cannot_leave_a_team_with_advisors(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->manager()->forTeam($team)->create();
        User::factory()->advisor()->forTeam($team)->create();

        $this->actingAs($manager);

        Livewire::test('pages::teams.index')
            ->call('leaveTeam', $team->id)
            ->assertForbidden();

        $secondManager = User::factory()->manager()->forTeam($team)->create();

        Livewire::test('pages::teams.index')
            ->call('leaveTeam', $team->id)
            ->assertHasNoErrors();

        $this->assertFalse($manager->fresh()->belongsToTeam($team));
        $this->assertTrue($secondManager->fresh()->belongsToTeam($team));
    }

    public function test_users_cannot_leave_teams_they_dont_belong_to(): void
    {
        $user = User::factory()->manager()->create();
        $team = Team::factory()->create();

        $this->actingAs($user);

        Livewire::test('pages::teams.index')
            ->call('leaveTeam', $team->id)
            ->assertForbidden();
    }

    public function test_guests_cannot_access_teams(): void
    {
        $response = $this->get(route('teams.index'));

        $response->assertRedirect(route('login'));
    }
}

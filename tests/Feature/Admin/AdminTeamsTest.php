<?php

namespace Tests\Feature\Admin;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTeamsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->administrator()->create());
    }

    public function test_teams_are_listed_searchable_and_sortable(): void
    {
        Team::factory()->create(['name' => 'Alpha Squad']);
        Team::factory()->create(['name' => 'Bravo Squad']);

        Livewire::test('pages::admin.teams.index')
            ->assertSee('Alpha Squad')
            ->assertSee('Bravo Squad')
            ->set('search', 'Bravo')
            ->assertDontSee('Alpha Squad')
            ->assertSee('Bravo Squad')
            ->set('search', '')
            ->call('sort', 'name')
            ->assertSet('sortDirection', 'desc')
            ->assertSeeInOrder(['Bravo Squad', 'Alpha Squad'])
            ->call('sort', 'not_a_column')
            ->assertSet('sortBy', 'name');
    }

    public function test_teams_without_a_manager_are_flagged(): void
    {
        $team = Team::factory()->create(['name' => 'Orphans']);
        $advisor = User::factory()->advisor()->forTeam($team)->create();

        Livewire::test('pages::admin.teams.index')->assertSee('No manager');
        Livewire::test('pages::admin.teams.edit', ['team' => $team])->assertSeeHtml('data-test="admin-team-needs-manager"');
        Livewire::test('pages::admin.users.edit', ['user' => $advisor])->assertSeeHtml('data-test="admin-user-unmanaged"');
    }

    public function test_teams_can_be_created_without_the_administrator_joining(): void
    {
        Livewire::test('pages::admin.teams.create')
            ->set('name', 'North Region')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.teams.edit', 'north-region'));

        $team = Team::where('name', 'North Region')->firstOrFail();

        $this->assertCount(0, $team->members);
    }

    public function test_team_names_are_validated(): void
    {
        Livewire::test('pages::admin.teams.create')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required'])
            ->set('name', 'settings')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_teams_can_be_renamed(): void
    {
        $team = Team::factory()->create(['name' => 'Old Name']);

        Livewire::test('pages::admin.teams.edit', ['team' => $team])
            ->set('name', 'New Name')
            ->call('rename')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.teams.edit', 'new-name'));

        $this->assertSame('New Name', $team->fresh()->name);
    }

    public function test_members_can_be_added_and_removed(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->manager()->create();
        $advisor = User::factory()->advisor()->forTeam($manager->currentTeam)->create();

        Livewire::test('pages::admin.teams.edit', ['team' => $team])
            ->set('newMemberId', (string) $manager->id)
            ->call('addMember')
            ->assertHasNoErrors()
            ->set('newMemberId', (string) $advisor->id)
            ->call('addMember')
            ->assertHasNoErrors()
            ->assertSee($manager->name)
            ->assertSee($advisor->name);

        $this->assertTrue($team->members()->whereKey([$manager->id, $advisor->id])->count() === 2);

        Livewire::test('pages::admin.teams.edit', ['team' => $team])
            ->call('removeMember', $advisor->id);

        $this->assertFalse($advisor->fresh()->belongsToTeam($team));
    }

    public function test_adding_an_existing_member_is_rejected(): void
    {
        $member = User::factory()->manager()->create();

        Livewire::test('pages::admin.teams.edit', ['team' => $member->currentTeam])
            ->set('newMemberId', (string) $member->id)
            ->call('addMember')
            ->assertHasErrors(['member']);
    }

    public function test_removing_a_member_cannot_strand_an_advisor(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->manager()->forTeam($team)->create();
        $advisor = User::factory()->advisor()->forTeam($team)->create();

        // Neither the advisor nor the team's only manager can be removed.
        Livewire::test('pages::admin.teams.edit', ['team' => $team])
            ->call('removeMember', $advisor->id)
            ->call('removeMember', $manager->id);

        $this->assertTrue($advisor->fresh()->belongsToTeam($team));
        $this->assertTrue($manager->fresh()->belongsToTeam($team));
    }

    public function test_teams_can_be_deleted_from_the_index_and_edit_pages(): void
    {
        $first = Team::factory()->create();
        $second = Team::factory()->create();
        $member = User::factory()->manager()->forTeam($first)->create();

        Livewire::test('pages::admin.teams.index')
            ->call('confirmDelete', $first->id)
            ->call('deleteTeam');

        $this->assertSoftDeleted($first);
        $this->assertNull($member->fresh()->current_team_id);

        Livewire::test('pages::admin.teams.edit', ['team' => $second])
            ->call('deleteTeam')
            ->assertRedirect(route('admin.teams.index'));

        $this->assertSoftDeleted($second);
    }

    public function test_teams_with_dependent_advisors_cannot_be_deleted(): void
    {
        $team = Team::factory()->create();
        User::factory()->manager()->forTeam($team)->create();
        User::factory()->advisor()->forTeam($team)->create();

        Livewire::test('pages::admin.teams.edit', ['team' => $team])
            ->call('deleteTeam')
            ->assertNoRedirect();

        $this->assertNotSoftDeleted($team);
    }
}

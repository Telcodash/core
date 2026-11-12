<?php

namespace Tests\Feature\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamMemberTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a team with a manager and an advisor who also belongs to a second managed team,
     * so the advisor can be removed from the first team without being stranded.
     *
     * @return array{Team, User, User, Team}
     */
    private function teamWithRemovableAdvisor(string $advisorState = 'advisor'): array
    {
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();

        $manager = User::factory()->manager()->forTeam($team)->create();
        User::factory()->manager()->forTeam($otherTeam)->create();

        $advisor = User::factory()->{$advisorState}()->forTeam($team)->create();
        $otherTeam->members()->attach($advisor);

        return [$team, $manager, $advisor, $otherTeam];
    }

    public function test_members_are_listed_with_their_role(): void
    {
        [$team, $manager] = $this->teamWithRemovableAdvisor('advisorPlus');

        $this->actingAs($manager);

        Livewire::test('pages::teams.edit', ['team' => $team])
            ->assertSee('Manager')
            ->assertSee('Advisor Plus');
    }

    public function test_managers_can_remove_advisors_from_their_team(): void
    {
        foreach (['advisor', 'advisorPlus'] as $state) {
            [$team, $manager, $advisor, $otherTeam] = $this->teamWithRemovableAdvisor($state);

            $this->actingAs($manager);

            Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
                ->set('memberId', $advisor->id)
                ->call('removeMember')
                ->assertHasNoErrors();

            $this->assertFalse($advisor->fresh()->belongsToTeam($team));
            $this->assertEquals($otherTeam->id, $advisor->fresh()->current_team_id);
        }
    }

    public function test_managers_cannot_remove_other_managers(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->manager()->forTeam($team)->create();
        $otherManager = User::factory()->manager()->forTeam($team)->create();

        $this->actingAs($manager);

        Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
            ->set('memberId', $otherManager->id)
            ->call('removeMember')
            ->assertForbidden();
    }

    public function test_managers_cannot_remove_members_of_teams_they_do_not_manage(): void
    {
        [$team, , $advisor] = $this->teamWithRemovableAdvisor();
        $outsideManager = User::factory()->manager()->create();

        $this->actingAs($outsideManager);

        Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
            ->set('memberId', $advisor->id)
            ->call('removeMember')
            ->assertForbidden();
    }

    public function test_advisors_cannot_remove_members(): void
    {
        [$team, , $advisor] = $this->teamWithRemovableAdvisor();
        $otherAdvisor = User::factory()->advisorPlus()->forTeam($team)->create();

        $this->actingAs($otherAdvisor);

        Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
            ->set('memberId', $advisor->id)
            ->call('removeMember')
            ->assertForbidden();
    }

    public function test_advisors_cannot_be_removed_from_their_only_managed_team(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->manager()->forTeam($team)->create();
        $advisor = User::factory()->advisor()->forTeam($team)->create();

        $this->actingAs($manager);

        Livewire::test('pages::teams.edit', ['team' => $team])
            ->assertDontSeeHtml('data-test="member-remove-button"');

        Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
            ->set('memberId', $advisor->id)
            ->call('removeMember')
            ->assertForbidden();

        $this->assertTrue($advisor->fresh()->belongsToTeam($team));
    }

    public function test_administrators_can_remove_managers_when_another_manager_remains(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->manager()->forTeam($team)->create();
        User::factory()->manager()->forTeam($team)->create();
        User::factory()->advisor()->forTeam($team)->create();

        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
            ->set('memberId', $manager->id)
            ->call('removeMember')
            ->assertHasNoErrors();

        $this->assertFalse($manager->fresh()->belongsToTeam($team));
    }

    public function test_administrators_cannot_remove_the_last_manager_of_a_team_with_advisors(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->manager()->forTeam($team)->create();
        User::factory()->advisor()->forTeam($team)->create();

        $this->actingAs(User::factory()->administrator()->create());

        Livewire::test('pages::teams.remove-member-modal', ['team' => $team])
            ->set('memberId', $manager->id)
            ->call('removeMember')
            ->assertForbidden();
    }
}

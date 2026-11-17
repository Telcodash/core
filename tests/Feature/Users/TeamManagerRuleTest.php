<?php

namespace Tests\Feature\Users;

use App\Enums\Role;
use App\Exceptions\TeamRequiresManagerException;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamManagerRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_must_belong_to_a_team()
    {
        $this->expectException(TeamRequiresManagerException::class);

        User::factory()->create(['team_id' => null]);
    }

    public function test_a_non_manager_cannot_join_a_team_without_a_manager()
    {
        $team = Team::factory()->create();

        $this->expectException(TeamRequiresManagerException::class);

        User::factory()->advisor()->forTeam($team)->create();
    }

    public function test_any_role_can_join_a_team_that_has_a_manager()
    {
        $manager = User::factory()->create();

        foreach (Role::cases() as $role) {
            $user = User::factory()->role($role)->forTeam($manager->team)->create();

            $this->assertSame($manager->team_id, $user->team_id);
            $this->assertSame($role, $user->role);
        }
    }

    public function test_the_last_manager_cannot_be_demoted()
    {
        $manager = User::factory()->create();
        User::factory()->advisor()->forTeam($manager->team)->create();

        $this->expectException(TeamRequiresManagerException::class);

        $manager->update(['role' => Role::AdvisorPlus]);
    }

    public function test_the_last_manager_cannot_be_deleted_or_moved()
    {
        $manager = User::factory()->create();
        $otherTeam = User::factory()->create()->team;

        try {
            $manager->update(['team_id' => $otherTeam->id]);
            $this->fail('Moving the last manager should be rejected.');
        } catch (TeamRequiresManagerException) {
            //
        }

        $this->expectException(TeamRequiresManagerException::class);

        $manager->fresh()->delete();
    }

    public function test_a_manager_can_be_demoted_when_another_manager_remains()
    {
        $manager = User::factory()->create();
        User::factory()->administrator()->forTeam($manager->team)->create();

        $manager->update(['role' => Role::Advisor]);

        $this->assertSame(Role::Advisor, $manager->fresh()->role);
    }

    public function test_changing_a_managers_role_between_manager_roles_is_allowed()
    {
        $manager = User::factory()->create();

        $manager->update(['role' => Role::Administrator]);

        $this->assertSame(Role::Administrator, $manager->fresh()->role);
    }

    public function test_the_last_manager_cannot_delete_their_own_account()
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertModelExists($manager);
    }
}

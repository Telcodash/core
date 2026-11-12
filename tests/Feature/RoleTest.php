<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTeamMembership;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_are_seeded_in_privilege_order(): void
    {
        $this->assertSame(Role::NAMES, Role::ordered()->pluck('name')->all());
    }

    public function test_unknown_roles_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Role::named('owner');
    }

    public function test_role_hierarchy_helpers(): void
    {
        $advisor = Role::named(Role::ADVISOR);
        $manager = Role::named(Role::MANAGER);

        $this->assertTrue($manager->isAtLeast(Role::ADVISOR_PLUS));
        $this->assertTrue($manager->isAtLeast($manager));
        $this->assertFalse($manager->isAtLeast(Role::ADMINISTRATOR));
        $this->assertTrue($advisor->isBelow(Role::ADVISOR_PLUS));
        $this->assertTrue($advisor->isAny(Role::ADVISOR, Role::MANAGER));
        $this->assertFalse($advisor->isAny(Role::MANAGER));
    }

    public function test_role_capabilities(): void
    {
        $expectations = [
            // role => [requiresTeamManager, canCreateTeams, canViewAllTeams, canManageTeams]
            Role::ADVISOR => [true, false, false, false],
            Role::ADVISOR_PLUS => [true, false, false, false],
            Role::MANAGER => [false, true, false, true],
            Role::ADMINISTRATOR => [false, true, true, true],
        ];

        foreach ($expectations as $name => $expected) {
            $role = Role::named($name);

            $this->assertSame($expected, [
                $role->requiresTeamManager(),
                $role->canCreateTeams(),
                $role->canViewAllTeams(),
                $role->canManageTeams(),
            ], "Unexpected capabilities for [{$name}].");
        }
    }

    public function test_which_roles_each_role_can_manage(): void
    {
        $this->assertTrue(Role::named(Role::MANAGER)->canManageRole(Role::ADVISOR));
        $this->assertTrue(Role::named(Role::MANAGER)->canManageRole(Role::ADVISOR_PLUS));
        $this->assertFalse(Role::named(Role::MANAGER)->canManageRole(Role::MANAGER));
        $this->assertFalse(Role::named(Role::MANAGER)->canManageRole(Role::ADMINISTRATOR));
        $this->assertTrue(Role::named(Role::ADMINISTRATOR)->canManageRole(Role::MANAGER));
        $this->assertFalse(Role::named(Role::ADVISOR_PLUS)->canManageRole(Role::ADVISOR));
    }

    public function test_user_role_helpers(): void
    {
        $user = User::factory()->advisorPlus()->create();

        $this->assertTrue($user->isAdvisorPlus());
        $this->assertFalse($user->isAdvisor());
        $this->assertTrue($user->hasRole(Role::ADVISOR, Role::ADVISOR_PLUS));
        $this->assertTrue($user->hasRoleAtLeast(Role::ADVISOR));
        $this->assertFalse($user->hasRoleAtLeast(Role::MANAGER));

        $user->assignRole(Role::MANAGER)->save();

        $this->assertTrue($user->fresh()->isManager());
    }

    public function test_role_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->advisor()->create();

        $user->update(['role_id' => Role::named(Role::ADMINISTRATOR)->id]);

        $this->assertTrue($user->fresh()->isAdvisor());
    }

    public function test_managers_can_only_manage_advisors_on_their_own_teams(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->manager()->forTeam($team)->create();
        $advisor = User::factory()->advisor()->forTeam($team)->create();
        $advisorPlus = User::factory()->advisorPlus()->forTeam($team)->create();
        $peerManager = User::factory()->manager()->forTeam($team)->create();
        $outsideAdvisor = User::factory()->advisor()->create();

        $this->assertTrue($manager->canManageUser($advisor));
        $this->assertTrue($manager->canManageUser($advisorPlus));
        $this->assertFalse($manager->canManageUser($peerManager));
        $this->assertFalse($manager->canManageUser($outsideAdvisor));
        $this->assertFalse($manager->canManageUser($manager));

        $this->assertTrue($manager->can('update', $advisor));
        $this->assertFalse($manager->can('update', $outsideAdvisor));
        $this->assertFalse($advisorPlus->can('update', $advisor));
    }

    public function test_administrators_can_manage_everyone_but_themselves(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->assertTrue($administrator->canManageUser(User::factory()->advisor()->create()));
        $this->assertTrue($administrator->canManageUser(User::factory()->manager()->create()));
        $this->assertTrue($administrator->canManageUser(User::factory()->administrator()->create()));
        $this->assertFalse($administrator->canManageUser($administrator));
    }

    public function test_minimum_role_middleware_parameter(): void
    {
        Route::middleware(['web', 'auth', EnsureTeamMembership::class.':manager'])
            ->get('{current_team}/managers-only', fn () => 'ok');

        $team = Team::factory()->create();
        $advisorPlus = User::factory()->advisorPlus()->forTeam($team)->create();
        $manager = User::factory()->manager()->forTeam($team)->create();

        $this->actingAs($advisorPlus)->get("/{$team->slug}/managers-only")->assertForbidden();
        $this->actingAs($manager)->get("/{$team->slug}/managers-only")->assertOk();
    }
}

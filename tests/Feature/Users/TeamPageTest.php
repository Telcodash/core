<?php

namespace Tests\Feature\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_can_view_their_team_members()
    {
        $manager = User::factory()->create();

        foreach (Role::cases() as $role) {
            $user = User::factory()->role($role)->forTeam($manager->team)->create();

            $this->actingAs($user)
                ->get(route('team.index', ['current_team' => $manager->team->slug]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('team/index')
                    ->where('canManage', $role === Role::Administrator)
                    ->has('members', User::where('team_id', $manager->team_id)->count()));
        }
    }

    public function test_users_cannot_view_another_teams_members()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->get(route('team.index', ['current_team' => $other->team->slug]))
            ->assertForbidden();
    }

    public function test_non_administrators_cannot_manage_members()
    {
        $manager = User::factory()->create();
        $target = User::factory()->advisor()->forTeam($manager->team)->create();
        $slug = $manager->team->slug;

        foreach ([$manager, $target] as $actor) {
            $this->actingAs($actor)->post(route('team.members.store', $slug), [
                'name' => 'N', 'email' => 'n@example.com', 'role' => 'advisor', 'password' => 'password',
            ])->assertForbidden();

            if ($actor->is($target)) {
                $this->actingAs($actor)->patch(route('team.members.update', [$slug, $target]), [
                    'name' => 'Changed', 'email' => $target->email, 'role' => 'advisor',
                ])->assertForbidden();
            }

            $this->actingAs($actor)->delete(route('team.members.destroy', [$slug, $target]))->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'n@example.com']);
        $this->assertModelExists($target);
        $this->assertNotSame('Changed', $target->fresh()->name);
    }

    public function test_administrators_can_create_update_and_delete_members()
    {
        $admin = User::factory()->administrator()->create();
        $slug = $admin->team->slug;

        $this->actingAs($admin)->post(route('team.members.store', $slug), [
            'name' => 'New Person', 'email' => 'New@Example.com', 'role' => 'advisor_plus', 'password' => 'password',
        ])->assertSessionHasNoErrors();

        $member = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame($admin->team_id, $member->team_id);
        $this->assertSame(Role::AdvisorPlus, $member->role);

        $this->actingAs($admin)->patch(route('team.members.update', [$slug, $member]), [
            'name' => 'Renamed', 'email' => 'new@example.com', 'role' => 'manager', 'password' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $member->fresh()->name);
        $this->assertSame(Role::Manager, $member->fresh()->role);

        $this->actingAs($admin)->delete(route('team.members.destroy', [$slug, $member]))->assertSessionHasNoErrors();
        $this->assertModelMissing($member);
    }

    public function test_administrators_cannot_leave_a_team_without_a_manager_or_touch_other_teams()
    {
        $admin = User::factory()->administrator()->create();
        $advisor = User::factory()->advisor()->forTeam($admin->team)->create();
        $outsider = User::factory()->create();
        $slug = $admin->team->slug;

        $this->actingAs($admin)->patch(route('team.members.update', [$slug, $admin]), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'advisor',
        ])->assertSessionHasErrors('role');
        $this->assertSame(Role::Administrator, $admin->fresh()->role);

        $this->actingAs($admin)->delete(route('team.members.destroy', [$slug, $admin]))->assertForbidden();

        $this->actingAs($admin)->delete(route('team.members.destroy', [$slug, $outsider]))->assertNotFound();
        $this->assertModelExists($outsider);
        $this->assertModelExists($advisor);
    }

    public function test_managers_can_switch_advisors_between_advisor_and_advisor_plus()
    {
        $manager = User::factory()->create();
        $advisor = User::factory()->advisor()->forTeam($manager->team)->create();
        $slug = $manager->team->slug;

        $this->actingAs($manager)->patch(route('team.members.update', [$slug, $advisor]), [
            'name' => 'Renamed', 'email' => $advisor->email, 'role' => 'advisor_plus',
        ])->assertSessionHasNoErrors();

        $this->assertSame(Role::AdvisorPlus, $advisor->fresh()->role);
        $this->assertSame('Renamed', $advisor->fresh()->name);

        $this->actingAs($manager)->patch(route('team.members.update', [$slug, $advisor]), [
            'name' => 'Renamed', 'email' => $advisor->email, 'role' => 'advisor',
        ])->assertSessionHasNoErrors();
        $this->assertSame(Role::Advisor, $advisor->fresh()->role);
    }

    public function test_managers_cannot_promote_demote_or_reset_passwords_beyond_their_remit()
    {
        $manager = User::factory()->create();
        $otherManager = User::factory()->manager()->forTeam($manager->team)->create();
        $admin = User::factory()->administrator()->forTeam($manager->team)->create();
        $advisor = User::factory()->advisor()->forTeam($manager->team)->create();
        $slug = $manager->team->slug;

        // Cannot assign manager / administrator.
        foreach (['manager', 'administrator'] as $role) {
            $this->actingAs($manager)->patch(route('team.members.update', [$slug, $advisor]), [
                'name' => $advisor->name, 'email' => $advisor->email, 'role' => $role,
            ])->assertSessionHasErrors('role');
        }

        // Cannot set passwords.
        $this->actingAs($manager)->patch(route('team.members.update', [$slug, $advisor]), [
            'name' => $advisor->name, 'email' => $advisor->email, 'role' => 'advisor', 'password' => 'new-password-123',
        ])->assertSessionHasErrors('password');

        // Cannot edit managers or administrators (including themselves), nor create/delete.
        foreach ([$otherManager, $admin, $manager] as $target) {
            $this->actingAs($manager)->patch(route('team.members.update', [$slug, $target]), [
                'name' => 'X', 'email' => $target->email, 'role' => 'advisor',
            ])->assertForbidden();
        }

        $this->actingAs($manager)->delete(route('team.members.destroy', [$slug, $advisor]))->assertForbidden();
        $this->assertSame(Role::Manager, $otherManager->fresh()->role);
        $this->assertSame(Role::Advisor, $advisor->fresh()->role);
    }

    public function test_page_flags_which_members_a_manager_can_edit()
    {
        $manager = User::factory()->create();
        User::factory()->advisorPlus()->forTeam($manager->team)->create();
        User::factory()->administrator()->forTeam($manager->team)->create();

        $this->actingAs($manager)
            ->get(route('team.index', ['current_team' => $manager->team->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', false)
                ->has('roles', 2)
                ->where('members', fn ($members) => collect($members)->every(
                    fn ($m) => $m['editable'] === in_array($m['role'], ['advisor', 'advisor_plus'], true),
                )));
    }
}

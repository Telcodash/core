<?php

namespace Tests\Feature\Users;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_use_user_crud()
    {
        $manager = User::factory()->create();
        $target = User::factory()->advisor()->forTeam($manager->team)->create();

        $this->get(route('users.index'))->assertRedirect(route('login'));
        $this->actingAs($manager)->get(route('users.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('users.store'), [])->assertForbidden();
        $this->actingAs($manager)->patch(route('users.update', $target), [])->assertForbidden();
        $this->actingAs($manager)->delete(route('users.destroy', $target))->assertForbidden();
        $this->assertModelExists($target);
    }

    public function test_search_by_name_email_or_team_name()
    {
        $admin = User::factory()->administrator()->create();
        $alpha = Team::factory()->create(['name' => 'Alpha Advisers']);
        User::factory()->forTeam($alpha)->create(['name' => 'Zed Zebra', 'email' => 'zed@example.com']);
        User::factory()->create(['name' => 'Quentin Quokka', 'email' => 'quentin@somewhere.test']);

        $total = fn (string $q) => $this->actingAs($admin)->get(route('users.index', ['q' => $q]))
            ->assertInertia(fn (Assert $page) => $page->component('admin/users/index')->where('users.total', 1));

        $total('zebra');
        $total('QUENTIN@SOMEWHERE');
        $total('alpha adv');

        $this->actingAs($admin)->get(route('users.index', ['q' => '%']))
            ->assertInertia(fn (Assert $page) => $page->where('users.total', 0));

        $this->actingAs($admin)->get(route('users.index'))
            ->assertInertia(fn (Assert $page) => $page->where('users.total', 3)->has('teams', 3)->has('roles', 4));
    }

    public function test_create_a_user_on_an_existing_team()
    {
        $admin = User::factory()->administrator()->create();
        $team = $admin->team;

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Adam', 'email' => 'Adam@Example.com', 'role' => 'advisor',
            'password' => 'password', 'team_id' => $team->id,
        ])->assertSessionHasNoErrors();

        $adam = User::where('email', 'adam@example.com')->firstOrFail();
        $this->assertSame($team->id, $adam->team_id);
        $this->assertSame(Role::Advisor, $adam->role);
    }

    public function test_create_a_manager_on_a_new_team_but_not_an_advisor()
    {
        $admin = User::factory()->administrator()->create();
        $base = ['name' => 'N', 'email' => 'n@example.com', 'password' => 'password', 'new_team_name' => 'Fresh Team'];

        $this->actingAs($admin)->post(route('users.store'), [...$base, 'role' => 'advisor'])
            ->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('teams', ['name' => 'Fresh Team']);

        $this->actingAs($admin)->post(route('users.store'), [...$base, 'role' => 'manager'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Fresh Team', User::where('email', 'n@example.com')->first()->team->name);
    }

    public function test_create_requires_exactly_one_team_choice_and_a_valid_team_name()
    {
        $admin = User::factory()->administrator()->create();
        $base = ['name' => 'N', 'email' => 'n@example.com', 'password' => 'password', 'role' => 'manager'];

        $this->actingAs($admin)->post(route('users.store'), $base)->assertSessionHasErrors('team_id');
        $this->actingAs($admin)->post(route('users.store'), [...$base, 'team_id' => $admin->team_id, 'new_team_name' => 'X'])
            ->assertSessionHasErrors('team_id');
        $this->actingAs($admin)->post(route('users.store'), [...$base, 'new_team_name' => 'Admin'])
            ->assertSessionHasErrors('new_team_name');
        $this->actingAs($admin)->post(route('users.store'), [...$base, 'team_id' => 99999])
            ->assertSessionHasErrors('team_id');
    }

    public function test_update_a_user_including_role_team_and_password()
    {
        $admin = User::factory()->administrator()->create();
        $other = User::factory()->create();
        $user = User::factory()->advisor()->forTeam($admin->team)->create();

        $this->actingAs($admin)->patch(route('users.update', $user), [
            'name' => 'Moved', 'email' => $user->email, 'role' => 'advisor_plus',
            'team_id' => $other->team_id, 'password' => 'a-new-password',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Moved', $user->name);
        $this->assertSame(Role::AdvisorPlus, $user->role);
        $this->assertSame($other->team_id, $user->team_id);
        $this->assertTrue(Hash::check('a-new-password', $user->password));

        // A blank password leaves it unchanged.
        $this->actingAs($admin)->patch(route('users.update', $user), [
            'name' => 'Moved', 'email' => $user->email, 'role' => 'advisor_plus',
            'team_id' => $other->team_id, 'password' => '',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('a-new-password', $user->fresh()->password));
    }

    public function test_the_last_manager_cannot_be_demoted_moved_or_deleted()
    {
        $admin = User::factory()->administrator()->create();
        $manager = User::factory()->create();
        User::factory()->advisor()->forTeam($manager->team)->create();
        $elsewhere = User::factory()->create()->team;

        $payload = ['name' => $manager->name, 'email' => $manager->email, 'role' => 'manager', 'team_id' => $manager->team_id];

        $this->actingAs($admin)->patch(route('users.update', $manager), [...$payload, 'role' => 'advisor'])
            ->assertSessionHasErrors('role');
        $this->actingAs($admin)->patch(route('users.update', $manager), [...$payload, 'team_id' => $elsewhere->id])
            ->assertSessionHasErrors('team_id');
        $this->actingAs($admin)->delete(route('users.destroy', $manager))->assertSessionHasErrors('user');

        $manager->refresh();
        $this->assertSame(Role::Manager, $manager->role);
        $this->assertModelExists($manager);
    }

    public function test_an_administrator_cannot_change_their_own_role_or_delete_themselves()
    {
        $admin = User::factory()->administrator()->create();
        User::factory()->administrator()->forTeam($admin->team)->create();

        $this->actingAs($admin)->patch(route('users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'advisor', 'team_id' => $admin->team_id,
        ])->assertSessionHasErrors('role');

        $this->actingAs($admin)->delete(route('users.destroy', $admin))->assertForbidden();
        $this->assertSame(Role::Administrator, $admin->fresh()->role);
    }

    public function test_delete_a_user()
    {
        $admin = User::factory()->administrator()->create();
        $user = User::factory()->advisor()->forTeam($admin->team)->create();

        $this->actingAs($admin)->delete(route('users.destroy', $user))->assertSessionHasNoErrors();
        $this->assertModelMissing($user);
    }
}

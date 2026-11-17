<?php

namespace Tests\Feature\Users;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_can_be_created_with_a_new_team()
    {
        $this->artisan('user:create', [
            '--name' => 'Mia Manager',
            '--email' => 'mia@example.com',
            '--password' => 'password',
            '--role' => 'manager',
            '--new-team' => 'Alpha Team',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $user = User::where('email', 'mia@example.com')->firstOrFail();

        $this->assertSame(Role::Manager, $user->role);
        $this->assertSame('Alpha Team', $user->team->name);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_an_advisor_can_join_an_existing_team()
    {
        $manager = User::factory()->create();

        $this->artisan('user:create', [
            '--name' => 'Adam Advisor',
            '--email' => 'adam@example.com',
            '--password' => 'password',
            '--role' => 'advisor',
            '--team' => $manager->team->slug,
            '--no-interaction' => true,
        ])->assertSuccessful();

        $this->assertSame($manager->team_id, User::where('email', 'adam@example.com')->value('team_id'));
    }

    public function test_an_advisor_cannot_create_a_new_team()
    {
        $this->artisan('user:create', [
            '--name' => 'Adam Advisor',
            '--email' => 'adam@example.com',
            '--password' => 'password',
            '--role' => 'advisor_plus',
            '--new-team' => 'Solo Team',
            '--no-interaction' => true,
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('teams', 0);
    }

    public function test_a_team_is_required()
    {
        $this->artisan('user:create', [
            '--name' => 'Mia Manager',
            '--email' => 'mia@example.com',
            '--password' => 'password',
            '--role' => 'manager',
            '--no-interaction' => true,
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_role_and_unknown_team_are_rejected()
    {
        $this->artisan('user:create', [
            '--name' => 'X', '--email' => 'x@example.com', '--password' => 'password',
            '--role' => 'owner', '--new-team' => 'T', '--no-interaction' => true,
        ])->assertFailed();

        $this->artisan('user:create', [
            '--name' => 'X', '--email' => 'x@example.com', '--password' => 'password',
            '--role' => 'advisor', '--team' => 'does-not-exist', '--no-interaction' => true,
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_duplicate_email_is_rejected()
    {
        $existing = User::factory()->create();

        $this->artisan('user:create', [
            '--name' => 'X', '--email' => $existing->email, '--password' => 'password',
            '--role' => 'manager', '--new-team' => 'Another', '--no-interaction' => true,
        ])->assertFailed();

        $this->assertSame(1, Team::count());
    }

    public function test_registration_and_email_verification_routes_are_gone()
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertStatus(404);
        $this->get('/email/verify')->assertNotFound();
    }
}

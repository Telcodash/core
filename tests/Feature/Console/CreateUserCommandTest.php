<?php

namespace Tests\Feature\Console;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $options
     */
    private function createUser(array $options): PendingCommand
    {
        $defaults = [
            '--name' => 'Jane Doe',
            '--email' => 'jane@example.com',
            '--password' => 'secret-password',
            '--no-interaction' => true,
        ];

        return $this->artisan('user:create', [...$defaults, ...$options]);
    }

    public function test_advisors_can_be_assigned_to_a_team_with_a_manager(): void
    {
        $team = Team::factory()->create(['slug' => 'sales']);
        User::factory()->manager()->forTeam($team)->create();

        $this->createUser(['--role' => 'advisor', '--team' => 'sales'])->assertSuccessful();

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        $this->assertTrue($user->isAdvisor());
        $this->assertTrue($user->belongsToTeam($team));
        $this->assertTrue($user->isCurrentTeam($team));
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_teams_can_be_referenced_by_id(): void
    {
        $team = Team::factory()->create();
        User::factory()->manager()->forTeam($team)->create();

        $this->createUser(['--role' => 'advisor_plus', '--team' => (string) $team->id])->assertSuccessful();

        $this->assertTrue(User::where('email', 'jane@example.com')->firstOrFail()->belongsToTeam($team));
    }

    public function test_numeric_slugs_take_precedence_over_ids(): void
    {
        $byId = Team::factory()->create(['slug' => 'first']);
        $bySlug = Team::factory()->create(['slug' => (string) $byId->id]);

        $this->createUser(['--role' => 'manager', '--team' => (string) $byId->id])->assertSuccessful();

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        $this->assertTrue($user->belongsToTeam($bySlug));
        $this->assertFalse($user->belongsToTeam($byId));
    }

    public function test_advisors_cannot_join_a_team_without_a_manager(): void
    {
        $team = Team::factory()->create(['slug' => 'sales']);

        $this->createUser(['--role' => 'advisor', '--team' => 'sales'])
            ->expectsOutputToContain('has no manager')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    }

    public function test_advisors_must_be_given_a_team(): void
    {
        $this->createUser(['--role' => 'advisor_plus'])
            ->expectsOutputToContain('must be assigned to an existing team')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    }

    public function test_advisors_cannot_create_teams(): void
    {
        $this->createUser(['--role' => 'advisor', '--new-team' => 'My Team'])
            ->expectsOutputToContain('cannot create a team')
            ->assertFailed();

        $this->assertDatabaseMissing('teams', ['name' => 'My Team']);
    }

    public function test_managers_can_create_a_new_team(): void
    {
        $this->createUser(['--role' => 'manager', '--new-team' => 'North Region'])->assertSuccessful();

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $team = Team::where('name', 'North Region')->firstOrFail();

        $this->assertTrue($user->isManager());
        $this->assertTrue($user->belongsToTeam($team));
        $this->assertTrue($team->hasManager());
    }

    public function test_managers_can_join_an_existing_team(): void
    {
        $team = Team::factory()->create(['slug' => 'sales']);

        $this->createUser(['--role' => 'manager', '--team' => 'sales'])->assertSuccessful();

        $this->assertTrue($team->hasManager());
    }

    public function test_managers_must_join_or_create_a_team(): void
    {
        $this->createUser(['--role' => 'manager'])
            ->expectsOutputToContain('Managers must be assigned')
            ->assertFailed();
    }

    public function test_administrators_do_not_need_a_team(): void
    {
        $this->createUser(['--role' => 'administrator'])->assertSuccessful();

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        $this->assertTrue($user->isAdministrator());
        $this->assertCount(0, $user->teams);
    }

    public function test_team_and_new_team_are_mutually_exclusive(): void
    {
        Team::factory()->create(['slug' => 'sales']);

        $this->createUser(['--role' => 'manager', '--team' => 'sales', '--new-team' => 'Other'])
            ->assertFailed();
    }

    public function test_input_is_validated(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->createUser(['--role' => 'administrator'])
            ->expectsOutputToContain('email has already been taken')
            ->assertFailed();

        $this->createUser(['--email' => 'new@example.com', '--role' => 'owner'])
            ->expectsOutputToContain('selected role is invalid')
            ->assertFailed();

        $this->createUser(['--email' => 'new@example.com', '--role' => 'manager', '--team' => 'missing'])
            ->expectsOutputToContain('does not exist')
            ->assertFailed();
    }

    public function test_users_can_be_created_interactively(): void
    {
        $team = Team::factory()->create(['name' => 'Sales', 'slug' => 'sales']);
        User::factory()->manager()->forTeam($team)->create();

        $this->artisan('user:create')
            ->expectsQuestion('Name', 'Jane Doe')
            ->expectsQuestion('Email address', 'jane@example.com')
            ->expectsQuestion('Password', 'secret-password')
            ->expectsQuestion('Confirm password', 'secret-password')
            ->expectsChoice('Role', 'advisor', ['advisor' => 'Advisor', 'advisor_plus' => 'Advisor Plus', 'manager' => 'Manager', 'administrator' => 'Administrator'])
            ->expectsChoice('Team', 'sales', ['sales' => 'Sales'])
            ->assertSuccessful();

        $this->assertTrue(User::where('email', 'jane@example.com')->firstOrFail()->belongsToTeam($team));
    }

    public function test_managers_can_create_a_team_interactively(): void
    {
        Team::factory()->create(['name' => 'Sales', 'slug' => 'sales']);

        $this->artisan('user:create')
            ->expectsQuestion('Name', 'Jane Doe')
            ->expectsQuestion('Email address', 'jane@example.com')
            ->expectsQuestion('Password', 'secret-password')
            ->expectsQuestion('Confirm password', 'secret-password')
            ->expectsChoice('Role', 'manager', ['advisor' => 'Advisor', 'advisor_plus' => 'Advisor Plus', 'manager' => 'Manager', 'administrator' => 'Administrator'])
            ->expectsChoice('Team', 'create', ['join' => 'Join an existing team', 'create' => 'Create a new team'])
            ->expectsQuestion('New team name', 'North Region')
            ->assertSuccessful();

        $this->assertTrue(Team::where('name', 'North Region')->firstOrFail()->hasManager());
    }
}

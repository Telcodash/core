<?php

namespace App\Console\Commands;

use App\Actions\Users\CreateUser;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('user:create
    {--name= : The user\'s full name}
    {--email= : The user\'s email address}
    {--password= : The user\'s password (prompted for when omitted)}
    {--role= : One of: advisor, advisor_plus, manager, administrator}
    {--team= : Slug or ID of an existing team to join}
    {--new-team= : Name of a new team to create (managers and administrators only)}')]
#[Description('Create a user and assign them to a team')]
class CreateUserCommand extends Command
{
    private const string JOIN = 'join';

    private const string CREATE = 'create';

    private const string NONE = 'none';

    public function handle(CreateUser $createUser): int
    {
        $input = $this->input->isInteractive() ? $this->promptForInput() : $this->optionInput();

        if ($input === null) {
            return self::FAILURE;
        }

        try {
            $user = $createUser->handle($input);
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->components->info("User [{$user->email}] created.");

        $this->components->twoColumnDetail('Name', $user->name);
        $this->components->twoColumnDetail('Email', $user->email);
        $this->components->twoColumnDetail('Role', $user->role->label);
        $this->components->twoColumnDetail('Team', $user->current_team_id ? $user->currentTeam->name : '<fg=gray>None</>');

        return self::SUCCESS;
    }

    /**
     * @return array{name: string, email: string, password: string, role: string, team: string|null, new_team: string|null}
     */
    private function optionInput(): array
    {
        return [
            'name' => (string) $this->option('name'),
            'email' => (string) $this->option('email'),
            'password' => (string) $this->option('password'),
            'role' => (string) $this->option('role'),
            'team' => $this->option('team'),
            'new_team' => $this->option('new-team'),
        ];
    }

    /**
     * @return array{name: string, email: string, password: string, role: string, team: string|null, new_team: string|null}|null
     */
    private function promptForInput(): ?array
    {
        $name = $this->option('name') ?: text(label: 'Name', required: true);

        $email = $this->option('email') ?: text(
            label: 'Email address',
            required: true,
            validate: ['email' => 'email|unique:users,email'],
        );

        $password = $this->option('password') ?: $this->promptForPassword();

        $role = Role::named($this->option('role') ?: $this->selectKey(
            label: 'Role',
            options: Role::ordered()->pluck('label', 'name')->all(),
            default: Role::ADVISOR,
        ));

        [$team, $newTeam] = $this->option('team') || $this->option('new-team')
            ? [$this->option('team'), $this->option('new-team')]
            : $this->promptForTeam($role);

        if ($team === false) {
            return null;
        }

        return [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role->name,
            'team' => $team,
            'new_team' => $newTeam,
        ];
    }

    private function promptForPassword(): string
    {
        $password = password(label: 'Password', required: true);

        password(
            label: 'Confirm password',
            required: true,
            validate: fn (string $value) => $value === $password ? null : 'The passwords do not match.',
        );

        return $password;
    }

    /**
     * @return array{0: string|false|null, 1: string|null} [existing team slug (false = abort), new team name]
     */
    private function promptForTeam(Role $role): array
    {
        if ($role->requiresTeamManager()) {
            $teams = Team::query()->withManager()->orderBy('name')->pluck('name', 'slug');

            if ($teams->isEmpty()) {
                $this->components->error("{$role->label} users must join a team with a manager, but none exist. Create a manager first.");

                return [false, null];
            }

            return [$this->selectKey(label: 'Team', options: $teams->all()), null];
        }

        $teams = Team::query()->orderBy('name')->pluck('name', 'slug');

        $choices = array_filter([
            self::JOIN => $teams->isNotEmpty() ? 'Join an existing team' : null,
            self::CREATE => 'Create a new team',
            self::NONE => $role->isAdministrator() ? 'No team' : null,
        ]);

        $choice = count($choices) === 1 ? array_key_first($choices) : select(label: 'Team', options: $choices);

        return match ($choice) {
            self::JOIN => [$this->selectKey(label: 'Team', options: $teams->all()), null],
            self::CREATE => [null, text(label: 'New team name', required: true)],
            default => [null, null],
        };
    }

    /**
     * Prompt for one of the given options and return its key as a string.
     *
     * PHP turns numeric-string array keys into integers, so a team slug such as "2024"
     * comes back from select() as an int; convert it back to the slug it represents.
     *
     * @param  array<int|string, string>  $options
     */
    private function selectKey(string $label, array $options, ?string $default = null): string
    {
        return (string) select(label: $label, options: $options, default: $default, scroll: 10);
    }
}

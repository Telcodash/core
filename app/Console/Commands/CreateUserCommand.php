<?php

namespace App\Console\Commands;

use App\Actions\Users\CreateUser;
use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create
        {--name= : The user\'s name}
        {--email= : The user\'s email address}
        {--password= : The user\'s password (prompted for if omitted)}
        {--role= : One of: advisor, advisor_plus, manager, administrator}
        {--team= : Slug of an existing team to join}
        {--new-team= : Name of a new team to create (manager or above only)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a user and assign them to a new or existing team';

    public function handle(CreateUser $createUser): int
    {
        $interactive = $this->input->isInteractive();

        $name = $this->option('name') ?? ($interactive ? text('Name', required: true) : null);
        $email = $this->option('email') ?? ($interactive ? text('Email', required: true) : null);
        $password = $this->option('password') ?? ($interactive ? password('Password', required: true) : null);
        $roleValue = $this->option('role') ?? ($interactive ? select(
            'Role',
            collect(Role::cases())->mapWithKeys(fn (Role $role) => [$role->value => $role->label()])->all(),
        ) : null);

        $role = is_string($roleValue) ? Role::tryFrom($roleValue) : null;

        $teamSlug = $this->option('team');
        $newTeamName = $this->option('new-team');

        if ($teamSlug === null && $newTeamName === null && $role !== null) {
            [$teamSlug, $newTeamName] = $this->promptForTeam($role, $interactive);
        }

        try {
            Validator::make([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $roleValue,
                'team' => $teamSlug,
                'new_team' => $newTeamName,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
                'password' => ['required', 'string', Password::default()],
                'role' => ['required', Rule::enum(Role::class)],
                'team' => ['nullable', 'required_without:new_team', 'prohibits:new_team', Rule::exists(Team::class, 'slug')->withoutTrashed()],
                'new_team' => ['nullable', 'required_without:team', 'string', 'max:255'],
            ])->after(function ($validator) use ($role, $newTeamName) {
                if ($role !== null && $newTeamName !== null && ! $role->isManagerOrAbove()) {
                    $validator->errors()->add('new_team', 'Only a manager or above can start a new team; other roles must join an existing team.');
                }
            })->validate();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->components->error($message);
                }
            }

            return self::FAILURE;
        }

        $user = $createUser->handle(
            name: $name,
            email: $email,
            password: $password,
            role: $role,
            team: $teamSlug !== null ? Team::where('slug', $teamSlug)->firstOrFail() : null,
            newTeamName: $newTeamName,
        );

        $this->components->info("Created {$role->label()} [{$user->email}] on team [{$user->team->name}].");

        return self::SUCCESS;
    }

    /**
     * Ask whether to join an existing team or (manager or above) create a new one.
     *
     * @return array{0: string|null, 1: string|null} [existing team slug, new team name]
     */
    private function promptForTeam(Role $role, bool $interactive): array
    {
        if (! $interactive) {
            return [null, null];
        }

        $teams = Team::query()->orderBy('name')->pluck('name', 'slug')->all();

        $choices = [];

        if ($role->isManagerOrAbove()) {
            $choices['new'] = 'Create a new team';
        }

        if ($teams !== []) {
            $choices['existing'] = 'Join an existing team';
        }

        if ($choices === []) {
            $this->components->error('No teams exist yet. Create a manager (or administrator) with a new team first.');

            return [null, null];
        }

        $choice = count($choices) === 1 ? array_key_first($choices) : select('Team', $choices);

        return $choice === 'new'
            ? [null, text('New team name', required: true)]
            : [(string) select('Existing team', $teams), null];
    }
}

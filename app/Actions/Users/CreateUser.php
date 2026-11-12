<?php

namespace App\Actions\Users;

use App\Actions\Teams\CreateTeam;
use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Rules\TeamName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Provision a user account. Since self-registration is disabled, this is the only
 * way users are created.
 *
 * Team rules:
 *  - advisor / advisor_plus: must join an existing team that has a manager.
 *  - manager: must join an existing team or create a new one.
 *  - administrator: may join or create a team, or have none (they can see every team).
 */
class CreateUser
{
    use ProfileValidationRules;

    public function __construct(private CreateTeam $createTeam)
    {
        //
    }

    /**
     * @param  array{name: string, email: string, password: string, role: string, team?: string|null, new_team?: string|null}  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input): User
    {
        $input['email'] = Str::lower($input['email']);

        $validated = Validator::make($input, [
            ...$this->profileRules(),
            'password' => ['required', 'string', Password::default()],
            'role' => ['required', 'string', Rule::in(Role::NAMES)],
            'team' => ['nullable', 'string', 'prohibits:new_team'],
            'new_team' => ['nullable', 'string', 'max:255', new TeamName],
        ])->validate();

        $role = Role::named($validated['role']);
        $team = $this->resolveTeam($validated['team'] ?? null);
        $newTeam = $validated['new_team'] ?? null;

        $this->ensureTeamAssignmentIsAllowed($role, $team, $newTeam);

        return DB::transaction(function () use ($validated, $role, $team, $newTeam) {
            $user = new User([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $user->assignRole($role)->save();

            if ($newTeam !== null) {
                $this->createTeam->handle($user, $newTeam);
            } elseif ($team !== null) {
                $team->members()->attach($user);
                $user->switchTeam($team);
            } else {
                $user->switchToFallbackTeam();
            }

            return $user;
        });
    }

    /**
     * Resolve an existing team by slug, falling back to ID. Slugs take precedence so a
     * numeric slug (e.g. "2024") can never resolve to a different team with that ID.
     */
    private function resolveTeam(?string $team): ?Team
    {
        if ($team === null) {
            return null;
        }

        $resolved = Team::query()->where('slug', $team)->first()
            ?? (ctype_digit($team) ? Team::query()->find((int) $team) : null);

        if (! $resolved) {
            throw ValidationException::withMessages(['team' => __('The team [:team] does not exist.', ['team' => $team])]);
        }

        return $resolved;
    }

    /**
     * @throws ValidationException
     */
    private function ensureTeamAssignmentIsAllowed(Role $role, ?Team $team, ?string $newTeam): void
    {
        if ($newTeam !== null && ! $role->canCreateTeams()) {
            throw ValidationException::withMessages([
                'new_team' => __('Users with the :role role cannot create a team.', ['role' => $role->label]),
            ]);
        }

        if ($role->requiresTeamManager()) {
            if ($team === null) {
                throw ValidationException::withMessages([
                    'team' => __(':role users must be assigned to an existing team.', ['role' => $role->label]),
                ]);
            }

            if (! $team->hasManager()) {
                throw ValidationException::withMessages([
                    'team' => __('The team [:team] has no manager. :role users must be assigned to a team with a manager.', [
                        'team' => $team->name,
                        'role' => $role->label,
                    ]),
                ]);
            }
        }

        if ($role->isManager() && $team === null && $newTeam === null) {
            throw ValidationException::withMessages([
                'team' => __('Managers must be assigned to an existing team or create a new one.'),
            ]);
        }
    }
}

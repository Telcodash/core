<?php

namespace App\Actions\Users;

use App\Actions\Teams\EnsureAdvisorsAreManaged;
use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    use ProfileValidationRules;

    public function __construct(private EnsureAdvisorsAreManaged $ensureAdvisorsAreManaged)
    {
        //
    }

    /**
     * Update a user's details and role, and optionally set a new password.
     *
     * @param  array{name: string, email: string, role: string, password?: string|null}  $input
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $input): User
    {
        $input['email'] = Str::lower($input['email']);

        $validated = Validator::make($input, [
            ...$this->profileRules($user->id),
            'role' => ['required', 'string', Rule::in(Role::NAMES)],
            'password' => ['nullable', 'string', Password::default()],
        ])->validate();

        return DB::transaction(function () use ($user, $validated) {
            $wasManager = $user->isManager();

            $user->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            if (filled($validated['password'] ?? null)) {
                $user->password = $validated['password'];
            }

            $user->assignRole($validated['role'])->save();

            // A user moving into an advisor role needs a managed team; a manager moving out of
            // the role can strand the advisors on their teams.
            $affected = collect([$user]);

            if ($wasManager && ! $user->isManager()) {
                $affected = $affected->merge(Team::advisorsOf($user->teams()->pluck('teams.id')));
            }

            $this->ensureAdvisorsAreManaged->handle($affected, 'role');

            return $user;
        });
    }
}

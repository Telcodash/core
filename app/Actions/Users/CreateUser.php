<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Exceptions\TeamRequiresManagerException;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateUser
{
    /**
     * Create a user and place them on a team.
     *
     * Pass an existing team (any role), or a new team name (manager or above only),
     * so that every team starts with a manager.
     */
    public function handle(
        string $name,
        string $email,
        string $password,
        Role $role,
        ?Team $team = null,
        ?string $newTeamName = null,
    ): User {
        if (($team === null) === ($newTeamName === null)) {
            throw new \InvalidArgumentException('Provide either an existing team or a new team name.');
        }

        if ($newTeamName !== null && ! $role->isManagerOrAbove()) {
            throw TeamRequiresManagerException::forTeam();
        }

        return DB::transaction(function () use ($name, $email, $password, $role, $team, $newTeamName) {
            $team ??= Team::create(['name' => $newTeamName]);

            return User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'team_id' => $team->id,
            ]);
        });
    }
}

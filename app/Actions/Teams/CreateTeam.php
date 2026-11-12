<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateTeam
{
    /**
     * Create a new team with the given user as its first member.
     *
     * Only roles that may create teams (managers and administrators) are allowed.
     */
    public function handle(User $user, string $name): Team
    {
        if (! $user->role->canCreateTeams()) {
            throw new InvalidArgumentException("Users with the [{$user->role->name}] role cannot create teams.");
        }

        return DB::transaction(function () use ($user, $name) {
            $team = Team::create(['name' => $name]);

            $team->members()->attach($user);

            $user->switchTeam($team);

            return $team;
        });
    }
}

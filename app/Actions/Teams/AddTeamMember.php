<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AddTeamMember
{
    /**
     * Add the user to the team. Adding a member can never strand an advisor.
     *
     * @throws ValidationException
     */
    public function handle(Team $team, User $user): void
    {
        if ($user->belongsToTeam($team)) {
            throw ValidationException::withMessages([
                'member' => __(':name is already a member of this team.', ['name' => $user->name]),
            ]);
        }

        $team->members()->attach($user);

        $user->refreshCurrentTeam();
    }
}

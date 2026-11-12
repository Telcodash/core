<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveTeamMember
{
    public function __construct(private EnsureAdvisorsAreManaged $ensureAdvisorsAreManaged)
    {
        //
    }

    /**
     * Remove the user from the team.
     *
     * @throws ValidationException if an advisor would be left without a managed team
     */
    public function handle(Team $team, User $user): void
    {
        DB::transaction(function () use ($team, $user) {
            // Removing a manager can strand the team's advisors.
            $affected = $user->isManager() ? $team->advisors()->get()->push($user) : collect([$user]);

            $team->members()->detach($user);

            $this->ensureAdvisorsAreManaged->handle($affected, 'member');

            $user->refreshCurrentTeam();
        });
    }
}

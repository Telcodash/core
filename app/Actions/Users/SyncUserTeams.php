<?php

namespace App\Actions\Users;

use App\Actions\Teams\EnsureAdvisorsAreManaged;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncUserTeams
{
    public function __construct(private EnsureAdvisorsAreManaged $ensureAdvisorsAreManaged)
    {
        //
    }

    /**
     * Make the given teams the user's complete set of team memberships.
     *
     * @param  array<int, int|string>  $teamIds
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $teamIds): void
    {
        $teamIds = Team::query()->whereKey($teamIds)->pluck('id');

        DB::transaction(function () use ($user, $teamIds) {
            $removedTeamIds = $user->teams()->whereNotIn('teams.id', $teamIds)->pluck('teams.id');

            // Removing a manager from teams can strand those teams' advisors.
            $affected = collect([$user]);

            if ($user->isManager() && $removedTeamIds->isNotEmpty()) {
                $affected = $affected->merge(Team::advisorsOf($removedTeamIds));
            }

            $user->teams()->sync($teamIds);

            $this->ensureAdvisorsAreManaged->handle($affected, 'teams');

            $user->refreshCurrentTeam();
        });
    }
}

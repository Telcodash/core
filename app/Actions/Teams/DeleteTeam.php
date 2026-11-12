<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteTeam
{
    public function __construct(private EnsureAdvisorsAreManaged $ensureAdvisorsAreManaged)
    {
        //
    }

    /**
     * Delete the team, moving anyone currently on it to a fallback team.
     *
     * @throws ValidationException if an advisor would be left without a managed team
     */
    public function handle(Team $team): void
    {
        DB::transaction(function () use ($team) {
            $advisors = $team->advisors()->get();

            $team->memberships()->delete();
            $team->delete();

            $this->ensureAdvisorsAreManaged->handle($advisors);

            User::where('current_team_id', $team->id)
                ->get()
                ->each(fn (User $user) => $user->refreshCurrentTeam());
        });
    }
}

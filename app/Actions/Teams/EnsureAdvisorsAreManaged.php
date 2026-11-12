<?php

namespace App\Actions\Teams;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Guards the rule that advisors and advisor_plus users always belong to at least one
 * team with a manager. Call it inside a transaction after making a change, passing the
 * users that change could affect; throwing rolls the change back.
 */
class EnsureAdvisorsAreManaged
{
    /**
     * @param  iterable<User>  $users
     *
     * @throws ValidationException
     */
    public function handle(iterable $users, string $errorKey = 'team'): void
    {
        $stranded = collect($users)
            ->unique('id')
            ->map(fn (User $user) => $user->fresh())
            ->filter(fn (?User $user) => $user && $user->role->requiresTeamManager() && ! $user->hasManagedTeam());

        if ($stranded->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => __('Advisors must belong to a team with a manager. This change would leave :names without one.', [
                'names' => $stranded->pluck('name')->join(', ', ' and '),
            ]),
        ]);
    }
}

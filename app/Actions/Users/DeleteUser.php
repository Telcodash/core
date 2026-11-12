<?php

namespace App\Actions\Users;

use App\Actions\Teams\EnsureAdvisorsAreManaged;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    public function __construct(private EnsureAdvisorsAreManaged $ensureAdvisorsAreManaged)
    {
        //
    }

    /**
     * Permanently delete the user.
     *
     * @throws ValidationException if deleting a manager would leave advisors without a managed team
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            $affected = $user->isManager()
                ? Team::advisorsOf($user->teams()->pluck('teams.id'))
                : collect();

            $user->teams()->detach();
            $user->delete();

            $this->ensureAdvisorsAreManaged->handle($affected, 'user');
        });
    }
}

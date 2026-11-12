<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can list other users.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->canManageTeams();
    }

    /**
     * Determine whether the user can view the given user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->is($model) || $user->canManageUser($model);
    }

    /**
     * Determine whether the user can update the given user.
     */
    public function update(User $user, User $model): bool
    {
        return $user->canManageUser($model);
    }

    /**
     * Determine whether the user can delete the given user.
     */
    public function delete(User $user, User $model): bool
    {
        // Deleting a manager must not leave any advisor without a managed team.
        return $user->canManageUser($model) && ! $model->isRequiredManager();
    }
}

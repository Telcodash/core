<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine whether the user can list teams. The list itself is scoped by
     * {@see Team::scopeVisibleTo()} so only administrators see every team.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the team.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->canAccessTeam($team);
    }

    /**
     * Determine whether the user can create teams.
     */
    public function create(User $user): bool
    {
        return $user->role->canCreateTeams();
    }

    /**
     * Determine whether the user can update the team.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->managesTeam($team);
    }

    /**
     * Determine whether the user can leave the team.
     */
    public function leave(User $user, Team $team): bool
    {
        return $team->canDetach($user);
    }

    /**
     * Determine whether the user can add the given user to the team.
     */
    public function addMember(User $user, Team $team, ?User $member = null): bool
    {
        if (! $user->managesTeam($team)) {
            return false;
        }

        if ($member === null) {
            return true;
        }

        // Managers may only add the roles they are allowed to manage.
        return ! $member->belongsToTeam($team)
            && $user->role->canManageRole($member->role);
    }

    /**
     * Determine whether the user can remove the given member from the team.
     */
    public function removeMember(User $user, Team $team, User $member): bool
    {
        return $user->managesTeam($team)
            && $user->canManageUser($member)
            && $team->canDetach($member);
    }

    /**
     * Determine whether the user can delete the team.
     */
    public function delete(User $user, Team $team): bool
    {
        return $user->managesTeam($team) && $team->canBeDeleted();
    }
}

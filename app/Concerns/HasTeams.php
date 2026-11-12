<?php

namespace App\Concerns;

use App\Data\TeamPermissions;
use App\Data\UserTeam;
use App\Models\Membership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

/**
 * @mixin User
 */
trait HasTeams
{
    /**
     * Get all of the teams the user belongs to.
     *
     * @return BelongsToMany<Team, $this, Membership, 'pivot'>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->using(Membership::class)
            ->withTimestamps();
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<Membership, $this>
     */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * Get the user's current team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Get a query for every team this user is allowed to see.
     *
     * Administrators can see all teams; everyone else only sees teams they belong to.
     *
     * @return Builder<Team>
     */
    public function visibleTeams(): Builder
    {
        return Team::query()->visibleTo($this);
    }

    /**
     * Switch to the given team.
     */
    public function switchTeam(Team $team): bool
    {
        if (! $this->canAccessTeam($team)) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);
        $this->setRelation('currentTeam', $team);

        URL::defaults(['current_team' => $team->slug]);

        return true;
    }

    /**
     * Switch to the user's fallback team, or clear the current team if none is available.
     */
    public function switchToFallbackTeam(?Team $excluding = null): void
    {
        $team = $this->fallbackTeam($excluding);

        if ($team) {
            $this->switchTeam($team);

            return;
        }

        $this->update(['current_team_id' => null]);
        $this->setRelation('currentTeam', null);
    }

    /**
     * Make sure the user's current team still exists and is accessible, falling back otherwise.
     *
     * Unlike {@see switchTeam()}, this doesn't touch URL defaults, so it is safe to call on
     * users other than the one making the request.
     */
    public function refreshCurrentTeam(): void
    {
        $this->unsetRelation('currentTeam');

        if ($this->currentTeam && $this->canAccessTeam($this->currentTeam)) {
            return;
        }

        $this->update(['current_team_id' => $this->fallbackTeam()?->id]);
        $this->unsetRelation('currentTeam');
    }

    /**
     * Determine if the user belongs to the given team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    /**
     * Determine if the user may access the given team (as a member or an administrator).
     */
    public function canAccessTeam(Team $team): bool
    {
        return $this->role->canViewAllTeams() || $this->belongsToTeam($team);
    }

    /**
     * Determine if the user manages the given team.
     *
     * Administrators manage every team; managers manage the teams they belong to.
     */
    public function managesTeam(Team $team): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        return $this->isManager() && $this->belongsToTeam($team);
    }

    /**
     * Determine if the user belongs to a team that has a manager, optionally ignoring one team.
     */
    public function hasManagedTeam(?Team $excluding = null): bool
    {
        return $this->teams()
            ->when($excluding, fn ($query) => $query->where('teams.id', '!=', $excluding->id))
            ->withManager()
            ->exists();
    }

    /**
     * Determine if removing this user entirely would leave advisors without a managed team.
     */
    public function isRequiredManager(): bool
    {
        return $this->isManager()
            && $this->teams->contains(fn (Team $team) => ! $team->canDetach($this));
    }

    /**
     * Determine if the given team is the user's current team.
     */
    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    /**
     * Get the teams visible to the user as a collection of UserTeam objects.
     *
     * @return Collection<int, UserTeam>
     */
    public function toUserTeams(bool $includeCurrent = false): Collection
    {
        return $this->visibleTeams()
            ->orderByRaw('LOWER(teams.name)')
            ->get()
            ->map(fn (Team $team) => ! $includeCurrent && $this->isCurrentTeam($team) ? null : $this->toUserTeam($team))
            ->filter()
            ->values();
    }

    /**
     * Get the user's team as a UserTeam object.
     */
    public function toUserTeam(Team $team): UserTeam
    {
        $gate = Gate::forUser($this);

        return new UserTeam(
            id: $team->id,
            name: $team->name,
            slug: $team->slug,
            isMember: $this->belongsToTeam($team),
            canManage: $gate->allows('update', $team),
            canLeave: $gate->allows('leave', $team),
            isCurrent: $this->isCurrentTeam($team),
        );
    }

    /**
     * Get the standard permissions for a team as a TeamPermissions object.
     */
    public function toTeamPermissions(Team $team): TeamPermissions
    {
        $gate = Gate::forUser($this);

        return new TeamPermissions(
            canUpdateTeam: $gate->allows('update', $team),
            canDeleteTeam: $gate->allows('delete', $team),
            canManageMembers: $this->managesTeam($team),
        );
    }

    /**
     * Get the team the user should fall back to when their current team is unavailable.
     */
    public function fallbackTeam(?Team $excluding = null): ?Team
    {
        $first = fn ($query) => $query
            ->when($excluding, fn ($query) => $query->where('teams.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(teams.name)')
            ->first();

        // Prefer teams the user belongs to, then any other team they can see (administrators).
        return $first($this->teams()) ?? $first($this->visibleTeams());
    }
}

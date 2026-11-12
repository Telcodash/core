<?php

namespace App\Concerns;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin User
 */
trait HasRole
{
    /**
     * Get the user's role.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Scope the query to users holding any of the given roles.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithRole(Builder $query, string ...$roles): void
    {
        $query->whereHas('role', fn (Builder $role) => $role->whereIn('name', $roles));
    }

    /**
     * Assign the given role to the user (does not save).
     */
    public function assignRole(string|Role $role): static
    {
        $role = $role instanceof Role ? $role : Role::named($role);

        $this->role()->associate($role);

        return $this;
    }

    /**
     * Determine if the user holds any of the given roles.
     */
    public function hasRole(string|Role ...$roles): bool
    {
        return $this->role->isAny(...$roles);
    }

    /**
     * Determine if the user's role is at least as privileged as the given role.
     */
    public function hasRoleAtLeast(string|Role $role): bool
    {
        return $this->role->isAtLeast($role);
    }

    public function isAdvisor(): bool
    {
        return $this->hasRole(Role::ADVISOR);
    }

    public function isAdvisorPlus(): bool
    {
        return $this->hasRole(Role::ADVISOR_PLUS);
    }

    public function isManager(): bool
    {
        return $this->hasRole(Role::MANAGER);
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole(Role::ADMINISTRATOR);
    }

    /**
     * Determine if this user may manage the given user.
     *
     * Administrators can manage everyone else. Managers can manage advisors and
     * advisor_plus users who share at least one of their teams.
     */
    public function canManageUser(User $other): bool
    {
        if ($this->is($other) || ! $this->role->canManageRole($other->role)) {
            return false;
        }

        if ($this->isAdministrator()) {
            return true;
        }

        return $this->teams()
            ->whereIn('teams.id', $other->teams()->select('teams.id'))
            ->exists();
    }
}

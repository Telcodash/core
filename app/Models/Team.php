<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 */
#[Fillable(['name', 'slug'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withTimestamps();
    }

    /**
     * Get the managers of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function managers(): BelongsToMany
    {
        return $this->members()->withRole(Role::MANAGER);
    }

    /**
     * Get the advisor-tier (advisor and advisor_plus) members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function advisors(): BelongsToMany
    {
        return $this->members()->withRole(Role::ADVISOR, Role::ADVISOR_PLUS);
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Determine if the team has at least one manager.
     */
    public function hasManager(): bool
    {
        return $this->managers()->exists();
    }

    /**
     * Determine if the given member can be removed from this team without leaving
     * any advisor-tier user without a managed team.
     */
    public function canDetach(User $member): bool
    {
        if (! $member->belongsToTeam($this)) {
            return false;
        }

        // An advisor must keep at least one other team that has a manager.
        if ($member->role->requiresTeamManager()) {
            return $member->hasManagedTeam(excluding: $this);
        }

        // The last manager cannot leave a team that still has advisors who depend on it.
        if ($member->isManager()) {
            $otherManagers = $this->managers()->where('users.id', '!=', $member->id)->exists();

            return $otherManagers || $this->advisorsDependingOnThisTeam()->isEmpty();
        }

        return true;
    }

    /**
     * Determine if the team can be deleted without stranding any advisor.
     */
    public function canBeDeleted(): bool
    {
        return $this->advisorsDependingOnThisTeam()->isEmpty();
    }

    /**
     * Get advisors whose only managed team is this one.
     *
     * @return Collection<int, User>
     */
    public function advisorsDependingOnThisTeam(): Collection
    {
        return $this->advisors()
            ->get()
            ->reject(fn (User $advisor) => $advisor->hasManagedTeam(excluding: $this))
            ->values();
    }

    /**
     * Scope the query to teams that have at least one manager.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithManager(Builder $query): void
    {
        $query->whereHas('members', fn (Builder $member) => $member->withRole(Role::MANAGER));
    }

    /**
     * Scope the query to teams the given user is allowed to see.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role->canViewAllTeams()) {
            return;
        }

        $query->whereHas('members', fn (Builder $member) => $member->whereKey($user->id));
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

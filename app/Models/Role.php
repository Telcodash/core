<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * A user's application-wide role. Roles are reference data seeded by migration and
 * ordered by `level`: advisor < advisor_plus < manager < administrator.
 *
 * @property int $id
 * @property string $name
 * @property string $label
 * @property int $level
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 */
class Role extends Model
{
    public const string ADVISOR = 'advisor';

    public const string ADVISOR_PLUS = 'advisor_plus';

    public const string MANAGER = 'manager';

    public const string ADMINISTRATOR = 'administrator';

    /**
     * Role names ordered from least to most privileged.
     *
     * @var list<string>
     */
    public const array NAMES = [
        self::ADVISOR,
        self::ADVISOR_PLUS,
        self::MANAGER,
        self::ADMINISTRATOR,
    ];

    /**
     * Find a role by its name.
     */
    public static function named(string $name): self
    {
        if (! in_array($name, self::NAMES, true)) {
            throw new InvalidArgumentException("Unknown role [{$name}].");
        }

        return static::query()->where('name', $name)->firstOrFail();
    }

    /**
     * Get all roles ordered from least to most privileged.
     *
     * @return Collection<int, static>
     */
    public static function ordered(): Collection
    {
        return static::query()->orderBy('level')->get();
    }

    /**
     * Get the users that have this role.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Determine if this role matches any of the given role names or models.
     */
    public function isAny(string|self ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->name === ($role instanceof self ? $role->name : $role)) {
                return true;
            }
        }

        return false;
    }

    public function isAdvisor(): bool
    {
        return $this->name === self::ADVISOR;
    }

    public function isAdvisorPlus(): bool
    {
        return $this->name === self::ADVISOR_PLUS;
    }

    public function isManager(): bool
    {
        return $this->name === self::MANAGER;
    }

    public function isAdministrator(): bool
    {
        return $this->name === self::ADMINISTRATOR;
    }

    /**
     * Determine if this role is at least as privileged as the given role.
     */
    public function isAtLeast(string|self $role): bool
    {
        return $this->level >= static::levelOf($role);
    }

    /**
     * Determine if this role is strictly less privileged than the given role.
     */
    public function isBelow(string|self $role): bool
    {
        return $this->level < static::levelOf($role);
    }

    /**
     * Advisor-tier roles must always belong to at least one team that has a manager.
     */
    public function requiresTeamManager(): bool
    {
        return $this->isAny(self::ADVISOR, self::ADVISOR_PLUS);
    }

    /**
     * Determine if users with this role may create new teams.
     */
    public function canCreateTeams(): bool
    {
        return $this->isAny(self::MANAGER, self::ADMINISTRATOR);
    }

    /**
     * Determine if users with this role can see every team, not just their own.
     */
    public function canViewAllTeams(): bool
    {
        return $this->isAdministrator();
    }

    /**
     * Determine if users with this role manage the members of teams they belong to.
     */
    public function canManageTeams(): bool
    {
        return $this->isAny(self::MANAGER, self::ADMINISTRATOR);
    }

    /**
     * Determine if a holder of this role may manage a user holding the given role.
     *
     * This only compares roles; team scoping is applied by {@see User::canManageUser()}.
     */
    public function canManageRole(string|self $role): bool
    {
        $name = $role instanceof self ? $role->name : $role;

        return match ($this->name) {
            self::ADMINISTRATOR => true,
            self::MANAGER => in_array($name, [self::ADVISOR, self::ADVISOR_PLUS], true),
            default => false,
        };
    }

    /**
     * Resolve the level for a role name or model.
     */
    protected static function levelOf(string|self $role): int
    {
        return $role instanceof self ? $role->level : static::named($role)->level;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
        ];
    }
}

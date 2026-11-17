<?php

namespace App\Models;

use App\Enums\Role;
use App\Exceptions\TeamRequiresManagerException;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Role $role
 * @property int|null $team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $team
 */
#[Fillable(['name', 'email', 'password', 'role', 'team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Enforce that every user belongs to a team and that every team keeps at
     * least one manager (manager or above).
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if ($user->team_id === null) {
                throw TeamRequiresManagerException::missingTeam();
            }

            if (! $user->role->isManagerOrAbove() && ! static::teamHasManager($user->team_id)) {
                throw TeamRequiresManagerException::forTeam();
            }
        });

        static::updating(function (User $user) {
            if (! $user->isDirty(['role', 'team_id'])) {
                return;
            }

            if ($user->team_id === null) {
                throw TeamRequiresManagerException::missingTeam();
            }

            $originalRole = $user->getOriginal('role');
            $originalTeamId = $user->getOriginal('team_id');

            $wasManager = $originalRole instanceof Role && $originalRole->isManagerOrAbove();
            $remainsManagerOfSameTeam = $user->role->isManagerOrAbove() && $originalTeamId === $user->team_id;

            // Demoting or moving the user must not leave their old team without a manager.
            if ($wasManager && ! $remainsManagerOfSameTeam
                && ! static::teamHasManager($originalTeamId, excluding: $user->id)) {
                throw TeamRequiresManagerException::forTeam();
            }

            // Moving a non-manager into a team requires that team to have a manager.
            if ($user->team_id !== $originalTeamId
                && ! $user->role->isManagerOrAbove()
                && ! static::teamHasManager($user->team_id)) {
                throw TeamRequiresManagerException::forTeam();
            }
        });

        static::deleting(function (User $user) {
            if ($user->role->isManagerOrAbove()
                && $user->team_id !== null
                && ! static::teamHasManager($user->team_id, excluding: $user->id)) {
                throw TeamRequiresManagerException::forTeam();
            }
        });
    }

    /**
     * Determine if the team has a manager (or above), optionally ignoring a user.
     */
    public static function teamHasManager(int $teamId, ?int $excluding = null): bool
    {
        return static::query()
            ->where('team_id', $teamId)
            ->whereIn('role', [Role::Manager->value, Role::Administrator->value])
            ->when($excluding, fn ($query) => $query->whereKeyNot($excluding))
            ->exists();
    }

    /**
     * Get the team the user belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Determine if the user has one of the given roles.
     */
    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Determine if the user's role is at least as privileged as the given role.
     */
    public function hasRoleAtLeast(Role $role): bool
    {
        return $this->role->isAtLeast($role);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'password' => 'hashed',
            /* @chisel-2fa */
            'two_factor_confirmed_at' => 'datetime',
            /* @end-chisel-2fa */
        ];
    }
}

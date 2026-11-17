<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * By default the user is a manager on a brand-new team, so the team has
     * its required manager.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Manager,
            'team_id' => Team::factory(),
            /* @chisel-2fa */
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            /* @end-chisel-2fa */
        ];
    }

    /**
     * Give the user the specified role.
     *
     * Non-manager roles join a new team that is created with a manager, unless
     * a team is supplied (via `forTeam()` or the `team_id` attribute).
     */
    public function role(Role $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
            // Keep an explicitly chosen team; otherwise a non-manager needs a team that already has a manager.
            'team_id' => $role->isManagerOrAbove() || ! $attributes['team_id'] instanceof Factory
                ? $attributes['team_id']
                : fn () => User::factory()->create()->team_id,
        ]);
    }

    public function advisor(): static
    {
        return $this->role(Role::Advisor);
    }

    public function advisorPlus(): static
    {
        return $this->role(Role::AdvisorPlus);
    }

    public function manager(): static
    {
        return $this->role(Role::Manager);
    }

    public function administrator(): static
    {
        return $this->role(Role::Administrator);
    }

    /**
     * Place the user on an existing team.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => ['team_id' => $team->id]);
    }

    /* @chisel-2fa */
    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
    /* @end-chisel-2fa */
}

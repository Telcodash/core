<?php

namespace Database\Factories;

use App\Models\Role;
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
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => fn () => Role::named(Role::ADVISOR)->id,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Configure the model factory.
     *
     * Every user is placed on a team: the team given via {@see forTeam()} or,
     * by default, a new team of their own.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $team = $user->current_team_id
                ? Team::findOrFail($user->current_team_id)
                : Team::factory()->create(['name' => $user->name."'s Team"]);

            $team->members()->syncWithoutDetaching([$user->id]);

            $user->switchTeam($team);
        });
    }

    /**
     * Place the user on the given team instead of a new one.
     */
    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes) => [
            'current_team_id' => $team->id,
        ]);
    }

    /**
     * Give the user the given role.
     */
    public function withRole(string $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::named($role)->id,
        ]);
    }

    public function advisor(): static
    {
        return $this->withRole(Role::ADVISOR);
    }

    public function advisorPlus(): static
    {
        return $this->withRole(Role::ADVISOR_PLUS);
    }

    public function manager(): static
    {
        return $this->withRole(Role::MANAGER);
    }

    public function administrator(): static
    {
        return $this->withRole(Role::ADMINISTRATOR);
    }

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
}

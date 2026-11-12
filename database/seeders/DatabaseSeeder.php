<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with one user per role.
     * Every account uses the password "password".
     */
    public function run(): void
    {
        $team = Team::factory()->create(['name' => 'Example Team', 'slug' => 'example-team']);

        User::factory()->administrator()->forTeam($team)->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        User::factory()->manager()->forTeam($team)->create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
        ]);

        User::factory()->advisorPlus()->forTeam($team)->create([
            'name' => 'Advisor Plus User',
            'email' => 'advisor-plus@example.com',
        ]);

        User::factory()->advisor()->forTeam($team)->create([
            'name' => 'Advisor User',
            'email' => 'advisor@example.com',
        ]);
    }
}

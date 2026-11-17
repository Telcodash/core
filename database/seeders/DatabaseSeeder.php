<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $manager = User::factory()->manager()->create([
            'name' => 'Test Manager',
            'email' => 'test@example.com',
        ]);

        User::factory()->advisor()->forTeam($manager->team)->create([
            'name' => 'Test Advisor',
            'email' => 'advisor@example.com',
        ]);
    }
}

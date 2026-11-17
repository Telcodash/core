<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $user = User::factory()->create();

        $this->get(route('dashboard', ['current_team' => $user->team->slug]))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_their_team_dashboard()
    {
        $user = User::factory()->advisor()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['current_team' => $user->team->slug]))
            ->assertOk();
    }

    public function test_users_cannot_visit_another_teams_dashboard()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['current_team' => $other->team->slug]))
            ->assertForbidden();
    }
}

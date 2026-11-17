<?php

namespace Tests\Feature\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_hierarchy()
    {
        $this->assertTrue(Role::Administrator->isAtLeast(Role::Manager));
        $this->assertTrue(Role::Manager->isAtLeast(Role::AdvisorPlus));
        $this->assertFalse(Role::Advisor->isAtLeast(Role::AdvisorPlus));
        $this->assertFalse(Role::AdvisorPlus->isManagerOrAbove());
        $this->assertTrue(Role::Manager->isManagerOrAbove());
    }

    public function test_role_middleware_gates_routes_by_minimum_role()
    {
        Route::middleware(['web', 'auth', 'role:manager'])->get('/_test/managers', fn () => 'ok');

        $manager = User::factory()->create();
        $advisor = User::factory()->advisor()->create();
        $admin = User::factory()->administrator()->create();

        $this->actingAs($advisor)->get('/_test/managers')->assertForbidden();
        $this->actingAs($manager)->get('/_test/managers')->assertOk();
        $this->actingAs($admin)->get('/_test/managers')->assertOk();
    }
}

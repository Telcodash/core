<?php

use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TeamMemberController as AdminTeamMemberController;
use App\Http\Controllers\Admin\TeamSearchController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Team\TeamMemberController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'role:administrator'])->prefix('admin/teams')->name('teams.')->group(function () {
    Route::get('/', [TeamSearchController::class, 'index'])->name('index');
    Route::post('/', [TeamController::class, 'store'])->name('store');
    Route::get('{team}', [AdminTeamMemberController::class, 'show'])->name('show');
    Route::patch('{team}', [TeamController::class, 'update'])->name('update');
    Route::delete('{team}', [TeamController::class, 'destroy'])->name('destroy');

    Route::post('{team}/members', [AdminTeamMemberController::class, 'store'])->name('members.store');
    Route::patch('{team}/members/{member}', [AdminTeamMemberController::class, 'update'])->name('members.update');
    Route::delete('{team}/members/{member}', [AdminTeamMemberController::class, 'destroy'])->name('members.destroy');
});

Route::middleware(['auth', 'role:administrator'])->prefix('admin/users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::patch('{user}', [UserController::class, 'update'])->name('update');
    Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
});

Route::prefix('{current_team}')
    ->middleware(['auth', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('team', [TeamMemberController::class, 'index'])->name('team.index');

        Route::patch('team/members/{member}', [TeamMemberController::class, 'update'])
            ->middleware('role:manager')
            ->name('team.members.update');

        Route::middleware('role:administrator')->group(function () {
            Route::post('team/members', [TeamMemberController::class, 'store'])->name('team.members.store');
            Route::delete('team/members/{member}', [TeamMemberController::class, 'destroy'])->name('team.members.destroy');
        });
    });

require __DIR__.'/settings.php';

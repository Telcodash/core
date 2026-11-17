<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Team\TeamMemberController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

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

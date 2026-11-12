<?php

use App\Http\Controllers\RedirectToCurrentTeamController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Team-independent entry point: redirects to the current team's dashboard (or the teams page).
Route::get('app', RedirectToCurrentTeamController::class)
    ->middleware('auth')
    ->name('app.home');

Route::prefix('{current_team}')
    ->middleware(['auth', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';

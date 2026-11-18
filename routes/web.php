<?php

use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\TariffController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TeamMemberController as AdminTeamMemberController;
use App\Http\Controllers\Admin\TeamSearchController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NumberUsageController;
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

Route::middleware(['auth', 'role:administrator'])->prefix('admin/tariffs')->name('tariffs.')->group(function () {
    Route::get('/', [TariffController::class, 'index'])->name('index');
    Route::post('/', [TariffController::class, 'store'])->name('store');
    Route::patch('{tariff}', [TariffController::class, 'update'])
        ->where('tariff', 'trf_[0-9A-HJKMNP-TV-Z]{26}')
        ->name('update');
});

Route::middleware(['auth', 'role:administrator'])->prefix('admin/services')->name('services.')->group(function () {
    Route::get('/', [ServiceController::class, 'index'])->name('index');
    Route::post('queue/test', [ServiceController::class, 'testQueue'])->middleware('throttle:10,1')->name('queue.test');
    Route::get('queue/tasks/{task}', [ServiceController::class, 'task'])->name('queue.task');
});

Route::prefix('{current_team}')
    ->middleware(['auth', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::where(['customer' => 'cus_[0-9A-HJKMNP-TV-Z]{26}'])->group(function () {
            Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
            Route::get('customers/{customer}/details', [CustomerController::class, 'details'])->name('customers.details');
            Route::get('customers/{customer}/numbers', [CustomerController::class, 'numbers'])->name('customers.numbers');
            Route::get('customers/{customer}/billing', [CustomerController::class, 'billing'])->name('customers.billing');
        });

        Route::get('customers/{customer}/numbers/{msisdn}/usage', [NumberUsageController::class, 'show'])
            ->where(['customer' => 'cus_[0-9A-HJKMNP-TV-Z]{26}', 'msisdn' => '447\d{9}'])
            ->name('customers.usage');

        Route::patch('customers/{customer}/numbers/{msisdn}', [CustomerController::class, 'renameNumber'])
            ->where(['customer' => 'cus_[0-9A-HJKMNP-TV-Z]{26}', 'msisdn' => '447\d{9}'])
            ->middleware('throttle:30,1')
            ->name('customers.rename-number');

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

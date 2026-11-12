<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::livewire('/', 'pages::admin.overview')->name('overview');

        Route::livewire('teams', 'pages::admin.teams.index')->name('teams.index');
        Route::livewire('teams/create', 'pages::admin.teams.create')->name('teams.create');
        Route::livewire('teams/{team}', 'pages::admin.teams.edit')->name('teams.edit');

        Route::livewire('users', 'pages::admin.users.index')->name('users.index');
        Route::livewire('users/create', 'pages::admin.users.create')->name('users.create');
        Route::livewire('users/{user}', 'pages::admin.users.edit')->name('users.edit');
    });

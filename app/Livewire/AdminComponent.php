<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Base class for admin-area components. Restricts the component to administrators on
 * every request (initial render and every subsequent update), independent of route
 * middleware. Subclasses overriding boot() must call parent::boot().
 */
abstract class AdminComponent extends Component
{
    public function boot(): void
    {
        Gate::authorize('access-admin');
    }
}

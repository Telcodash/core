<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Restrict a route to users with at least the given role.
     *
     * Usage: ->middleware('role:manager')
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $minimumRole): Response
    {
        $required = Role::tryFrom($minimumRole);

        abort_if(
            $required === null || ! $request->user()?->hasRoleAtLeast($required),
            403,
        );

        return $next($request);
    }
}

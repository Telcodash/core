<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamMembership
{
    /**
     * Ensure the user can access the team in the route, optionally requiring a minimum role
     * (e.g. `EnsureTeamMembership::class.':manager'`). Administrators can access every team.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $minimumRole = null): Response
    {
        [$user, $team] = [$request->user(), $this->team($request)];

        abort_if(! $user || ! $team || ! $user->canAccessTeam($team), 403);

        $this->ensureUserHasRequiredRole($user, $minimumRole);

        if ($request->route('current_team') && ! $user->isCurrentTeam($team)) {
            $user->switchTeam($team);
        }

        return $next($request);
    }

    /**
     * Ensure the given user has at least the given role, if applicable.
     */
    protected function ensureUserHasRequiredRole(User $user, ?string $minimumRole): void
    {
        if ($minimumRole === null) {
            return;
        }

        abort_unless(in_array($minimumRole, Role::NAMES, true) && $user->hasRoleAtLeast($minimumRole), 403);
    }

    /**
     * Get the team associated with the request.
     */
    protected function team(Request $request): ?Team
    {
        $team = $request->route('current_team') ?? $request->route('team');

        if (is_string($team)) {
            $team = Team::where('slug', $team)->first();
        }

        return $team;
    }
}

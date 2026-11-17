<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamMembership
{
    /**
     * Ensure the user belongs to the team in the route, optionally with at least the given role.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $minimumRole = null): Response
    {
        $user = $request->user();
        $team = $this->team($request);

        abort_if(! $user || ! $team || $user->team_id !== $team->id, 403);

        if ($minimumRole !== null) {
            $required = Role::tryFrom($minimumRole);

            abort_if($required === null || ! $user->hasRoleAtLeast($required), 403);
        }

        return $next($request);
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

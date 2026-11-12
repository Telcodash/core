<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam(Request $request, string $redirect): string
    {
        $user = $request->user();

        abort_if(! $user, 403);

        $team = $user->currentTeam && $user->canAccessTeam($user->currentTeam)
            ? $user->currentTeam
            : $user->fallbackTeam();

        // Users without any team (e.g. an administrator before teams exist) land on the teams page.
        if (! $team) {
            return route('teams.index', absolute: false);
        }

        if (! $user->isCurrentTeam($team)) {
            $user->switchTeam($team);
        }

        URL::defaults(['current_team' => $team->slug]);

        return "/{$team->slug}{$redirect}";
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Team\TeamMemberController as BaseTeamMemberController;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Member management for any team (administrators only). Reuses the "My Team"
 * logic; the team comes from the `{team}` route parameter.
 */
class TeamMemberController extends BaseTeamMemberController
{
    /**
     * Show any team and its members.
     */
    public function show(Request $request, Team $team): Response
    {
        return $this->render($request, $team, 'teams/show');
    }
}

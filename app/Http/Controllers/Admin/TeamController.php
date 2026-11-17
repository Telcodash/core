<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\CreateUser;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTeamRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamController extends Controller
{
    /**
     * Create a team together with its first manager.
     */
    public function store(SaveTeamRequest $request, CreateUser $createUser): RedirectResponse
    {
        $data = $request->validated();

        $manager = $createUser->handle(
            name: $data['manager_name'],
            email: $data['manager_email'],
            password: $data['manager_password'],
            role: Role::from($data['manager_role']),
            newTeamName: $data['name'],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team created.')]);

        return to_route('teams.show', ['team' => $manager->team->slug]);
    }

    /**
     * Rename a team.
     */
    public function update(SaveTeamRequest $request, Team $team): RedirectResponse
    {
        $team->update(['name' => $request->validated('name')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team updated.')]);

        return to_route('teams.show', ['team' => $team->slug]);
    }

    /**
     * Delete a team and every account on it.
     */
    public function destroy(Request $request, Team $team): RedirectResponse
    {
        abort_if($team->id === $request->user()->team_id, 403, __('You cannot delete your own team.'));

        DB::transaction(function () use ($team) {
            // Bulk delete: the whole team goes, so the "last manager" guard does not apply.
            $team->members()->delete();
            $team->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team deleted.')]);

        return to_route('teams.index');
    }
}

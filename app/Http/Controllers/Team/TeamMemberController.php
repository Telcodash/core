<?php

namespace App\Http\Controllers\Team;

use App\Actions\Users\CreateUser;
use App\Enums\Role;
use App\Exceptions\TeamRequiresManagerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\SaveTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeamMemberController extends Controller
{
    // Note: `string $teamSlug` on store/update/destroy only receives the team route parameter
    // ("{current_team}" or "{team}") so later arguments line up; the team is resolved by team().

    /**
     * Show the team and its members (visible to every role).
     */
    public function index(Request $request, Team $current_team): Response
    {
        return $this->render($request, $current_team, 'team/index');
    }

    /**
     * Build the members page for the given team and Inertia component.
     */
    protected function render(Request $request, Team $current_team, string $component): Response
    {
        $user = $request->user();
        $isAdmin = $user->hasRole(Role::Administrator);
        $assignable = $isAdmin ? Role::cases() : ($user->hasRole(Role::Manager) ? Role::managerAssignable() : []);

        return Inertia::render($component, [
            'team' => ['id' => $current_team->id, 'name' => $current_team->name, 'slug' => $current_team->slug],
            'members' => $current_team->members()
                ->orderBy('name')
                ->get()
                ->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->role->value,
                    'role_label' => $member->role->label(),
                    'editable' => $isAdmin
                        || ($user->hasRole(Role::Manager) && in_array($member->role, Role::managerAssignable(), true)),
                ]),
            'roles' => collect($assignable)->map(fn (Role $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->values(),
            'canManage' => $isAdmin,
        ]);
    }

    /**
     * Add a member to the team (administrators only).
     */
    public function store(SaveTeamMemberRequest $request, CreateUser $createUser, string $teamSlug): RedirectResponse
    {
        $current_team = $this->team($request);
        $data = $request->validated();

        try {
            $createUser->handle(
                name: $data['name'],
                email: $data['email'],
                password: $data['password'],
                role: Role::from($data['role']),
                team: $current_team,
            );
        } catch (TeamRequiresManagerException $e) {
            throw ValidationException::withMessages(['role' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member added.')]);

        return back();
    }

    /**
     * Update a team member (administrators; managers for advisor / advisor plus only).
     */
    public function update(SaveTeamMemberRequest $request, string $teamSlug, User $member): RedirectResponse
    {
        $current_team = $this->team($request);
        abort_unless($member->team_id === $current_team->id, 404);

        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        try {
            $member->update($data);
        } catch (TeamRequiresManagerException $e) {
            throw ValidationException::withMessages(['role' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member updated.')]);

        return back();
    }

    /**
     * Remove a member from the team and delete their account (administrators only).
     */
    public function destroy(Request $request, string $teamSlug, User $member): RedirectResponse
    {
        $current_team = $this->team($request);
        abort_unless($member->team_id === $current_team->id, 404);
        abort_if($member->is($request->user()), 403, __('You cannot delete your own account here.'));

        try {
            $member->delete();
        } catch (TeamRequiresManagerException $e) {
            throw ValidationException::withMessages(['member' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member deleted.')]);

        return back();
    }

    /**
     * Resolve the team from the route ("{current_team}" for My Team, "{team}" for admin routes).
     */
    protected function team(Request $request): Team
    {
        $team = $request->route('team') ?? $request->route('current_team');

        return $team instanceof Team ? $team : Team::where('slug', $team)->firstOrFail();
    }
}

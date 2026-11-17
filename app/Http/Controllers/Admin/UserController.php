<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\CreateUser;
use App\Enums\Role;
use App\Exceptions\TeamRequiresManagerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * List and search every user by name, email or team name (administrators only).
     */
    public function index(Request $request): Response
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $search = $request->string('q')->trim()->toString();
        $like = '%'.addcslashes(mb_strtolower($search), '\\%_').'%';

        $users = User::query()
            ->with('team')
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($like) {
                $query->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereHas('team', fn (Builder $team) => $team->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$like]));
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'team' => $user->team ? ['id' => $user->team->id, 'name' => $user->team->name, 'slug' => $user->team->slug] : null,
                'is_self' => $user->is($request->user()),
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'search' => $search,
            'roles' => collect(Role::cases())->map(fn (Role $role) => ['value' => $role->value, 'label' => $role->label()])->values(),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Create a user on an existing team, or on a new team (manager or above).
     */
    public function store(SaveUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        $data = $request->validated();

        try {
            $createUser->handle(
                name: $data['name'],
                email: $data['email'],
                password: $data['password'],
                role: Role::from($data['role']),
                team: isset($data['team_id']) ? Team::whereKey($data['team_id'])->firstOrFail() : null,
                newTeamName: $data['new_team_name'] ?? null,
            );
        } catch (TeamRequiresManagerException $e) {
            throw ValidationException::withMessages([isset($data['team_id']) ? 'team_id' : 'role' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return back();
    }

    /**
     * Update a user, including moving them to another team.
     */
    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Prevent an administrator locking themselves out of the admin area.
        if ($user->is($request->user()) && $data['role'] !== $user->role->value) {
            throw ValidationException::withMessages(['role' => __('You cannot change your own role.')]);
        }

        try {
            $user->update($data);
        } catch (TeamRequiresManagerException $e) {
            throw ValidationException::withMessages([
                (int) $data['team_id'] !== $user->getOriginal('team_id') ? 'team_id' : 'role' => $e->getMessage(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return back();
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, __('You cannot delete your own account here.'));

        try {
            $user->delete();
        } catch (TeamRequiresManagerException $e) {
            throw ValidationException::withMessages(['user' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return back();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamSearchController extends Controller
{
    /**
     * Search every team by team name, or by member name / email (administrators only).
     */
    public function index(Request $request): Response
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $search = $request->string('q')->trim()->toString();
        $needle = mb_strtolower($search);
        $like = '%'.addcslashes($needle, '\\%_').'%';

        $teams = Team::query()
            ->with(['members' => fn ($query) => $query->orderBy('name')])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($like) {
                $query->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$like])
                    ->orWhereHas('members', fn (Builder $members) => $members
                        ->whereRaw("LOWER(email) LIKE ? ESCAPE '\\'", [$like])
                        ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$like]));
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'members' => $team->members->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->role->value,
                    'role_label' => $member->role->label(),
                    'matched' => $needle !== ''
                        && (str_contains(mb_strtolower($member->email), $needle)
                            || str_contains(mb_strtolower($member->name), $needle)),
                ])->values(),
            ]);

        return Inertia::render('teams/index', [
            'teams' => $teams,
            'search' => $search,
        ]);
    }
}

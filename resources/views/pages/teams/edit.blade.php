<?php

use App\Data\TeamPermissions;
use App\Models\Team;
use App\Models\User;
use App\Rules\TeamName;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Team $teamModel;

    public string $teamName = '';

    public array $teamData = [];

    public array $members = [];

    public bool $isCurrentTeam = false;

    public function mount(Team $team): void
    {
        Gate::authorize('view', $team);

        $this->teamModel = $team;
        $this->teamName = $team->name;

        $this->populateTeamData();
    }

    public function updateTeam(): void
    {
        Gate::authorize('update', $this->teamModel);

        $validated = $this->validate([
            'teamName' => ['required', 'string', 'max:255', new TeamName],
        ]);

        $team = DB::transaction(function () use ($validated) {
            $team = Team::whereKey($this->teamModel->id)->lockForUpdate()->firstOrFail();

            $team->update(['name' => $validated['teamName']]);

            return $team;
        });

        $this->teamModel = $team;

        $this->populateTeamData();

        Flux::toast(variant: 'success', text: __('Team updated.'));

        $this->redirectRoute('teams.edit', ['team' => $this->teamModel->fresh()->slug], navigate: true);
    }

    private function populateTeamData(): void
    {
        $user = Auth::user();

        $team = $this->teamModel->fresh();

        $this->teamData = [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
        ];

        $this->members = $team->members()
            ->with('role')
            ->get()
            ->sortBy([
                fn (User $a, User $b) => $b->role->level <=> $a->role->level,
                fn (User $a, User $b) => strcasecmp($a->name, $b->name),
            ])
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'avatar' => $member->avatar ?? null,
                'initials' => $member->initials(),
                'role' => $member->role->name,
                'role_label' => $member->role->label,
                'can_remove' => $user->can('removeMember', [$team, $member]),
            ])
            ->values()
            ->toArray();

        $this->isCurrentTeam = $user->isCurrentTeam($team);
    }

    public function render()
    {
        $teamName = $this->teamData['name'] ?? $this->teamModel->name;

        $title = $this->permissions->canUpdateTeam
            ? __('Edit :name', ['name' => $teamName])
            : __('View :name', ['name' => $teamName]);

        return $this->view()->title($title);
    }

    #[Computed]
    public function permissions(): TeamPermissions
    {
        return Auth::user()->toTeamPermissions($this->teamModel);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Teams') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Teams')" :subheading="__('Manage your team settings')">
        <div class="space-y-10">
            <div class="space-y-6">
                @if ($this->permissions->canUpdateTeam)
                    <div class="space-y-4">
                        <form wire:submit="updateTeam" class="space-y-6">
                            <flux:input wire:model="teamName" :label="__('Team name')" required data-test="team-name-input" />

                            <flux:button variant="primary" type="submit" data-test="team-save-button">
                                {{ __('Save') }}
                            </flux:button>
                        </form>
                    </div>
                @else
                    <div>
                        <flux:heading>{{ $teamData['name'] }}</flux:heading>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div>
                    <flux:heading>{{ __('Team members') }}</flux:heading>
                    @if ($this->permissions->canManageMembers)
                        <flux:subheading>{{ __('Manage who belongs to this team') }}</flux:subheading>
                    @endif
                </div>

                <div class="space-y-3">
                    @forelse ($members as $member)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="member-row">
                            <div class="flex items-center gap-4">
                                <flux:avatar :name="$member['name']" :initials="$member['initials']" />
                                <div>
                                    <div class="font-medium">{{ $member['name'] }}</div>
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $member['email'] }}</flux:text>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:badge color="zinc" data-test="member-role">{{ $member['role_label'] }}</flux:badge>

                                @if ($member['can_remove'])
                                    <flux:modal.trigger name="remove-member-{{ $member['id'] }}">
                                        <flux:tooltip :content="__('Remove member')">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="x-mark"
                                                data-test="member-remove-button"
                                            />
                                        </flux:tooltip>
                                    </flux:modal.trigger>
                                @endif
                            </div>
                        </div>

                        @if ($member['can_remove'])
                            <livewire:pages::teams.remove-member-modal
                                :team="$teamModel"
                                :member-id="$member['id']"
                                :member-name="$member['name']"
                                :modal-name="'remove-member-'.$member['id']"
                                :key="'remove-member-modal-'.$member['id']"
                            />
                        @endif
                    @empty
                        <flux:text class="py-4 text-zinc-500 dark:text-zinc-400">
                            {{ __('This team has no members yet.') }}
                        </flux:text>
                    @endforelse
                </div>
            </div>

            @if ($this->permissions->canDeleteTeam)
                <div class="space-y-6">
                    <div>
                        <flux:heading>{{ __('Delete team') }}</flux:heading>
                        <flux:subheading>{{ __('Permanently delete this team') }}</flux:subheading>
                    </div>

                    <div class="space-y-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700 dark:border-red-200/10 dark:bg-red-900/20 dark:text-red-100">
                        <div>
                            <p class="font-medium">{{ __('Warning') }}</p>
                            <p class="text-sm">{{ __('Please proceed with caution, this cannot be undone.') }}</p>
                        </div>

                        <flux:modal.trigger name="delete-team">
                            <flux:button variant="danger" data-test="delete-team-button">
                                {{ __('Delete team') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>
            @endif
        </div>
    </x-pages::settings.layout>

    @if ($this->permissions->canDeleteTeam)
        <livewire:pages::teams.delete-team-modal :team="$teamModel" />
    @endif
</section>

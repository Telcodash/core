<?php

use App\Actions\Teams\AddTeamMember;
use App\Actions\Teams\DeleteTeam;
use App\Actions\Teams\RemoveTeamMember;
use App\Livewire\AdminComponent;
use App\Models\Team;
use App\Models\User;
use App\Rules\TeamName;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

new #[Layout('layouts::admin')] class extends AdminComponent {
    public Team $team;

    public string $name = '';

    public string $newMemberId = '';

    public function mount(Team $team): void
    {
        $this->team = $team;
        $this->name = $team->name;
    }

    public function render()
    {
        return $this->view()->title(__('Edit :name', ['name' => $this->team->name]));
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function members(): Collection
    {
        return $this->team->members()
            ->with('role')
            ->get()
            ->sortBy([
                fn (User $a, User $b) => $b->role->level <=> $a->role->level,
                fn (User $a, User $b) => strcasecmp($a->name, $b->name),
            ])
            ->values();
    }

    /**
     * Users who can be added to this team.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function availableUsers(): Collection
    {
        return User::query()
            ->whereDoesntHave('teams', fn ($query) => $query->whereKey($this->team->id))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function needsManager(): bool
    {
        return ! $this->team->hasManager() && $this->team->advisors()->exists();
    }

    public function rename(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', new TeamName],
        ]);

        $this->team->update(['name' => $validated['name']]);

        Flux::toast(variant: 'success', text: __('Team renamed.'));

        // The slug changes with the name, so move to the new URL.
        $this->redirectRoute('admin.teams.edit', $this->team->fresh(), navigate: true);
    }

    public function addMember(AddTeamMember $addTeamMember): void
    {
        $this->validate(['newMemberId' => ['required', 'integer', 'exists:users,id']], [], ['newMemberId' => __('user')]);

        $user = User::findOrFail($this->newMemberId);

        $addTeamMember->handle($this->team, $user);

        $this->reset('newMemberId');
        unset($this->members, $this->availableUsers, $this->needsManager);

        Flux::toast(variant: 'success', text: __(':name added to the team.', ['name' => $user->name]));
    }

    public function removeMember(int $userId, RemoveTeamMember $removeTeamMember): void
    {
        $user = $this->team->members()->whereKey($userId)->firstOrFail();

        try {
            $removeTeamMember->handle($this->team, $user);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        unset($this->members, $this->availableUsers, $this->needsManager);

        Flux::toast(variant: 'success', text: __(':name removed from the team.', ['name' => $user->name]));
    }

    public function deleteTeam(DeleteTeam $deleteTeam): void
    {
        try {
            $deleteTeam->handle($this->team);
        } catch (ValidationException $e) {
            Flux::modal('delete-team')->close();
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Team ":name" deleted.', ['name' => $this->team->name]));

        $this->redirectRoute('admin.teams.index', navigate: true);
    }
}; ?>

<section class="w-full">
    <x-admin.page-heading :heading="$team->name" :subheading="__('Manage team details and members')">
        <x-slot:actions>
            <flux:button variant="ghost" icon="arrow-left" :href="route('admin.teams.index')" wire:navigate>{{ __('All teams') }}</flux:button>
        </x-slot:actions>
    </x-admin.page-heading>

    @if ($this->needsManager)
        <flux:callout variant="danger" icon="exclamation-triangle" class="mb-10" :heading="__('This team has advisors but no manager.')" data-test="admin-team-needs-manager">
            <flux:callout.text>{{ __('Add a manager so its advisors are managed.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div data-rise>
        <x-form-section :heading="__('Details')" :description="__('The team name is shown in the team switcher and on the dashboard.')">
            <form wire:submit="rename" class="space-y-5">
                <flux:input wire:model="name" :label="__('Team name')" required data-test="admin-team-name" />
                <flux:button variant="primary" type="submit" data-test="admin-team-save">{{ __('Save') }}</flux:button>
            </form>
        </x-form-section>

        <x-form-section :heading="__('Members')" :description="__('Advisors must always belong to at least one team with a manager.')">
            <form wire:submit="addMember" class="flex items-end gap-2">
                <div class="flex-1">
                    <flux:select wire:model="newMemberId" :label="__('Add a member')" :placeholder="__('Choose a user...')" data-test="admin-team-add-member-select">
                        @foreach ($this->availableUsers as $user)
                            <flux:select.option :value="$user->id">{{ $user->name }} ({{ $user->role->label }})</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:button type="submit" icon="plus" data-test="admin-team-add-member">{{ __('Add') }}</flux:button>
            </form>
            <flux:error name="newMemberId" />

            <ul role="list" class="mt-6 border-t border-zinc-200 dark:border-white/9">
                @forelse ($this->members as $member)
                    <li wire:key="member-{{ $member->id }}" class="flex items-center gap-3 border-b border-zinc-200 py-3 dark:border-white/9" data-test="admin-team-member-row">
                        <flux:avatar size="sm" :name="$member->name" :initials="$member->initials()" color="auto" circle />
                        <div class="min-w-0 flex-1">
                            <flux:link :href="route('admin.users.edit', $member)" wire:navigate class="text-sm font-medium">{{ $member->name }}</flux:link>
                            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $member->email }}</p>
                        </div>
                        <flux:badge size="sm" :color="$member->role->badgeColor()">{{ $member->role->label }}</flux:badge>
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="x-mark"
                            wire:click="removeMember({{ $member->id }})"
                            wire:confirm="{{ __('Remove :name from this team?', ['name' => $member->name]) }}"
                            :tooltip="__('Remove from team')"
                            data-test="admin-team-remove-member"
                        />
                    </li>
                @empty
                    <li class="py-6">
                        <x-empty-state icon="user-plus" :heading="__('This team has no members yet.')" :description="__('Add a manager first, then the advisors they look after.')" />
                    </li>
                @endforelse
            </ul>
        </x-form-section>

        <x-form-section :heading="__('Delete team')" :description="__('Members are removed from the team. Advisors who depend on it must be moved first.')" danger>
            <flux:modal.trigger name="delete-team">
                <flux:button variant="danger" icon="trash" data-test="admin-delete-team-button">{{ __('Delete team') }}</flux:button>
            </flux:modal.trigger>
        </x-form-section>
    </div>

    <flux:modal name="delete-team" class="max-w-lg">
        <form wire:submit="deleteTeam" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete team?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('":name" will be permanently deleted. This cannot be undone.', ['name' => $team->name]) }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit" data-test="admin-delete-team-confirm">{{ __('Delete team') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

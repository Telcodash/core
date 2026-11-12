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

    <div class="max-w-3xl space-y-10">
        @if ($this->needsManager)
            <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('This team has advisors but no manager.')" data-test="admin-team-needs-manager">
                <flux:callout.text>{{ __('Add a manager so its advisors are managed.') }}</flux:callout.text>
            </flux:callout>
        @endif

        <form wire:submit="rename" class="max-w-lg space-y-4">
            <flux:heading>{{ __('Details') }}</flux:heading>
            <flux:input wire:model="name" :label="__('Team name')" required data-test="admin-team-name" />
            <flux:button variant="primary" type="submit" data-test="admin-team-save">{{ __('Save') }}</flux:button>
        </form>

        <div class="space-y-4">
            <div>
                <flux:heading>{{ __('Members') }}</flux:heading>
                <flux:subheading>{{ __('Advisors must always belong to at least one team with a manager.') }}</flux:subheading>
            </div>

            <form wire:submit="addMember" class="flex max-w-lg items-end gap-2">
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

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->members as $member)
                        <flux:table.row :key="$member->id" data-test="admin-team-member-row">
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('admin.users.edit', $member)" wire:navigate>{{ $member->name }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $member->email }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$member->role->badgeColor()">{{ $member->role->label }}</flux:badge></flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="x-mark"
                                    wire:click="removeMember({{ $member->id }})"
                                    wire:confirm="{{ __('Remove :name from this team?', ['name' => $member->name]) }}"
                                    :tooltip="__('Remove from team')"
                                    data-test="admin-team-remove-member"
                                />
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center text-zinc-500">{{ __('This team has no members yet.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="space-y-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-900/20">
            <div>
                <flux:heading class="text-red-700! dark:text-red-100!">{{ __('Delete team') }}</flux:heading>
                <flux:text class="text-red-700 dark:text-red-100">{{ __('Members are removed from the team. Advisors who depend on it must be moved first.') }}</flux:text>
            </div>
            <flux:modal.trigger name="delete-team">
                <flux:button variant="danger" data-test="admin-delete-team-button">{{ __('Delete team') }}</flux:button>
            </flux:modal.trigger>
        </div>
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

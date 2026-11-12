<?php

use App\Actions\Users\DeleteUser;
use App\Actions\Users\SyncUserTeams;
use App\Actions\Users\UpdateUser;
use App\Livewire\AdminComponent;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;

new #[Layout('layouts::admin')] class extends AdminComponent {
    public User $user;

    public string $name = '';

    public string $email = '';

    public string $role = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** @var list<string> IDs of the teams the user belongs to (staged until saved). */
    public array $teams = [];

    public string $teamSearch = '';

    /** The assigned team being swapped for a search result, if any. */
    public ?int $replacingTeamId = null;

    public function mount(User $user): void
    {
        $this->user = $user;

        $this->fillFromUser();
    }

    public function render()
    {
        return $this->view()->title(__('Edit :name', ['name' => $this->user->name]));
    }

    #[Computed]
    public function isSelf(): bool
    {
        return Auth::user()->is($this->user);
    }

    /**
     * @return Collection<int, Role>
     */
    #[Computed]
    public function roles(): Collection
    {
        return Role::ordered();
    }

    /**
     * The teams currently staged for this user.
     *
     * @return Collection<int, Team>
     */
    #[Computed]
    public function assignedTeams(): Collection
    {
        return Team::query()->with('managers')->whereKey($this->teams)->orderBy('name')->get();
    }

    /**
     * Teams matching the search that the user is not already on.
     *
     * @return Collection<int, Team>
     */
    #[Computed]
    public function teamResults(): Collection
    {
        $search = trim($this->teamSearch);

        if ($search === '') {
            return new Collection;
        }

        return Team::query()
            ->with('managers')
            ->whereKeyNot($this->teams)
            ->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')
            ->orderBy('name')
            ->limit(6)
            ->get();
    }

    #[Computed]
    public function hasAnyTeams(): bool
    {
        return Team::query()->exists();
    }

    /**
     * Whether the staged teams differ from what is saved.
     */
    #[Computed]
    public function teamsChanged(): bool
    {
        $saved = $this->user->teams()->pluck('teams.id')->map(fn ($id) => (string) $id)->sort()->values()->all();

        return collect($this->teams)->sort()->values()->all() !== $saved;
    }

    public function addTeam(int $teamId): void
    {
        if (! Team::query()->whereKey($teamId)->exists()) {
            return;
        }

        if ($this->replacingTeamId !== null) {
            $this->teams = array_values(array_diff($this->teams, [(string) $this->replacingTeamId]));
        }

        $this->teams = array_values(array_unique([...$this->teams, (string) $teamId]));

        $this->reset('teamSearch', 'replacingTeamId');
        $this->forgetTeamState();
    }

    public function removeTeam(int $teamId): void
    {
        $this->teams = array_values(array_diff($this->teams, [(string) $teamId]));

        if ($this->replacingTeamId === $teamId) {
            $this->reset('replacingTeamId');
        }

        $this->forgetTeamState();
    }

    public function startChangingTeam(int $teamId): void
    {
        $this->replacingTeamId = $teamId;
        $this->reset('teamSearch');

        $this->js("document.querySelector('[data-test=\"admin-user-team-search\"] input, input[data-test=\"admin-user-team-search\"]')?.focus()");
    }

    public function cancelChangingTeam(): void
    {
        $this->reset('replacingTeamId', 'teamSearch');
    }

    public function discardTeamChanges(): void
    {
        $this->reset('teamSearch', 'replacingTeamId');
        $this->fillFromUser();
        $this->forgetTeamState();
    }

    public function updateDetails(UpdateUser $updateUser): void
    {
        if ($this->isSelf && $this->role !== $this->user->role->name) {
            $this->addError('role', __('You cannot change your own role.'));

            return;
        }

        try {
            $updateUser->handle($this->user, [
                'name' => $this->name,
                'email' => $this->email,
                'role' => $this->role,
            ]);
        } catch (ValidationException $e) {
            // The change was rolled back; discard the unsaved values on the model (keep the form input).
            $this->user = $this->user->fresh();

            throw $e;
        }

        $this->refreshUser();

        Flux::toast(variant: 'success', text: __('User details saved.'));
    }

    public function updatePassword(): void
    {
        $validated = $this->validate([
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        $this->user->update(['password' => $validated['password']]);

        $this->reset('password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }

    public function updateTeams(SyncUserTeams $syncUserTeams): void
    {
        $this->validate([
            'teams' => ['array'],
            'teams.*' => ['integer', 'exists:teams,id'],
        ]);

        $syncUserTeams->handle($this->user, $this->teams);

        $this->refreshUser();

        Flux::toast(variant: 'success', text: __('Team memberships saved.'));
    }

    public function resetTwoFactor(DisableTwoFactorAuthentication $disableTwoFactor): void
    {
        $disableTwoFactor($this->user);

        $this->refreshUser();

        Flux::toast(variant: 'success', text: __('Two-factor authentication has been reset.'));
    }

    public function removePasskeys(): void
    {
        $this->user->passkeys()->delete();

        $this->refreshUser();

        Flux::toast(variant: 'success', text: __('Passkeys removed.'));
    }

    public function deleteUser(DeleteUser $deleteUser): void
    {
        Flux::modal('delete-user')->close();

        if ($this->isSelf) {
            Flux::toast(variant: 'danger', text: __('You cannot delete your own account from here.'));

            return;
        }

        try {
            $deleteUser->handle($this->user);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('User ":name" deleted.', ['name' => $this->user->name]));

        $this->redirectRoute('admin.users.index', navigate: true);
    }

    private function refreshUser(): void
    {
        $this->user = $this->user->fresh();

        $this->reset('teamSearch', 'replacingTeamId');

        $this->fillFromUser();
    }

    private function forgetTeamState(): void
    {
        unset($this->assignedTeams, $this->teamResults, $this->teamsChanged);
    }

    private function fillFromUser(): void
    {
        $this->name = $this->user->name;
        $this->email = $this->user->email;
        $this->role = $this->user->role->name;
        $this->teams = $this->user->teams()->pluck('teams.id')->map(fn ($id) => (string) $id)->all();
        $this->forgetTeamState();
    }
}; ?>

<section class="w-full">
    <x-admin.page-heading :heading="$user->name" :subheading="$user->email">
        <x-slot:actions>
            <flux:button variant="ghost" icon="arrow-left" :href="route('admin.users.index')" wire:navigate>{{ __('All users') }}</flux:button>
        </x-slot:actions>
    </x-admin.page-heading>

    @if ($user->role->requiresTeamManager() && ! $user->hasManagedTeam())
        <flux:callout variant="danger" icon="exclamation-triangle" class="mb-10" :heading="__('This user has no team with a manager.')" data-test="admin-user-unmanaged">
            <flux:callout.text>{{ __('Add them to a team that has a manager below.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div data-rise>
        <x-form-section :heading="__('Details')" :description="__('Name, email address and role. The role decides what this person can see and do.')">
            <form wire:submit="updateDetails" class="space-y-5">
                <flux:input wire:model="name" :label="__('Name')" required data-test="admin-user-name" />
                <flux:input wire:model="email" :label="__('Email')" type="email" required data-test="admin-user-email" />

                <flux:select wire:model="role" :label="__('Role')" :disabled="$this->isSelf" data-test="admin-user-role">
                    @foreach ($this->roles as $roleOption)
                        <flux:select.option :value="$roleOption->name">{{ $roleOption->label }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($this->isSelf)
                    <flux:description>{{ __('You cannot change your own role.') }}</flux:description>
                @endif

                <flux:button variant="primary" type="submit" data-test="admin-user-save-details">{{ __('Save details') }}</flux:button>
            </form>
        </x-form-section>

        <x-form-section
            :heading="__('Teams')"
            :description="$user->role->requiresTeamManager()
                ? __('Advisors must belong to at least one team with a manager.')
                : ($user->isManager()
                    ? __('Managers manage the advisors on every team they belong to.')
                    : __('Administrators can see every team regardless of membership.'))"
        >
            <form wire:submit="updateTeams" class="space-y-5">
                @if (! $this->hasAnyTeams)
                    <flux:text>{{ __('No teams exist yet.') }} <flux:link :href="route('admin.teams.create')" wire:navigate>{{ __('Create one') }}</flux:link></flux:text>
                @else
                    {{-- Assigned teams --}}
                    <ul role="list" class="border-y border-zinc-200 dark:border-white/9" data-test="admin-user-teams">
                        @forelse ($this->assignedTeams as $assigned)
                            <li
                                wire:key="assigned-team-{{ $assigned->id }}"
                                @class([
                                    'flex items-center gap-3 py-3 transition-colors duration-(--dur-fast) not-last:border-b not-last:border-zinc-200 dark:not-last:border-white/9',
                                    '-mx-3 rounded-xl bg-accent/6 px-3 ring-1 ring-accent/25 ring-inset' => $replacingTeamId === $assigned->id,
                                ])
                                data-test="admin-user-team-row"
                            >
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-zinc-200/80 font-display text-sm font-semibold text-zinc-700 dark:bg-white/8 dark:text-zinc-200" aria-hidden="true">{{ mb_strtoupper(mb_substr($assigned->name, 0, 1)) }}</span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 text-sm font-medium text-zinc-950 dark:text-white">
                                        <span class="truncate">{{ $assigned->name }}</span>
                                        @if ($user->current_team_id === $assigned->id)
                                            <flux:badge size="sm" color="indigo">{{ __('Current') }}</flux:badge>
                                        @endif
                                    </div>
                                    <p @class(['truncate text-xs', 'text-zinc-500 dark:text-zinc-400' => $assigned->managers->isNotEmpty(), 'text-attention-strong dark:text-attention' => $assigned->managers->isEmpty()])>
                                        {{ $assigned->managers->isEmpty() ? __('No manager') : __('Managed by :names', ['names' => $assigned->managers->pluck('name')->join(', ')]) }}
                                    </p>
                                </div>

                                @if ($replacingTeamId === $assigned->id)
                                    <flux:button size="sm" variant="ghost" wire:click="cancelChangingTeam">{{ __('Cancel') }}</flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" icon="arrows-right-left" wire:click="startChangingTeam({{ $assigned->id }})" data-test="admin-user-change-team">{{ __('Change') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeTeam({{ $assigned->id }})" :tooltip="__('Remove from team')" data-test="admin-user-remove-team" />
                                @endif
                            </li>
                        @empty
                            <li class="py-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Not in any team yet. Search below to add one.') }}</li>
                        @endforelse
                    </ul>

                    {{-- Search to add a team, or to pick a replacement --}}
                    <div class="space-y-2">
                        <flux:input
                            wire:model.live.debounce.250ms="teamSearch"
                            icon="magnifying-glass"
                            clearable
                            autocomplete="off"
                            :label="$replacingTeamId ? __('Replace :team with…', ['team' => $this->assignedTeams->firstWhere('id', $replacingTeamId)?->name]) : __('Add to a team')"
                            :placeholder="__('Search teams by name')"
                            x-on:keydown.escape="$wire.cancelChangingTeam()"
                            data-test="admin-user-team-search"
                        />

                        @if (trim($teamSearch) !== '')
                            <ul role="list" class="overflow-hidden rounded-xl bg-white ring-1 ring-zinc-200 dark:bg-white/4 dark:ring-white/9" data-test="admin-user-team-results">
                                @forelse ($this->teamResults as $result)
                                    <li wire:key="team-result-{{ $result->id }}" class="not-last:border-b not-last:border-zinc-100 dark:not-last:border-white/6">
                                        <button
                                            type="button"
                                            wire:click="addTeam({{ $result->id }})"
                                            class="group flex w-full items-center gap-3 px-3 py-2.5 text-start transition-colors duration-(--dur-fast) hover:bg-accent/6 focus-visible:bg-accent/6 focus-visible:outline-none"
                                            data-test="admin-user-team-result"
                                        >
                                            <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-zinc-100 font-display text-xs font-semibold text-zinc-600 dark:bg-white/8 dark:text-zinc-300" aria-hidden="true">{{ mb_strtoupper(mb_substr($result->name, 0, 1)) }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-medium text-zinc-950 dark:text-white">{{ $result->name }}</span>
                                                <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ $result->managers->isEmpty() ? __('No manager') : __('Managed by :names', ['names' => $result->managers->pluck('name')->join(', ')]) }}
                                                </span>
                                            </span>
                                            <span class="flex items-center gap-1 text-xs font-medium text-accent-content">
                                                <flux:icon :name="$replacingTeamId ? 'arrows-right-left' : 'plus'" variant="micro" class="size-3.5" />
                                                {{ $replacingTeamId ? __('Switch') : __('Add') }}
                                            </span>
                                        </button>
                                    </li>
                                @empty
                                    <li class="px-3 py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No other teams match “:search”.', ['search' => $teamSearch]) }}</li>
                                @endforelse
                            </ul>
                        @endif
                    </div>

                    <flux:error name="teams" />

                    @if ($this->teamsChanged)
                        <div class="flex flex-wrap items-center gap-3">
                            <flux:button variant="primary" type="submit" data-test="admin-user-save-teams">{{ __('Save teams') }}</flux:button>
                            <flux:button variant="ghost" wire:click="discardTeamChanges">{{ __('Discard') }}</flux:button>
                            <span class="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                                <span class="size-1.5 rounded-full bg-attention"></span>
                                {{ __('Unsaved changes') }}
                            </span>
                        </div>
                    @endif
                @endif
            </form>
        </x-form-section>

        <x-form-section :heading="__('Password')" :description="__('Set a new password for this user. They can change it later in their settings.')">
            <form wire:submit="updatePassword" class="space-y-5">
                <flux:input wire:model="password" :label="__('New password')" type="password" viewable autocomplete="new-password" data-test="admin-user-password" />
                <flux:input wire:model="password_confirmation" :label="__('Confirm new password')" type="password" viewable autocomplete="new-password" />
                <flux:button type="submit" data-test="admin-user-save-password">{{ __('Set password') }}</flux:button>
            </form>
        </x-form-section>

        <x-form-section :heading="__('Sign-in methods')" :description="__('Reset these if the person has lost access to a device.')">
            @php($passkeyCount = $user->passkeys()->count())
            <ul role="list" class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-white/9 dark:border-white/9">
                <li class="flex items-center justify-between gap-4 py-3.5">
                    <div class="flex items-center gap-3">
                        <span @class(['size-2 rounded-full', 'bg-emerald-500' => $user->hasEnabledTwoFactorAuthentication(), 'bg-zinc-300 dark:bg-white/20' => ! $user->hasEnabledTwoFactorAuthentication()])></span>
                        <div>
                            <p class="text-sm font-medium text-zinc-950 dark:text-white">{{ __('Two-factor authentication') }}</p>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $user->hasEnabledTwoFactorAuthentication() ? __('Enabled') : __('Not enabled') }}</p>
                        </div>
                    </div>
                    @if ($user->two_factor_secret)
                        <flux:button size="sm" wire:click="resetTwoFactor" wire:confirm="{{ __('Reset two-factor authentication for :name?', ['name' => $user->name]) }}" data-test="admin-user-reset-2fa">
                            {{ __('Reset') }}
                        </flux:button>
                    @endif
                </li>
                <li class="flex items-center justify-between gap-4 py-3.5">
                    <div class="flex items-center gap-3">
                        <span @class(['size-2 rounded-full', 'bg-emerald-500' => $passkeyCount > 0, 'bg-zinc-300 dark:bg-white/20' => $passkeyCount === 0])></span>
                        <div>
                            <p class="text-sm font-medium text-zinc-950 dark:text-white">{{ __('Passkeys') }}</p>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ trans_choice('{0} None registered|{1} :count registered|[2,*] :count registered', $passkeyCount) }}</p>
                        </div>
                    </div>
                    @if ($passkeyCount > 0)
                        <flux:button size="sm" wire:click="removePasskeys" wire:confirm="{{ __('Remove all passkeys for :name?', ['name' => $user->name]) }}" data-test="admin-user-remove-passkeys">
                            {{ __('Remove all') }}
                        </flux:button>
                    @endif
                </li>
            </ul>
        </x-form-section>

        @unless ($this->isSelf)
            <x-form-section :heading="__('Delete user')" :description="__('Permanently delete this account. This cannot be undone.')" danger>
                <flux:modal.trigger name="delete-user">
                    <flux:button variant="danger" icon="trash" data-test="admin-delete-user-button">{{ __('Delete user') }}</flux:button>
                </flux:modal.trigger>
            </x-form-section>

            <flux:modal name="delete-user" class="max-w-lg">
                <form wire:submit="deleteUser" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Delete user?') }}</flux:heading>
                        <flux:text class="mt-2">{{ __(':name\'s account will be permanently deleted. This cannot be undone.', ['name' => $user->name]) }}</flux:text>
                    </div>
                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="danger" type="submit" data-test="admin-delete-user-confirm">{{ __('Delete user') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endunless
    </div>
</section>

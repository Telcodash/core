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

    /** @var list<string> IDs of the teams the user belongs to. */
    public array $teams = [];

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
     * @return Collection<int, Team>
     */
    #[Computed]
    public function allTeams(): Collection
    {
        return Team::query()->with('managers')->orderBy('name')->get();
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

        unset($this->allTeams);

        $this->fillFromUser();
    }

    private function fillFromUser(): void
    {
        $this->name = $this->user->name;
        $this->email = $this->user->email;
        $this->role = $this->user->role->name;
        $this->teams = $this->user->teams()->pluck('teams.id')->map(fn ($id) => (string) $id)->all();
    }
}; ?>

<section class="w-full">
    <x-admin.page-heading :heading="$user->name" :subheading="$user->email">
        <x-slot:actions>
            <flux:badge :color="$user->role->badgeColor()">{{ $user->role->label }}</flux:badge>
            <flux:button variant="ghost" icon="arrow-left" :href="route('admin.users.index')" wire:navigate>{{ __('All users') }}</flux:button>
        </x-slot:actions>
    </x-admin.page-heading>

    <div class="max-w-2xl space-y-12">
        @if ($user->role->requiresTeamManager() && ! $user->hasManagedTeam())
            <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('This user has no team with a manager.')" data-test="admin-user-unmanaged">
                <flux:callout.text>{{ __('Add them to a team that has a manager below.') }}</flux:callout.text>
            </flux:callout>
        @endif

        {{-- Details & role --}}
        <form wire:submit="updateDetails" class="space-y-4">
            <flux:heading size="lg">{{ __('Details') }}</flux:heading>

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

        {{-- Teams --}}
        <form wire:submit="updateTeams" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Teams') }}</flux:heading>
                <flux:subheading>
                    @if ($user->role->requiresTeamManager())
                        {{ __('Advisors must belong to at least one team with a manager.') }}
                    @elseif ($user->isManager())
                        {{ __('Managers manage the advisors on every team they belong to.') }}
                    @else
                        {{ __('Administrators can see every team regardless of membership.') }}
                    @endif
                </flux:subheading>
            </div>

            @if ($this->allTeams->isEmpty())
                <flux:text>{{ __('No teams exist yet.') }} <flux:link :href="route('admin.teams.create')" wire:navigate>{{ __('Create one') }}</flux:link></flux:text>
            @else
                <flux:checkbox.group wire:model="teams" data-test="admin-user-teams">
                    @foreach ($this->allTeams as $teamOption)
                        <flux:checkbox
                            :value="(string) $teamOption->id"
                            :label="$teamOption->name"
                            :description="$teamOption->managers->isEmpty()
                                ? __('No manager')
                                : __('Managed by :names', ['names' => $teamOption->managers->pluck('name')->join(', ')])"
                        />
                    @endforeach
                </flux:checkbox.group>
                <flux:error name="teams" />

                @if ($user->currentTeam)
                    <flux:text class="text-sm">{{ __('Current team: :name', ['name' => $user->currentTeam->name]) }}</flux:text>
                @endif

                <flux:button variant="primary" type="submit" data-test="admin-user-save-teams">{{ __('Save teams') }}</flux:button>
            @endif
        </form>

        {{-- Password --}}
        <form wire:submit="updatePassword" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Password') }}</flux:heading>
                <flux:subheading>{{ __('Set a new password for this user.') }}</flux:subheading>
            </div>
            <flux:input wire:model="password" :label="__('New password')" type="password" viewable autocomplete="new-password" data-test="admin-user-password" />
            <flux:input wire:model="password_confirmation" :label="__('Confirm new password')" type="password" viewable autocomplete="new-password" />
            <flux:button type="submit" data-test="admin-user-save-password">{{ __('Set password') }}</flux:button>
        </form>

        {{-- Sign-in methods --}}
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Sign-in methods') }}</flux:heading>

            <div class="flex items-center justify-between gap-4">
                <div>
                    <flux:text variant="strong">{{ __('Two-factor authentication') }}</flux:text>
                    <flux:text class="text-sm">{{ $user->hasEnabledTwoFactorAuthentication() ? __('Enabled') : __('Not enabled') }}</flux:text>
                </div>
                @if ($user->two_factor_secret)
                    <flux:button size="sm" wire:click="resetTwoFactor" wire:confirm="{{ __('Reset two-factor authentication for :name?', ['name' => $user->name]) }}" data-test="admin-user-reset-2fa">
                        {{ __('Reset') }}
                    </flux:button>
                @endif
            </div>

            <div class="flex items-center justify-between gap-4">
                @php($passkeyCount = $user->passkeys()->count())
                <div>
                    <flux:text variant="strong">{{ __('Passkeys') }}</flux:text>
                    <flux:text class="text-sm">{{ trans_choice('{0} None registered|{1} :count registered|[2,*] :count registered', $passkeyCount) }}</flux:text>
                </div>
                @if ($passkeyCount > 0)
                    <flux:button size="sm" wire:click="removePasskeys" wire:confirm="{{ __('Remove all passkeys for :name?', ['name' => $user->name]) }}" data-test="admin-user-remove-passkeys">
                        {{ __('Remove all') }}
                    </flux:button>
                @endif
            </div>
        </div>

        {{-- Danger zone --}}
        @unless ($this->isSelf)
            <div class="space-y-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-900/20">
                <div>
                    <flux:heading class="text-red-700! dark:text-red-100!">{{ __('Delete user') }}</flux:heading>
                    <flux:text class="text-red-700 dark:text-red-100">{{ __('Permanently delete this account. This cannot be undone.') }}</flux:text>
                </div>
                <flux:modal.trigger name="delete-user">
                    <flux:button variant="danger" data-test="admin-delete-user-button">{{ __('Delete user') }}</flux:button>
                </flux:modal.trigger>
            </div>

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

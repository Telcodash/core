<?php

use App\Actions\Users\CreateUser;
use App\Livewire\AdminComponent;
use App\Models\Role;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::admin')] #[Title('New user')] class extends AdminComponent {
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $role = Role::ADVISOR;

    /** Slug of an existing team to join. */
    public string $team = '';

    /** Name of a new team to create instead. */
    public string $newTeam = '';

    /**
     * @return Collection<int, Role>
     */
    #[Computed]
    public function roles(): Collection
    {
        return Role::ordered();
    }

    #[Computed]
    public function selectedRole(): Role
    {
        return $this->roles->firstWhere('name', $this->role) ?? $this->roles->first();
    }

    /**
     * Teams the user can join: advisors may only join teams that have a manager.
     *
     * @return Collection<int, Team>
     */
    #[Computed]
    public function teams(): Collection
    {
        return Team::query()
            ->when($this->selectedRole->requiresTeamManager(), fn ($query) => $query->withManager())
            ->orderBy('name')
            ->get();
    }

    public function updatedRole(): void
    {
        unset($this->selectedRole, $this->teams);

        if ($this->team !== '' && ! $this->teams->contains('slug', $this->team)) {
            $this->team = '';
        }

        if (! $this->selectedRole->canCreateTeams()) {
            $this->newTeam = '';
        }
    }

    public function save(CreateUser $createUser): void
    {
        $this->validate([
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        try {
            $user = $createUser->handle([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'role' => $this->role,
                'team' => $this->team !== '' ? $this->team : null,
                'new_team' => filled($this->newTeam) ? $this->newTeam : null,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key === 'new_team' ? 'newTeam' : $key, $messages[0]);
            }

            return;
        }

        Flux::toast(variant: 'success', text: __('User ":name" created.', ['name' => $user->name]));

        $this->redirectRoute('admin.users.edit', $user, navigate: true);
    }
}; ?>

<section class="w-full">
    <x-admin.page-heading :heading="__('New user')" :subheading="__('Create an account and assign it to a team')">
        <x-slot:actions>
            <flux:button variant="ghost" icon="arrow-left" :href="route('admin.users.index')" wire:navigate>{{ __('All users') }}</flux:button>
        </x-slot:actions>
    </x-admin.page-heading>

    <form wire:submit="save" data-rise>
        <x-form-section :heading="__('Account')" :description="__('The person signs in with this email address and password.')">
            <div class="space-y-5">
                <flux:input wire:model="name" :label="__('Name')" required autofocus autocomplete="off" data-test="admin-user-name" />
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="off" data-test="admin-user-email" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="password" :label="__('Password')" type="password" required viewable autocomplete="new-password" data-test="admin-user-password" />
                    <flux:input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required viewable autocomplete="new-password" />
                </div>
            </div>
        </x-form-section>

        <x-form-section :heading="__('Role and team')" :description="__('The role decides what this person can see and do.')">
            <div class="space-y-5">
                <flux:select wire:model.live="role" :label="__('Role')" data-test="admin-user-role">
                    @foreach ($this->roles as $roleOption)
                        <flux:select.option :value="$roleOption->name">{{ $roleOption->label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:fieldset>
                    <flux:legend>{{ __('Team') }}</flux:legend>
                    <flux:description>
                        @if ($this->selectedRole->requiresTeamManager())
                            {{ __(':role users must join a team that has a manager.', ['role' => $this->selectedRole->label]) }}
                        @elseif ($this->selectedRole->isManager())
                            {{ __('Managers must join an existing team or create a new one.') }}
                        @else
                            {{ __('Administrators can see every team, so a team is optional.') }}
                        @endif
                    </flux:description>

                    <div class="mt-3 space-y-4">
                        <flux:select wire:model.live="team" :disabled="filled($newTeam)" data-test="admin-user-team">
                            <flux:select.option value="">{{ __('No existing team') }}</flux:select.option>
                            @foreach ($this->teams as $teamOption)
                                <flux:select.option :value="$teamOption->slug">{{ $teamOption->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="team" />

                        @if ($this->selectedRole->canCreateTeams())
                            <flux:input wire:model.live.blur="newTeam" :label="__('Or create a new team')" :placeholder="__('New team name')" :disabled="$team !== ''" data-test="admin-user-new-team" />
                        @endif
                    </div>
                </flux:fieldset>
            </div>
        </x-form-section>

        <div class="flex gap-2 border-t border-zinc-200 pt-6 lg:ps-[calc(15rem+3rem)] dark:border-white/9">
            <flux:button variant="primary" type="submit" data-test="admin-user-save">{{ __('Create user') }}</flux:button>
            <flux:button variant="ghost" :href="route('admin.users.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>

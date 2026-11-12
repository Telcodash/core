<?php

use App\Livewire\AdminComponent;
use App\Models\Team;
use App\Rules\TeamName;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::admin')] #[Title('New team')] class extends AdminComponent {
    public string $name = '';

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', new TeamName],
        ]);

        $team = Team::create(['name' => $validated['name']]);

        Flux::toast(variant: 'success', text: __('Team created. Add a manager and members below.'));

        $this->redirectRoute('admin.teams.edit', $team, navigate: true);
    }
}; ?>

<section class="w-full">
    <x-admin.page-heading :heading="__('New team')" :subheading="__('Members can be added once the team is created')" />

    <form wire:submit="save" class="max-w-lg space-y-6">
        <flux:input wire:model="name" :label="__('Team name')" required autofocus data-test="admin-team-name" />

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit" data-test="admin-team-save">{{ __('Create team') }}</flux:button>
            <flux:button variant="ghost" :href="route('admin.teams.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</section>

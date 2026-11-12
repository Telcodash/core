<?php

use App\Livewire\AdminComponent;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::admin')] #[Title('Overview')] class extends AdminComponent {
}; ?>

<section class="w-full">
    <x-admin.page-heading :heading="__('Overview')" :subheading="__('Administration overview')" />

    <div class="flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700" data-test="admin-overview-empty">
        <flux:icon name="chart-bar" class="mb-3 size-8 text-zinc-400" />
        <flux:heading>{{ __('Nothing here yet') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Use Teams and Users in the sidebar to manage the application.') }}</flux:text>
    </div>
</section>

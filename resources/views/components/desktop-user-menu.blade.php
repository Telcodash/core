@props(['showTeam' => true, 'admin' => false])

<flux:dropdown position="top" align="start" {{ $attributes }}>
    <button type="button" class="group flex w-full items-center gap-3 rounded-xl p-1.5 text-start transition-colors duration-(--dur-fast) hover:bg-white/7" data-test="sidebar-menu-button">
        <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" size="sm" color="auto" circle />
        <div class="in-data-flux-sidebar-collapsed-desktop:hidden grid min-w-0 flex-1 text-sm leading-tight">
            <span class="truncate font-medium text-white">{{ auth()->user()->name }}</span>
            <span class="truncate text-xs text-white/50">{{ auth()->user()->role->label }}</span>
        </div>
        <flux:icon name="ellipsis-horizontal" variant="micro" class="in-data-flux-sidebar-collapsed-desktop:hidden ms-auto size-4 text-white/40 group-hover:text-white/80" />
    </button>

    <flux:menu class="min-w-60">
        <x-user-menu-identity />

        <flux:menu.separator />

        <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>
            {{ __('Settings') }}
        </flux:menu.item>

        @if ($admin)
            <flux:menu.item :href="route('app.home')" icon="arrow-uturn-left">
                {{ __('Back to app') }}
            </flux:menu.item>
        @endif

        <flux:menu.separator />

        <x-logout-menu-item />
    </flux:menu>
</flux:dropdown>

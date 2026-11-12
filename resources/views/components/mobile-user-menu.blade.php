@props(['admin' => false])

<flux:dropdown position="top" align="end">
    <flux:profile :initials="auth()->user()->initials()" avatar:color="auto" avatar:color:seed="{{ auth()->user()->name }}" circle icon-trailing="chevron-down" />

    <flux:menu class="min-w-60">
        <x-user-menu-identity />

        <flux:menu.separator />

        @if ($admin)
            <flux:menu.item :href="route('app.home')" icon="arrow-uturn-left">
                {{ __('Back to app') }}
            </flux:menu.item>
        @else
            <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>
                {{ __('Settings') }}
            </flux:menu.item>
        @endif

        <flux:menu.separator />

        <x-logout-menu-item />
    </flux:menu>
</flux:dropdown>

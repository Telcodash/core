<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen">
        <flux:sidebar sticky collapsible="mobile" class="dark gap-5 text-white" data-island>
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('admin.overview') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Administration')" class="grid">
                    <flux:sidebar.item icon="squares-2x2" :href="route('admin.overview')" :current="request()->routeIs('admin.overview')" wire:navigate data-test="admin-nav-overview">
                        {{ __('Overview') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-group" :href="route('admin.teams.index')" :current="request()->routeIs('admin.teams.*')" wire:navigate data-test="admin-nav-teams">
                        {{ __('Teams') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('admin.users.index')" :current="request()->routeIs('admin.users.*')" wire:navigate data-test="admin-nav-users">
                        {{ __('Users') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="arrow-uturn-left" :href="route('app.home')" data-test="admin-back-to-app">
                    {{ __('Back to app') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <div class="border-t border-white/8 pt-3">
                <x-desktop-user-menu class="hidden lg:block" :show-team="false" :admin="true" />
            </div>
        </flux:sidebar>

        <!-- Mobile header -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('admin.overview') }}" class="ms-2" wire:navigate />

            <flux:spacer />

            <x-mobile-user-menu :admin="true" />
        </flux:header>

        <flux:main class="mx-auto w-full max-w-6xl px-5! py-8! sm:px-8! lg:px-12! lg:py-12!">
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>

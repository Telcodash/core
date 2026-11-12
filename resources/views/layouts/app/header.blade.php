<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen">
        @php($dashboardUrl = auth()->user()->currentTeam ? route('dashboard') : route('app.home'))
        <flux:header container class="border-b border-zinc-200 dark:border-white/9">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ $dashboardUrl }}" wire:navigate />

            <flux:navbar class="-mb-px ms-4 max-lg:hidden">
                <flux:navbar.item icon="squares-2x2" :href="$dashboardUrl" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>
                @can('access-admin')
                    <flux:navbar.item icon="shield-check" :href="route('admin.overview')" wire:navigate data-test="nav-admin">
                        {{ __('Admin area') }}
                    </flux:navbar.item>
                @endcan
            </flux:navbar>

            <flux:spacer />

            <div class="dark me-2 w-56 rounded-xl bg-ink max-lg:hidden">
                <livewire:team-switcher />
            </div>

            <x-mobile-user-menu />
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="dark lg:hidden text-white" data-island>
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ $dashboardUrl }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <livewire:team-switcher />

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Workspace')">
                    <flux:sidebar.item icon="squares-2x2" :href="$dashboardUrl" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard')  }}
                    </flux:sidebar.item>
                    @can('access-admin')
                        <flux:sidebar.item icon="shield-check" :href="route('admin.overview')" wire:navigate>
                            {{ __('Admin area') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        {{ $slot }}

        @can('create', App\Models\Team::class)
            <livewire:create-team-modal />
        @endcan

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen">
        @php($dashboardUrl = auth()->user()->currentTeam ? route('dashboard') : route('app.home'))

        {{-- The island: always deep ink, so Flux renders its dark variants inside it. --}}
        <flux:sidebar sticky collapsible="mobile" class="dark gap-5 text-white" data-island>
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ $dashboardUrl }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <livewire:team-switcher />

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Workspace')" class="grid">
                    <flux:sidebar.item icon="squares-2x2" :href="$dashboardUrl" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @can('access-admin')
                    <flux:sidebar.group :heading="__('Organisation')" class="grid">
                        <flux:sidebar.item icon="shield-check" :href="route('admin.overview')" wire:navigate data-test="nav-admin">
                            {{ __('Admin area') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="cog-6-tooth" :href="route('profile.edit')" :current="request()->routeIs('profile.*', 'security.*', 'appearance.*', 'teams.*')" wire:navigate>
                    {{ __('Settings') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <div class="border-t border-white/8 pt-3">
                <x-desktop-user-menu class="hidden lg:block" />
            </div>
        </flux:sidebar>

        <!-- Mobile header -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <x-app-logo href="{{ $dashboardUrl }}" class="ms-2" wire:navigate />

            <flux:spacer />

            <x-mobile-user-menu />
        </flux:header>

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

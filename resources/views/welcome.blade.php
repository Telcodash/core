<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => null])
    </head>
    <body class="min-h-screen">
        <div class="mx-auto flex min-h-svh max-w-6xl flex-col px-5 sm:px-8">
            <header class="flex h-20 items-center justify-between">
                <x-app-logo href="{{ route('home') }}" />

                @if (Route::has('login'))
                    @auth
                        <flux:button size="sm" :href="route('app.home')">{{ __('Open dashboard') }}</flux:button>
                    @else
                        <flux:button size="sm" :href="route('login')">{{ __('Log in') }}</flux:button>
                    @endauth
                @endif
            </header>

            <main class="grid flex-1 items-center gap-12 py-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] lg:gap-16" data-rise>
                <div class="space-y-7">
                    <h1 class="font-display text-[2.75rem] leading-[1.02] font-semibold text-zinc-950 sm:text-6xl dark:text-white">
                        {{ __('The switchboard for your telecoms teams.') }}
                    </h1>
                    <p class="max-w-lg text-lg text-zinc-600 dark:text-zinc-400">
                        {{ __('Sales, support and operations in one workspace, organised by team, with access set by role.') }}
                    </p>
                    <div class="flex flex-wrap items-center gap-4">
                        @auth
                            <flux:button variant="primary" :href="route('app.home')">{{ __('Open dashboard') }}</flux:button>
                        @else
                            <flux:button variant="primary" :href="route('login')">{{ __('Log in') }}</flux:button>
                            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Accounts are created by your administrator.') }}</span>
                        @endauth
                    </div>
                </div>

                <div class="dark relative aspect-[5/4] overflow-hidden rounded-[1.75rem] bg-ink text-white shadow-(--shadow-island)" aria-hidden="true">
                    <div class="absolute inset-0 bg-[radial-gradient(60%_55%_at_55%_45%,oklch(0.5_0.2_275/0.4),transparent)]"></div>
                    <x-network-art class="absolute inset-0 m-auto w-[90%]" />
                </div>
            </main>

            <footer class="flex h-16 items-center text-xs text-zinc-500 dark:text-zinc-400">
                &copy; {{ now()->year }} {{ config('app.name', 'TelcoDash') }}
            </footer>
        </div>

        @fluxScripts
    </body>
</html>

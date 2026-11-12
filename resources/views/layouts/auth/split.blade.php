<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen">
        <div class="grid min-h-dvh lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">
            <div class="flex flex-col px-5 py-6 sm:px-10 lg:px-14">
                <x-app-logo href="{{ route('home') }}" class="self-start" wire:navigate />

                <div class="flex flex-1 items-center py-12">
                    <div class="mx-auto w-full max-w-sm animate-rise">
                        {{ $slot }}
                    </div>
                </div>

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Accounts are created by your administrator.') }}
                </p>
            </div>

            <div class="dark relative m-3 hidden flex-col justify-end overflow-hidden rounded-[1.75rem] bg-ink p-12 text-white shadow-(--shadow-island) lg:flex">
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_50%_at_60%_40%,oklch(0.5_0.2_275/0.35),transparent)]" aria-hidden="true"></div>
                <x-network-art class="absolute inset-x-0 top-[8%] mx-auto w-[88%] text-white" />

                <div class="relative max-w-md space-y-4">
                    <p class="font-display text-[2.25rem] leading-[1.1] font-semibold tracking-tight">
                        {{ __('Every line, queue and target, in one place.') }}
                    </p>
                    <p class="text-white/60">
                        {{ __('TelcoDash connects your sales, support and operations teams.') }}
                    </p>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>

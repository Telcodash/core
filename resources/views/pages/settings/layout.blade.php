@php
    $tabs = [
        ['label' => __('Profile'), 'icon' => 'user-circle', 'route' => 'profile.edit', 'active' => request()->routeIs('profile.*')],
        ['label' => __('Security'), 'icon' => 'lock-closed', 'route' => 'security.edit', 'active' => request()->routeIs('security.*')],
        ['label' => __('Teams'), 'icon' => 'user-group', 'route' => 'teams.index', 'active' => request()->routeIs('teams.*')],
        ['label' => __('Appearance'), 'icon' => 'swatch', 'route' => 'appearance.edit', 'active' => request()->routeIs('appearance.*')],
    ];
@endphp

<div>
    <nav class="-mt-4 mb-10 overflow-x-auto border-b border-zinc-200 lg:-mt-8 dark:border-white/9" aria-label="{{ __('Settings') }}">
        <ul role="list" class="flex min-w-max gap-1">
            @foreach ($tabs as $tab)
                <li>
                    <a
                        href="{{ route($tab['route']) }}"
                        wire:navigate
                        @if ($tab['active']) aria-current="page" @endif
                        @class([
                            'relative flex items-center gap-2 px-3 pt-2 pb-3.5 text-sm font-medium transition-colors duration-(--dur-fast)',
                            'after:absolute after:inset-x-3 after:-bottom-px after:h-0.5 after:rounded-full after:bg-accent',
                            'text-zinc-950 dark:text-white' => $tab['active'],
                            'text-zinc-500 after:scale-x-0 after:transition-transform after:duration-(--dur-base) hover:text-zinc-900 hover:after:scale-x-50 hover:after:bg-zinc-300 dark:text-zinc-400 dark:hover:text-white dark:hover:after:bg-white/20' => ! $tab['active'],
                        ])
                    >
                        <flux:icon :name="$tab['icon']" variant="micro" @class(['size-4', 'text-accent-content' => $tab['active'], 'text-zinc-400' => ! $tab['active']]) />
                        {{ $tab['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="grid gap-x-14 gap-y-8 lg:grid-cols-[16rem_minmax(0,1fr)]" data-rise>
        <div class="space-y-2">
            <h2 class="font-display text-xl font-semibold text-zinc-950 dark:text-white">{{ $heading ?? '' }}</h2>
            @if (filled($subheading ?? null))
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $subheading }}</p>
            @endif
        </div>

        <div class="w-full max-w-xl">
            {{ $slot }}
        </div>
    </div>
</div>

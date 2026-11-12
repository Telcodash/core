<x-layouts::app :title="__('Dashboard')">
    @php
        $user = auth()->user();
        $team = $user->currentTeam;
        $members = $team->members()->orderBy('name')->get();
        $otherTeams = $user->teams()->whereKeyNot($team->id)->orderBy('name')->get();
        $hour = now()->hour;
        $greeting = match (true) {
            $hour < 12 => __('Good morning'),
            $hour < 18 => __('Good afternoon'),
            default => __('Good evening'),
        };
        $modules = [
            ['icon' => 'presentation-chart-line', 'name' => __('Sales'), 'description' => __('Pipeline, targets and team performance.')],
            ['icon' => 'chat-bubble-left-right', 'name' => __('Support'), 'description' => __('Customer contact, queues and service levels.')],
            ['icon' => 'signal', 'name' => __('Operations'), 'description' => __('Accounts, lines and provisioning.')],
        ];
    @endphp

    <x-page-header :heading="$greeting.', '.str($user->name)->before(' ')">
        <x-slot:meta>
            <x-meta-chip icon="user-group">{{ $team->name }}</x-meta-chip>
            <x-meta-chip icon="users">{{ trans_choice(':count member|:count members', $members->count()) }}</x-meta-chip>
            <x-meta-chip icon="calendar">{{ now()->translatedFormat('l j F') }}</x-meta-chip>
        </x-slot:meta>

        @can('update', $team)
            <x-slot:actions>
                <flux:button icon="cog-6-tooth" :href="route('teams.edit', $team)" wire:navigate>
                    {{ __('Manage team') }}
                </flux:button>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <div class="grid gap-x-14 gap-y-12 lg:grid-cols-[minmax(0,1fr)_19rem]" data-rise>
        <div class="min-w-0 space-y-14">
            <x-section :heading="__('Modules')" :description="__('The tools coming to this workspace. Each one switches on here when it goes live.')">
                <ol role="list" class="grid gap-7 sm:grid-cols-3 sm:gap-6">
                    @foreach ($modules as $module)
                        <li class="group relative flex gap-4 sm:block">
                            {{-- The link to the next module: dashed while nothing is live yet. --}}
                            @unless ($loop->last)
                                <span class="absolute top-12 -bottom-7 left-5 border-l-2 border-dashed border-zinc-300 sm:top-5 sm:-right-6 sm:bottom-auto sm:left-12 sm:border-t-2 sm:border-l-0 dark:border-white/12" aria-hidden="true"></span>
                            @endunless
                            <div class="relative z-10 flex size-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 ring-2 ring-zinc-300 transition-all duration-(--dur-base) ease-(--ease-out) group-hover:ring-accent/60 dark:bg-zinc-950 dark:ring-white/14">
                                <flux:icon :name="$module['icon']" class="size-[18px] text-zinc-500 transition-colors duration-(--dur-base) group-hover:text-accent-content dark:text-zinc-400" />
                            </div>
                            <div class="min-w-0 sm:mt-4">
                                <div class="flex items-center gap-2">
                                    <p class="font-display text-base font-semibold text-zinc-950 dark:text-white">{{ $module['name'] }}</p>
                                    <span class="text-xs text-zinc-400">{{ __('Planned') }}</span>
                                </div>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $module['description'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-section>

            <x-section :heading="__('Team')" :count="$members->count()">
                @if ($members->isEmpty())
                    <x-empty-state icon="user-group" :heading="__('No members yet')" :description="__('People added to :team will be listed here.', ['team' => $team->name])" />
                @else
                    <ul role="list" class="grid gap-x-10 sm:grid-cols-2">
                        @foreach ($members as $member)
                            <li class="-mx-3 flex items-center gap-3 rounded-xl px-3 py-3 transition-colors duration-(--dur-fast) hover:bg-white dark:hover:bg-white/4">
                                <flux:avatar :name="$member->name" :initials="$member->initials()" color="auto" circle />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-zinc-950 dark:text-white">
                                        {{ $member->name }}
                                        @if ($member->is($user))
                                            <span class="font-normal text-zinc-400">{{ __('you') }}</span>
                                        @endif
                                    </p>
                                    <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $member->email }}</p>
                                </div>
                                <flux:badge size="sm" :color="$member->role->badgeColor()">{{ $member->role->label }}</flux:badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-section>
        </div>

        <aside class="space-y-10">
            {{-- Account: a piece of the island's ink, so personal status reads as part of the shell. --}}
            <div class="dark relative overflow-hidden rounded-[1.25rem] bg-ink p-6 text-white shadow-(--shadow-island)">

                <div class="flex items-center gap-3">
                    <flux:avatar :name="$user->name" :initials="$user->initials()" color="auto" circle size="lg" />
                    <div class="min-w-0">
                        <p class="truncate font-display text-base font-semibold">{{ $user->name }}</p>
                        <p class="truncate text-xs text-white/50">{{ $user->email }}</p>
                    </div>
                </div>

                <dl class="mt-6 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-white/55">{{ __('Role') }}</dt>
                        <dd><flux:badge size="sm" :color="$user->role->badgeColor()">{{ $user->role->label }}</flux:badge></dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-white/55">{{ __('Two-factor') }}</dt>
                        <dd class="flex items-center gap-2 font-medium">
                            @if ($user->two_factor_confirmed_at)
                                <span class="size-1.5 rounded-full bg-emerald-400"></span> {{ __('On') }}
                            @else
                                <span class="size-1.5 rounded-full bg-attention"></span> <span class="text-attention">{{ __('Off') }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-white/55">{{ __('Member since') }}</dt>
                        <dd class="font-medium tabular-nums">{{ $user->created_at?->format('M Y') }}</dd>
                    </div>
                </dl>

                @unless ($user->two_factor_confirmed_at)
                    <a href="{{ route('security.edit') }}" wire:navigate class="group mt-6 flex items-center gap-3 rounded-xl bg-attention/12 p-3 text-sm ring-1 ring-attention/25 transition-colors duration-(--dur-fast) ring-inset hover:bg-attention/18">
                        <flux:icon name="lock-closed" variant="mini" class="size-5 shrink-0 text-attention" />
                        <span class="min-w-0 flex-1 leading-snug text-white/85">{{ __('Add a second sign-in step to protect your account.') }}</span>
                        <flux:icon name="chevron-right" variant="micro" class="size-4 text-attention transition-transform duration-(--dur-fast) group-hover:translate-x-0.5" />
                    </a>
                @endunless
            </div>

            @if ($otherTeams->isNotEmpty())
                <x-section :heading="__('Your other teams')">
                    <ul role="list" class="space-y-1">
                        @foreach ($otherTeams as $otherTeam)
                            <li class="flex items-center gap-3 py-1.5 text-sm">
                                <span class="flex size-7 items-center justify-center rounded-lg bg-zinc-200/80 font-display text-xs font-semibold text-zinc-700 dark:bg-white/8 dark:text-zinc-200">{{ mb_strtoupper(mb_substr($otherTeam->name, 0, 1)) }}</span>
                                <span class="truncate font-medium text-zinc-800 dark:text-zinc-200">{{ $otherTeam->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-section>
            @endif
        </aside>
    </div>
</x-layouts::app>

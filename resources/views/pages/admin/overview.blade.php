<?php

use App\Livewire\AdminComponent;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts::admin')] #[Title('Overview')] class extends AdminComponent {
    /**
     * @return array<int, array{label: string, value: int, href: string}>
     */
    #[Computed]
    public function totals(): array
    {
        return [
            ['label' => __('Users'), 'value' => User::count(), 'href' => route('admin.users.index')],
            ['label' => __('Teams'), 'value' => Team::count(), 'href' => route('admin.teams.index')],
            ['label' => __('Managers'), 'value' => User::withRole(Role::MANAGER)->count(), 'href' => route('admin.users.index', ['role' => Role::MANAGER])],
        ];
    }

    /**
     * Teams that have no manager, so their advisors are unmanaged.
     *
     * @return Collection<int, Team>
     */
    #[Computed]
    public function teamsWithoutManager(): Collection
    {
        return Team::query()->whereNot(fn ($query) => $query->withManager())->orderBy('name')->get();
    }

    /**
     * Advisor-tier users who belong to no team.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function advisorsWithoutTeam(): Collection
    {
        return User::query()->withRole(...Role::ADVISOR_TIER)->whereDoesntHave('teams')->orderBy('name')->get();
    }
}; ?>

<section class="w-full">
    <x-admin.page-heading :heading="__('Overview')" :subheading="__('Accounts and teams across the organisation.')" />

    <div class="space-y-14" data-rise>
        <dl class="grid border-y border-zinc-200 sm:grid-cols-3 dark:border-white/9">
            @foreach ($this->totals as $total)
                <a href="{{ $total['href'] }}" wire:navigate class="group relative flex items-end justify-between gap-4 py-6 max-sm:border-b max-sm:last:border-b-0 sm:px-6 sm:first:ps-0 sm:not-first:border-s dark:border-white/9">
                    <div>
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ $total['label'] }}</dt>
                        <dd class="mt-2 font-display text-5xl leading-none font-semibold text-zinc-950 tabular-nums dark:text-white">{{ number_format($total['value']) }}</dd>
                    </div>
                    <span class="flex size-8 items-center justify-center rounded-full text-zinc-400 ring-1 ring-zinc-200 transition-all duration-(--dur-base) ease-(--ease-out) group-hover:-rotate-45 group-hover:bg-accent group-hover:text-accent-foreground group-hover:ring-accent dark:ring-white/10">
                        <flux:icon name="arrow-right" variant="micro" class="size-4" />
                    </span>
                </a>
            @endforeach
        </dl>

        @php($attentionCount = $this->teamsWithoutManager->count() + $this->advisorsWithoutTeam->count())

        <x-section :heading="__('Needs attention')" :count="$attentionCount ?: null" :description="__('Every advisor must belong to a team that has a manager.')" data-test="admin-overview-attention">
            @if ($attentionCount === 0)
                <x-empty-state icon="check-badge" :heading="__('Everything is in order')" :description="__('Every team has a manager and every advisor belongs to a team.')" data-test="admin-overview-empty" />
            @else
                <ul role="list" class="divide-y divide-zinc-200 dark:divide-white/8">
                    @foreach ($this->teamsWithoutManager as $team)
                        <li class="flex items-center gap-4 py-3.5">
                            <span class="size-2 shrink-0 rounded-full bg-attention ring-4 ring-attention/20"></span>
                            <div class="min-w-0 flex-1 text-sm">
                                <span class="font-medium text-zinc-950 dark:text-white">{{ $team->name }}</span>
                                <span class="text-zinc-500 dark:text-zinc-400">{{ __('has no manager') }}</span>
                            </div>
                            <flux:button size="sm" :href="route('admin.teams.edit', $team)" wire:navigate>{{ __('Assign manager') }}</flux:button>
                        </li>
                    @endforeach
                    @foreach ($this->advisorsWithoutTeam as $advisor)
                        <li class="flex items-center gap-4 py-3.5">
                            <span class="size-2 shrink-0 rounded-full bg-attention ring-4 ring-attention/20"></span>
                            <div class="min-w-0 flex-1 text-sm">
                                <span class="font-medium text-zinc-950 dark:text-white">{{ $advisor->name }}</span>
                                <span class="text-zinc-500 dark:text-zinc-400">{{ __('is not in a team') }}</span>
                            </div>
                            <flux:button size="sm" :href="route('admin.users.edit', $advisor)" wire:navigate>{{ __('Add to team') }}</flux:button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-section>
    </div>
</section>

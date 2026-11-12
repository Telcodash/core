<?php

use App\Data\UserTeam;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public function currentTeam(): ?array
    {
        $team = Auth::user()->currentTeam;

        return $team ? [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
        ] : null;
    }

    /**
     * @return Collection<int, UserTeam>
     */
    public function teams(): Collection
    {
        return Auth::user()->toUserTeams(includeCurrent: true);
    }

    public function switchTeam(string $slug): void
    {
        $user = Auth::user();

        abort_unless(
            $user->canAccessTeam($team = Team::where('slug', $slug)->firstOrFail()),
            403
        );

        $currentTeamSlug = $user->currentTeam?->slug;

        $user->switchTeam($team);

        if (! request()->header('Referer')) {
            $this->redirectRoute('dashboard', ['current_team' => $team->slug], navigate: true);

            return;
        }

        if (! $currentTeamSlug) {
            $this->redirect(request()->header('Referer'), navigate: true);

            return;
        }

        $redirectTo = $this->replaceCurrentTeamInReferer(
            request()->header('Referer'),
            $currentTeamSlug,
            $team->slug,
        );

        $this->redirect($redirectTo ?? request()->header('Referer'), navigate: true);
    }

    protected function replaceCurrentTeamInReferer(string $referer, string $currentTeamSlug, string $newTeamSlug): ?string
    {
        $redirectTo = preg_replace(
            '#/'.preg_quote($currentTeamSlug, '#').'(?=/|\?|$)#',
            '/'.$newTeamSlug,
            $referer,
            1,
        );

        return preg_replace(
            '#([?&]current_team=)'.preg_quote($currentTeamSlug, '#').'(?=&|$)#',
            '$1'.$newTeamSlug,
            $redirectTo ?? $referer,
            1,
        );
    }
}; ?>

<div>
    <flux:dropdown position="bottom" align="start" class="block w-full">
        <button type="button" class="group flex w-full items-center gap-3 rounded-xl bg-white/[0.06] p-2 text-start ring-1 ring-white/8 transition-colors duration-(--dur-fast) ring-inset hover:bg-white/10 in-data-flux-sidebar-collapsed-desktop:justify-center" data-test="team-switcher-trigger">
            @php($current = $this->currentTeam())
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-attention font-display text-sm font-semibold text-zinc-950" aria-hidden="true">
                {{ $current ? mb_strtoupper(mb_substr($current['name'], 0, 1)) : '?' }}
            </span>
            <span class="grid min-w-0 flex-1 leading-tight in-data-flux-sidebar-collapsed-desktop:hidden">
                <span class="text-[11px] text-white/45">{{ __('Team') }}</span>
                <span class="truncate text-sm font-medium text-white">{{ $current['name'] ?? __('Select team') }}</span>
            </span>
            <flux:icon
                name="chevrons-up-down"
                variant="micro"
                class="ms-auto size-4 text-white/40 transition-colors group-hover:text-white/80 in-data-flux-sidebar-collapsed-desktop:hidden"
            />
        </button>

        <flux:menu class="min-w-56">
            <flux:menu.heading>{{ __('Teams') }}</flux:menu.heading>

            @foreach ($this->teams() as $team)
                <flux:menu.item
                    wire:click="switchTeam('{{ $team->slug }}')"
                    class="cursor-pointer"
                    data-test="team-switcher-item"
                >
                    <div class="flex w-full items-center justify-between">
                        <span>{{ $team->name }}</span>
                        @if ($team->isCurrent)
                            <flux:icon name="check" class="size-4" />
                        @endif
                    </div>
                </flux:menu.item>
            @endforeach

            @can('create', App\Models\Team::class)
                <flux:menu.separator />

                <flux:modal.trigger name="create-team-switcher">
                    <flux:menu.item icon="plus" class="cursor-pointer" data-test="team-switcher-new-team">
                        {{ __('New team') }}
                    </flux:menu.item>
                </flux:modal.trigger>
            @endcan
        </flux:menu>
    </flux:dropdown>
</div>

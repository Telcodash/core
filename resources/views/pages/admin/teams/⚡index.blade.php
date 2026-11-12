<?php

use App\Actions\Teams\DeleteTeam;
use App\Livewire\AdminComponent;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('Teams')] class extends AdminComponent {
    use WithPagination;

    private const array SORTABLE = ['name', 'members_count', 'created_at'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'name')]
    public string $sortBy = 'name';

    #[Url(except: 'asc')]
    public string $sortDirection = 'asc';

    public ?int $deletingTeamId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
    }

    /**
     * @return LengthAwarePaginator<int, Team>
     */
    #[Computed]
    public function teams(): LengthAwarePaginator
    {
        $sortBy = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'name';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return Team::query()
            ->with('managers')
            ->withCount(['members', 'advisors'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy($sortBy, $direction)
            ->paginate(15);
    }

    #[Computed]
    public function deletingTeam(): ?Team
    {
        return $this->deletingTeamId ? Team::find($this->deletingTeamId) : null;
    }

    public function confirmDelete(int $teamId): void
    {
        $this->deletingTeamId = $teamId;

        Flux::modal('delete-team')->show();
    }

    public function deleteTeam(DeleteTeam $deleteTeam): void
    {
        $team = Team::findOrFail($this->deletingTeamId);

        try {
            $deleteTeam->handle($team);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        } finally {
            Flux::modal('delete-team')->close();
        }

        $this->reset('deletingTeamId');

        Flux::toast(variant: 'success', text: __('Team ":name" deleted.', ['name' => $team->name]));
    }
}; ?>

<section class="w-full">
    <x-admin.page-heading :heading="__('Teams')" :subheading="__('Create, edit and delete teams')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('admin.teams.create')" wire:navigate data-test="admin-create-team-button">
                {{ __('New team') }}
            </flux:button>
        </x-slot:actions>
    </x-admin.page-heading>

    <div class="mb-4 max-w-sm">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search teams...')" clearable data-test="admin-teams-search" />
    </div>

    <flux:table :paginate="$this->teams">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Managers') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'members_count'" :direction="$sortDirection" wire:click="sort('members_count')">{{ __('Members') }}</flux:table.column>
            <flux:table.column>{{ __('Advisors') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">{{ __('Created') }}</flux:table.column>
            <flux:table.column align="end"><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->teams as $team)
                <flux:table.row :key="$team->id" data-test="admin-team-row">
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('admin.teams.edit', $team)" wire:navigate>{{ $team->name }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($team->managers->isNotEmpty())
                            {{ $team->managers->pluck('name')->join(', ') }}
                        @elseif ($team->advisors_count > 0)
                            <flux:badge color="red" size="sm" icon="exclamation-triangle">{{ __('No manager') }}</flux:badge>
                        @else
                            <flux:text class="text-zinc-400">{{ __('None') }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $team->members_count }}</flux:table.cell>
                    <flux:table.cell>{{ $team->advisors_count }}</flux:table.cell>
                    <flux:table.cell>{{ $team->created_at?->format('j M Y') }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button variant="ghost" size="sm" icon="pencil-square" :href="route('admin.teams.edit', $team)" wire:navigate :tooltip="__('Edit')" />
                        <flux:button variant="ghost" size="sm" icon="trash" wire:click="confirmDelete({{ $team->id }})" :tooltip="__('Delete')" data-test="admin-delete-team-button" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">
                        {{ $search === '' ? __('No teams yet.') : __('No teams match your search.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="delete-team" class="max-w-lg">
        <form wire:submit="deleteTeam" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete team?') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('":name" will be deleted and all of its members removed from it. This cannot be undone.', ['name' => $this->deletingTeam?->name]) }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit" data-test="admin-delete-team-confirm">{{ __('Delete team') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

<?php

use App\Actions\Users\DeleteUser;
use App\Livewire\AdminComponent;
use App\Models\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Layout('layouts::admin')] #[Title('Users')] class extends AdminComponent {
    use WithPagination;

    private const array SORTABLE = ['name', 'email', 'created_at'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: 'name')]
    public string $sortBy = 'name';

    #[Url(except: 'asc')]
    public string $sortDirection = 'asc';

    public ?int $deletingUserId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
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
     * @return Collection<int, Role>
     */
    #[Computed]
    public function roles(): Collection
    {
        return Role::ordered();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $sortBy = in_array($this->sortBy, self::SORTABLE, true) ? $this->sortBy : 'name';
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return User::query()
            ->with('teams')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')))
            ->when(in_array($this->role, Role::NAMES, true), fn ($query) => $query->withRole($this->role))
            ->orderBy($sortBy, $direction)
            ->paginate(15);
    }

    #[Computed]
    public function deletingUser(): ?User
    {
        return $this->deletingUserId ? User::find($this->deletingUserId) : null;
    }

    public function confirmDelete(int $userId): void
    {
        $this->deletingUserId = $userId;

        Flux::modal('delete-user')->show();
    }

    public function deleteUser(DeleteUser $deleteUser): void
    {
        $user = User::findOrFail($this->deletingUserId);

        Flux::modal('delete-user')->close();

        if (Auth::user()->is($user)) {
            Flux::toast(variant: 'danger', text: __('You cannot delete your own account from here.'));

            return;
        }

        try {
            $deleteUser->handle($user);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->reset('deletingUserId');

        Flux::toast(variant: 'success', text: __('User ":name" deleted.', ['name' => $user->name]));
    }
}; ?>

<section class="w-full">
    <x-admin.page-heading :heading="__('Users')" :subheading="__('Create, edit and delete user accounts')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('admin.users.create')" wire:navigate data-test="admin-create-user-button">
                {{ __('New user') }}
            </flux:button>
        </x-slot:actions>
    </x-admin.page-heading>

    <div class="mb-4 flex flex-wrap gap-3">
        <div class="w-full max-w-sm">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name or email...')" clearable data-test="admin-users-search" />
        </div>
        <div class="w-48">
            <flux:select wire:model.live="role" data-test="admin-users-role-filter">
                <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
                @foreach ($this->roles as $roleOption)
                    <flux:select.option :value="$roleOption->name">{{ $roleOption->label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('Teams') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">{{ __('Created') }}</flux:table.column>
            <flux:table.column align="end"><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->users as $user)
                <flux:table.row :key="$user->id" data-test="admin-user-row">
                    <flux:table.cell variant="strong">
                        <div class="flex items-center gap-3">
                            <flux:avatar size="xs" :name="$user->name" :initials="$user->initials()" />
                            <flux:link :href="route('admin.users.edit', $user)" wire:navigate>{{ $user->name }}</flux:link>
                            @if (auth()->user()->is($user))
                                <flux:badge size="sm">{{ __('You') }}</flux:badge>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$user->role->badgeColor()">{{ $user->role->label }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="max-w-64 truncate">
                        @if ($user->teams->isNotEmpty())
                            {{ $user->teams->pluck('name')->join(', ') }}
                        @elseif ($user->role->requiresTeamManager())
                            <flux:badge color="red" size="sm" icon="exclamation-triangle">{{ __('No team') }}</flux:badge>
                        @else
                            <flux:text class="text-zinc-400">{{ __('None') }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->created_at?->format('j M Y') }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button variant="ghost" size="sm" icon="pencil-square" :href="route('admin.users.edit', $user)" wire:navigate :tooltip="__('Edit')" />
                        @unless (auth()->user()->is($user))
                            <flux:button variant="ghost" size="sm" icon="trash" wire:click="confirmDelete({{ $user->id }})" :tooltip="__('Delete')" data-test="admin-delete-user-button" />
                        @endunless
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">{{ __('No users match your filters.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="delete-user" class="max-w-lg">
        <form wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete user?') }}</flux:heading>
                <flux:text class="mt-2">{{ __(':name\'s account will be permanently deleted. This cannot be undone.', ['name' => $this->deletingUser?->name]) }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" type="submit" data-test="admin-delete-user-confirm">{{ __('Delete user') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

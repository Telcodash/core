@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand :name="config('app.name', 'TelcoDash')" {{ $attributes }}>
        <x-slot name="logo" class="logo-tile size-8 rounded-[0.6rem]">
            <x-app-logo-icon class="size-[18px]" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="config('app.name', 'TelcoDash')" {{ $attributes }}>
        <x-slot name="logo" class="logo-tile size-8 rounded-[0.6rem]">
            <x-app-logo-icon class="size-[18px]" />
        </x-slot>
    </flux:brand>
@endif

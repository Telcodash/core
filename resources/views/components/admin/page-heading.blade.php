@props(['heading', 'subheading' => null])

<div class="relative mb-6 w-full">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $heading }}</flux:heading>
            @if ($subheading)
                <flux:subheading size="lg">{{ $subheading }}</flux:subheading>
            @endif
        </div>

        @isset($actions)
            <div class="flex items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
    <flux:separator variant="subtle" class="mt-6" />
</div>

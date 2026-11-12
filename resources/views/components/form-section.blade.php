@props([
    'heading',
    'description' => null,
    'danger' => false,
])

{{-- A labelled band of a form: what it is on the left, the fields on the right. --}}
<div {{ $attributes->class('grid gap-x-12 gap-y-5 border-t border-zinc-200 py-9 first:border-t-0 first:pt-0 lg:grid-cols-[15rem_minmax(0,1fr)] dark:border-white/9') }}>
    <div class="space-y-1.5">
        <h2 @class([
            'font-display text-base font-semibold',
            'text-zinc-950 dark:text-white' => ! $danger,
            'text-red-600 dark:text-red-400' => $danger,
        ])>{{ $heading }}</h2>

        @if ($description)
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
        @endif
    </div>

    <div class="min-w-0 max-w-xl">
        {{ $slot }}
    </div>
</div>

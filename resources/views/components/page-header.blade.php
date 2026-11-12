@props([
    'heading',
    'description' => null,
])

{{-- Page title row. Large display type does the work a card would otherwise do. --}}
<header {{ $attributes->class('mb-10 flex flex-wrap items-end justify-between gap-x-8 gap-y-5 lg:mb-14') }}>
    <div class="min-w-0 space-y-3">
        <h1 class="font-display text-[2rem] leading-[1.08] font-semibold text-zinc-950 sm:text-[2.5rem] dark:text-white">{{ $heading }}</h1>

        @if ($description)
            <p class="max-w-2xl text-[0.95rem] text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
        @endif

        @isset($meta)
            <div class="flex flex-wrap items-center gap-2 pt-1">{{ $meta }}</div>
        @endisset
    </div>

    @isset($actions)
        <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
    @endisset
</header>

@props([
    'heading',
    'description' => null,
    'count' => null,
])

{{-- An open, borderless group of content: a display heading followed by a hairline that fades out. --}}
<section {{ $attributes->class('min-w-0') }}>
    <div class="mb-5 flex items-center gap-3">
        <h2 class="font-display text-lg font-semibold whitespace-nowrap text-zinc-950 dark:text-white">{{ $heading }}</h2>

        @if (! is_null($count))
            <span class="rounded-full bg-zinc-200/80 px-2 py-0.5 text-xs font-medium text-zinc-600 tabular-nums dark:bg-white/8 dark:text-zinc-300">{{ $count }}</span>
        @endif

        <span class="h-px min-w-6 flex-1 bg-linear-to-r from-zinc-300 to-transparent dark:from-white/14" aria-hidden="true"></span>

        @isset($actions)
            <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>

    @if ($description)
        <p class="-mt-2 mb-5 max-w-2xl text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
    @endif

    {{ $slot }}
</section>

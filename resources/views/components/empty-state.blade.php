@props([
    'icon' => 'inbox',
    'heading',
    'description' => null,
])

{{-- An empty state tells people what will appear here and how to get it there. --}}
<div {{ $attributes->class('flex items-start gap-4 rounded-2xl border border-dashed border-zinc-300 px-5 py-6 dark:border-white/12') }}>
    <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent/10 text-accent-content">
        <flux:icon :name="$icon" variant="outline" class="size-5" />
    </div>

    <div class="min-w-0 pt-0.5">
        <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $heading }}</h3>

        @if ($description)
            <p class="mt-1 max-w-md text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
        @endif

        @if ($slot->isNotEmpty())
            <div class="mt-4 flex flex-wrap items-center gap-2">{{ $slot }}</div>
        @endif
    </div>
</div>

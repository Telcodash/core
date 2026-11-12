@props(['icon' => null])

<span {{ $attributes->class('inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-medium text-zinc-600 ring-1 ring-zinc-200 dark:bg-white/5 dark:text-zinc-300 dark:ring-white/10') }}>
    @if ($icon)
        <flux:icon :name="$icon" variant="micro" class="size-3.5 text-zinc-400" />
    @endif
    {{ $slot }}
</span>

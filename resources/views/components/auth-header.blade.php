@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2">
    <h1 class="font-display text-[1.75rem] leading-tight font-semibold text-zinc-950 dark:text-white">{{ $title }}</h1>
    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
</div>

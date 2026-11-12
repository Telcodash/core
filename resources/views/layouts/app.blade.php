<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="mx-auto w-full max-w-6xl px-5! py-8! sm:px-8! lg:px-12! lg:py-12!">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>

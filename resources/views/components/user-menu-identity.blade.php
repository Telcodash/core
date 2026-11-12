<div class="flex items-center gap-3 px-2 py-2 text-start text-sm">
    <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" size="sm" color="auto" circle />
    <div class="grid min-w-0 flex-1 leading-tight">
        <span class="truncate font-medium text-zinc-900 dark:text-white">{{ auth()->user()->name }}</span>
        <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ auth()->user()->email }}</span>
    </div>
</div>

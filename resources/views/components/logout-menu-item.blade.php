<form method="POST" action="{{ route('logout') }}" class="w-full">
    @csrf
    <flux:menu.item
        as="button"
        type="submit"
        icon="arrow-right-start-on-rectangle"
        class="w-full cursor-pointer"
        data-test="logout-button"
    >
        {{ __('Log out') }}
    </flux:menu.item>
</form>

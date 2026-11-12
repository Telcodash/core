@props(['heading', 'subheading' => null])

<x-page-header :heading="$heading" :description="$subheading" {{ $attributes }}>
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset
</x-page-header>

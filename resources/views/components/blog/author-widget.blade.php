@props([
    'name' => 'Daniel Petrica',
    'title' => 'Laravel Developer',
    'bio' => 'Building products and sharing DevOps tips.',
    'initials' => 'DP',
    'twitterUrl' => '#',
])

<x-ui.card {{ $attributes->merge(['class' => 'p-6 text-center']) }}>
    <div class="w-20 h-20 bg-neutral-100 rounded-full mx-auto mb-4 overflow-hidden border-2 border-primary-100">
        <div class="w-full h-full flex items-center justify-center bg-primary-100 text-primary-600 font-bold text-xl">
            {{ $initials }}
        </div>
    </div>
    <h4 class="font-bold text-neutral-900 text-lg">{{ $name }}</h4>
    <p class="text-xs text-primary-600 font-bold uppercase tracking-wide mb-4">{{ $title }}</p>
    <p class="text-sm text-neutral-500 mb-6">{{ $bio }}</p>
    <div class="flex justify-center">
        <x-social-list />
    </div>
</x-ui.card>

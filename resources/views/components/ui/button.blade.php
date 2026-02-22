@props([
    'type' => 'button',
    'href' => null,
    'variant' => 'primary',
    'size' => 'md',
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-medium transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none';

    $variants = [
        'primary' => 'text-white bg-primary-600 hover:bg-primary-700 focus:ring-primary-500',
        'secondary' => 'text-neutral-700 bg-white border border-neutral-200 hover:bg-neutral-50 hover:border-neutral-300 focus:ring-neutral-500',
        'dark' => 'text-white bg-neutral-900 hover:bg-neutral-800 focus:ring-neutral-900',
        'ghost' => 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 focus:ring-neutral-500',
    ];

    $sizes = [
        'xs' => 'px-2.5 py-1.5 text-xs rounded',
        'sm' => 'px-3 py-2 text-sm rounded-md',
        'md' => 'px-4 py-2 text-sm rounded-lg',
        'lg' => 'px-6 py-3 text-base rounded-lg',
    ];

    $classes = $baseClasses . ' ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif

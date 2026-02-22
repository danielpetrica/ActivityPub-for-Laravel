@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
])

@php
    $baseClasses = 'inline-flex items-center font-bold uppercase tracking-wider rounded-full transition-colors';

    $variants = [
        'primary' => 'bg-primary-50 text-primary-700 hover:bg-primary-100',
        'secondary' => 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200',
        'success' => 'bg-green-50 text-green-700 hover:bg-green-100',
        'danger' => 'bg-red-50 text-red-700 hover:bg-red-100',
        'warning' => 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100',
        'dark' => 'bg-neutral-900 text-white hover:bg-neutral-800',
    ];

    $sizes = [
        'xs' => 'px-2 py-0.5 text-[10px]',
        'sm' => 'px-2.5 py-0.5 text-xs',
        'md' => 'px-3 py-1 text-xs',
    ];

    $classes = $baseClasses . ' ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <span {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </span>
@endif

@props([
    'variant' => 'info',
])

@php
    $variants = [
        'info' => [
            'bg' => 'bg-blue-50',
            'border' => 'border-blue-200',
            'text' => 'text-blue-800',
            'icon' => 'info',
            'iconColor' => 'text-blue-400',
        ],
        'success' => [
            'bg' => 'bg-green-50',
            'border' => 'border-green-200',
            'text' => 'text-green-800',
            'icon' => 'check-circle',
            'iconColor' => 'text-green-400',
        ],
        'warning' => [
            'bg' => 'bg-yellow-50',
            'border' => 'border-yellow-200',
            'text' => 'text-yellow-800',
            'icon' => 'alert-triangle',
            'iconColor' => 'text-yellow-400',
        ],
        'danger' => [
            'bg' => 'bg-red-50',
            'border' => 'border-red-200',
            'text' => 'text-red-800',
            'icon' => 'alert-circle',
            'iconColor' => 'text-red-400',
        ],
    ];

    $currentVariant = $variants[$variant] ?? $variants['info'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border p-4 ' . $currentVariant['bg'] . ' ' . $currentVariant['border'] . ' ' . $currentVariant['text']]) }} role="alert">
    <div class="flex">
        <div class="flex-shrink-0">
            <x-ui.icon name="{{ $currentVariant['icon'] }}" class="h-5 w-5 {{ $currentVariant['iconColor'] }}" />
        </div>
        <div class="ml-3">
            <div class="text-sm font-medium">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>

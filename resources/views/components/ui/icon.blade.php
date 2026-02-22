@props(['name', 'size' => 4])

<i data-lucide="{{ $name }}" {{ $attributes->merge(['class' => "w-{$size} h-{$size}"]) }} aria-hidden="true"></i>

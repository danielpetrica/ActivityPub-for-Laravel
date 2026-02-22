@props([
    'label' => null,
])

<label class="inline-flex items-center cursor-pointer group">
    <div class="relative">
        <input type="checkbox" {{ $attributes->merge(['class' => 'sr-only peer']) }}>
        <div class="w-5 h-5 bg-white border border-neutral-300 rounded transition-all peer-checked:bg-primary-600 peer-checked:border-primary-600 group-hover:border-primary-400"></div>
        <x-ui.icon
            name="check"
            class="absolute inset-0 w-3.5 h-3.5 m-auto text-white opacity-0 transition-opacity peer-checked:opacity-100"
            stroke-width="4"
        />
    </div>
    @if($label || $slot->isNotEmpty())
        <span class="ml-3 text-sm font-medium text-neutral-700 group-hover:text-neutral-900 transition-colors">
            {{ $label ?? $slot }}
        </span>
    @endif
</label>

@props([
    'options' => [],
    'selected' => null,
])

<select {{ $attributes->merge(['class' => 'w-full px-4 py-2 text-sm bg-white border border-neutral-300 rounded-lg outline-none transition-all focus:border-primary-500 focus:ring-2 focus:ring-primary-200 appearance-none']) }}>
    @if($slot->isNotEmpty())
        {{ $slot }}
    @else
        @foreach($options as $value => $label)
            <option value="{{ $value }}" @selected($selected == $value)>
                {{ $label }}
            </option>
        @endforeach
    @endif
</select>

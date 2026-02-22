@props([
    'items' => [] // Array of ['label' => '...', 'url' => '...']
])

<nav {{ $attributes->merge(['class' => 'flex text-sm font-medium text-neutral-500']) }}>
    <ol class="flex items-center space-x-2">
        @foreach($items as $item)
            @if(!$loop->last)
                <li>
                    <a href="{{ $item['url'] }}" class="hover:text-primary-600 transition-colors">
                        {{ $item['label'] }}
                    </a>
                </li>
                <li><span class="text-neutral-300">/</span></li>
            @else
                <li class="text-neutral-900">{{ $item['label'] }}</li>
            @endif
        @endforeach
    </ol>
</nav>

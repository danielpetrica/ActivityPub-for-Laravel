@props([
    'items' => [] // Array of ['label' => '...', 'id' => '...', 'active' => false]
])

<x-ui.card {{ $attributes->merge(['class' => 'p-6']) }}>
    <h4 class="font-bold text-neutral-900 mb-4 text-sm uppercase tracking-wide">On this page</h4>
    <ul class="space-y-3 text-sm border-l-2 border-neutral-100">
        @foreach($items as $item)
            <li>
                <a href="#{{ $item['id'] }}"
                   @class([
                       'block pl-4 transition-colors',
                       'text-primary-600 border-l-2 -ml-[2px] border-primary-500 font-medium' => $item['active'] ?? false,
                       'text-neutral-500 hover:text-neutral-900' => !($item['active'] ?? false),
                   ])>
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</x-ui.card>

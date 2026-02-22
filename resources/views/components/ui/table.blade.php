@props([
    'headers' => [],
])

<div class="overflow-hidden border border-neutral-200 rounded-xl">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-neutral-200']) }}>
        <thead class="bg-neutral-50">
            <tr>
                @foreach($headers as $header)
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-neutral-500 uppercase tracking-wider">
                        {{ $header }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-neutral-200">
            {{ $slot }}
        </tbody>
    </table>
</div>

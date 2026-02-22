@props([
    'id',
    'title' => null,
])

<dialog
    id="{{ $id }}"
    {{ $attributes->merge(['class' => 'fixed inset-0 z-50 m-auto backdrop:bg-neutral-900/50 backdrop:backdrop-blur-sm rounded-2xl shadow-2xl border border-neutral-100 p-0 overflow-hidden w-full max-w-lg bg-white']) }}
>
    <div class="flex flex-col h-full">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-neutral-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-neutral-900">
                {{ $title ?? $slot_title ?? '' }}
            </h3>
            <button
                type="button"
                onclick="document.getElementById('{{ $id }}').close()"
                class="p-2 rounded-lg text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 transition-colors"
                aria-label="Close modal"
            >
                <x-ui.icon name="x" class="w-5 h-5" />
            </button>
        </div>

        <!-- Body -->
        <div class="px-6 py-6 overflow-y-auto">
            {{ $slot }}
        </div>

        <!-- Footer -->
        @if (isset($footer))
            <div class="px-6 py-4 bg-neutral-50 border-t border-neutral-100 flex justify-end gap-3">
                {{ $footer }}
            </div>
        @endif
    </div>
</dialog>

<script>
    // Close on click outside
    document.getElementById('{{ $id }}').addEventListener('click', function(e) {
        if (e.target === this) {
            this.close();
        }
    });
</script>

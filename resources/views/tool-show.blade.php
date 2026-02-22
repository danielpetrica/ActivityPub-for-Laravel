<x-layouts.app
    :title="$tool->seo_metadata['title'] ?? ($tool->name . ' - Daniel Petrica')"
    :description="$tool->seo_metadata['description'] ?? 'Try this interactive tool.'"
>
    <x-layouts.hero
        :title="$tool->name"
        :excerpt="$tool->seo_metadata['description'] ?? 'Try this interactive tool.'"
    />

    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-neutral-50 rounded-2xl p-8 border border-neutral-100 shadow-sm">
                {!! $tool->html_content !!}
            </div>

            <div class="mt-12 p-6 bg-primary-50 rounded-xl border border-primary-100">
                <h3 class="text-lg font-bold text-primary-900 mb-2">About this tool</h3>
                <p class="text-primary-800">
                    This is one of the custom tools I've built to help with daily tech tasks. Feel free to use it and share it!
                </p>
            </div>
        </div>
    </section>

    {{-- View Tracker --}}
    <img src="{{ route('tracker', ['type' => 'tool', 'slug' => $tool->slug]) }}" alt="" class="hidden" aria-hidden="true">
</x-layouts.app>

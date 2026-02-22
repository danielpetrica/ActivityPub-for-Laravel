@props(['caseStudy'])

<div class="bg-white rounded-2xl p-1 group focus:outline-none focus:ring-2 focus:ring-primary-500 hover:ring-2 hover:ring-primary-500 hover:ring-offset-2 hover:ring-offset-neutral-50 transition-all duration-200 shadow-sm">
    <article class="bg-white rounded-xl overflow-hidden border border-neutral-100 flex flex-col h-full relative">
        @if($caseStudy->images && count($caseStudy->images) > 0)
            <div class="aspect-video overflow-hidden bg-neutral-100">
                <img
                    src="{{ Storage::url($caseStudy->images[0]) }}"
                    alt="{{ $caseStudy->title }}"
                    class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-500"
                >
            </div>
        @endif

        <div class="p-8 flex-1 flex flex-col">
            <h3 class="text-2xl font-bold text-neutral-900 mb-4 group-hover:text-primary-600 transition-colors">
                {{ $caseStudy->title }}
            </h3>

            <div class="prose prose-sm text-neutral-600 mb-6 flex-1">
                {!! $caseStudy->description !!}
            </div>

            @if($caseStudy->services->isNotEmpty())
                <div class="flex flex-wrap gap-2 mt-auto pt-6 border-t border-neutral-50">
                    @foreach($caseStudy->services as $service)
                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded bg-neutral-100 text-neutral-500">
                            {{ $service->name }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </article>
</div>

@props([
    'title',
    'image' => null,
    'excerpt' => null,
    'date' => null,
    'readTime' => null,
    'tags' => null,
    'url' => '#',
])

<article
    itemscope
    itemtype="http://schema.org/BlogPosting"
    {{ $attributes->class(['group bg-white rounded-2xl overflow-hidden border border-neutral-100 shadow-sm hover:shadow-xl hover:shadow-primary-500/10 transition-all duration-300']) }}
>
    <a href="{{ $url }}" class="block aspect-[1200/630] overflow-hidden bg-neutral-50 relative">
        @if($image)
            <img
                itemprop="image"
                src="{{ $image }}"
                alt="{{ $title }}"
                class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-500"
            >
        @else
            <div class="w-full h-full bg-neutral-800 flex items-center justify-center">
                <x-ui.icon name="code-2" size="12" class="text-neutral-600" />
            </div>
        @endif
    </a>

    {{-- Tags (outside the link) --}}
    @if($tags && $tags->isNotEmpty())
        <div class="flex flex-wrap gap-2 px-6 lg:px-8 pt-4">
            @foreach($tags->take(3) as $tag)
                <a
                    href="{{ route('tags.show', $tag->slug) }}"
                    class="bg-neutral-100 font-bold text-xs px-2.5 py-1 rounded text-primary-600 hover:bg-primary-600 hover:text-white transition-all"
                    itemprop="articleSection"
                >
                    {{ $tag->name }}
                </a>
            @endforeach
        </div>
    @endif

    <div class="p-6 lg:p-8">
        <div class="flex items-center gap-3 text-sm text-neutral-500 mb-3">
            @if($date)
                <time itemprop="datePublished" datetime="{{ $date }}">{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</time>
            @endif
            @if($readTime)
                <span class="text-neutral-300">•</span>
                <span>{{ $readTime }}</span>
            @endif
        </div>

        <h2 class="text-2xl lg:text-3xl font-bold text-neutral-900 mb-4 group-hover:text-primary-600 transition-colors" itemprop="headline">
            {{ $title }}
        </h2>

        @if($excerpt)
            <p class="text-neutral-600 leading-relaxed mb-6" itemprop="description">
                {{ $excerpt }}
            </p>
        @endif

        <span class="inline-flex items-center gap-2 font-semibold text-primary-600">
            Read Article
            <x-ui.icon name="arrow-right" class="h-4 w-4" />
        </span>
    </div>
</article>

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

        @if($tags && $tags->isNotEmpty())
            <div class="absolute top-4 left-4 flex flex-wrap gap-2">
                @foreach($tags->take(3) as $tag)
                    <a
                        href="{{ route('tags.show', $tag->slug) }}"
                        class="bg-white/90 backdrop-blur font-bold text-xs px-2.5 py-1 rounded text-primary-600 shadow-sm hover:bg-primary-600 hover:text-white transition-all"
                        itemprop="articleSection"
                    >
                        {{ $tag->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </a>

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

        <h2 class="text-2xl lg:text-3xl font-extrabold text-neutral-900 mb-4 group-hover:text-primary-600 transition-colors" itemprop="headline">
            <a href="{{ $url }}">
                {{ $title }}
            </a>
        </h2>

        @if($excerpt)
            <p class="text-neutral-600 leading-relaxed mb-6" itemprop="description">
                {{ $excerpt }}
            </p>
        @endif

        <a href="{{ $url }}" class="inline-flex items-center gap-2 font-semibold text-primary-600 hover:text-primary-700 transition-colors">
            Read Article
            <x-ui.icon name="arrow-right" class="h-4 w-4" />
        </a>
    </div>
</article>

@props([
    'title',
    'image' => null,
    'date' => null,
    'readTime' => null,
    'tags' => null,
    'url' => '#',
    'excerpt' => null,
    'layout' => 'grid', // 'grid' (horizontal), 'vertical' (stacked), 'carousel'
    'imagePosition' => 'left', // 'left', 'right'
])

@php
    $isCarousel = $layout === 'carousel';
    $isVertical = $layout === 'vertical';

    $wrapperClasses = $isCarousel
        ? 'min-w-[280px] sm:min-w-[320px] group rounded-xl p-1 focus:outline-none focus:ring-2 focus:ring-primary-500 hover:ring-2 hover:ring-primary-500 hover:ring-offset-2 hover:ring-offset-neutral-50 transition-all duration-200'
        : 'block group rounded-2xl p-1 focus:outline-none focus:ring-2 focus:ring-primary-500 hover:ring-2 hover:ring-primary-500 hover:ring-offset-2 hover:ring-offset-neutral-50 transition-all duration-200 h-full';

    $imageOrderClass = $imagePosition === 'right' ? 'sm:order-2' : 'sm:order-1';

    // Vertical cards stack the image above the content; horizontal cards
    // keep the image on the side. Carousel cards use a floating image.
    $articleClasses = $isCarousel
        ? 'relative h-full'
        : 'bg-white rounded-xl overflow-hidden border border-neutral-100 shadow-sm group-hover:shadow-xl group-hover:shadow-primary-500/10 transition-all duration-300 flex flex-col h-full relative' . ($isVertical ? '' : ' sm:flex-row');

    $imageWrapperClasses = match (true) {
        $isCarousel => 'relative aspect-[1200/630] overflow-hidden rounded-xl mb-4 shadow-sm group-hover:shadow-md transition-shadow bg-neutral-50',
        $isVertical => 'relative aspect-[1200/630] w-full shrink-0 overflow-hidden bg-neutral-50',
        default => 'relative h-48 sm:h-auto sm:min-h-full sm:w-48 md:w-56 lg:w-64 shrink-0 overflow-hidden bg-neutral-50 ' . $imageOrderClass,
    };

    $contentClasses = match (true) {
        $isCarousel => '',
        $isVertical => 'p-6 flex-1 flex flex-col',
        default => 'p-6 flex-1 flex flex-col sm:min-h-full ' . ($imagePosition === 'right' ? 'sm:order-1' : 'sm:order-2'),
    };
@endphp

<div class="{{ $wrapperClasses }}" itemscope itemtype="http://schema.org/BlogPosting">
    <article class="{{ $articleClasses }}">
        <div class="{{ $imageWrapperClasses }}">
            <a href="{{ $url }}" class="absolute inset-0 z-20"></a>
            @if($image)
                <img
                    itemprop="image"
                    src="{{ $image }}"
                    alt="{{ $title }}"
                    class="w-full h-full object-contain transform group-hover:scale-105 transition-transform duration-500"
                >
            @else
                <div class="w-full h-full bg-neutral-800 flex items-center justify-center">
                    <x-ui.icon name="code-2" size="12" class="text-neutral-600" />
                </div>
            @endif

            @if($tags && $tags->isNotEmpty())
                <div class="absolute top-4 left-4 z-30 flex flex-wrap gap-2">
                    @foreach($tags as $tag)
                        <a href="{{ route('tags.show', $tag->slug) }}" class="bg-white/90 backdrop-blur font-bold text-[10px] px-2 py-0.5 rounded text-primary-600 shadow-sm hover:bg-primary-600 hover:text-white transition-all" itemprop="articleSection">
                            {{ $tag->name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="{{ $contentClasses }}">
            @if(! $isCarousel)
                <div class="flex items-center gap-2 text-xs text-neutral-400 mb-3">
                    @if($date)
                        <time itemprop="datePublished" datetime="{{ $date }}">{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</time>
                    @endif
                    @if($readTime)
                        <span>•</span>
                        <span>{{ $readTime }}</span>
                    @endif
                </div>
            @endif

            <h3 class="{{ $isCarousel ? 'font-bold text-lg text-neutral-900 group-hover:text-primary-600 line-clamp-2 mb-1 transition-colors' : 'text-xl font-bold text-neutral-900 mb-3 group-hover:text-primary-600 transition-colors' }}" itemprop="headline">
                <a href="{{ $url }}" class="hover:text-primary-600 transition-colors">
                    {{ $title }}
                </a>
            </h3>

            @if($isCarousel)
                @if($date)
                    <p class="text-sm text-neutral-500"><time itemprop="datePublished" datetime="{{ $date }}">{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</time></p>
                @endif
            @else
                @if($excerpt)
                    <p class="text-neutral-600 text-sm leading-relaxed mb-4 flex-1" itemprop="description">
                        {{ $excerpt }}
                    </p>
                @endif
                <a href="{{ $url }}" class="inline-flex items-center text-sm font-semibold text-primary-600 hover:text-primary-700">
                    Read more <x-ui.icon name="arrow-right" class="ml-1" />
                </a>
            @endif
        </div>
    </article>
</div>

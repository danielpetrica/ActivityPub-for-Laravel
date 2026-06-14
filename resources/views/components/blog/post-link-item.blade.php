@props([
    'title',
    'image' => null,
    'date' => null,
    'readTime' => null,
    'tags' => null,
    'url' => '#',
])

<article
    itemscope
    itemtype="http://schema.org/BlogPosting"
    {{ $attributes->class(['flex gap-4 py-4 group border-b border-neutral-100 last:border-b-0']) }}
>
    <a href="{{ $url }}" class="shrink-0 w-16 h-16 rounded-lg overflow-hidden bg-neutral-50 border border-neutral-100">
        @if($image)
            <img
                itemprop="image"
                src="{{ $image }}"
                alt="{{ $title }}"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            >
        @else
            <div class="w-full h-full bg-neutral-800 flex items-center justify-center">
                <x-ui.icon name="code-2" size="5" class="text-neutral-600" />
            </div>
        @endif
    </a>

    <div class="flex-1 min-w-0">
        <h3 class="font-semibold text-neutral-900 group-hover:text-primary-600 transition-colors line-clamp-2" itemprop="headline">
            <a href="{{ $url }}">
                {{ $title }}
            </a>
        </h3>

        <div class="flex items-center gap-2 mt-1 text-xs text-neutral-500">
            @if($date)
                <time itemprop="datePublished" datetime="{{ $date }}">{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</time>
            @endif
            @if($readTime)
                <span class="text-neutral-300">•</span>
                <span>{{ $readTime }}</span>
            @endif
        </div>

        @if($tags && $tags->isNotEmpty())
            <div class="flex flex-wrap gap-1.5 mt-1.5">
                @foreach($tags->take(2) as $tag)
                    <a
                        href="{{ route('tags.show', $tag->slug) }}"
                        class="text-[10px] font-semibold text-primary-600 bg-primary-50 px-1.5 py-0.5 rounded hover:bg-primary-100 transition-colors"
                    >
                        {{ $tag->name }}
                    </a>
                @endforeach
                @if($tags->count() > 2)
                    <span class="text-[10px] text-neutral-400">+{{ $tags->count() - 2 }}</span>
                @endif
            </div>
        @endif
    </div>
</article>

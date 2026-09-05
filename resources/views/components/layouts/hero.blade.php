@props([
    'title',
    'excerpt' => null,
    'image' => null,
    'date' => null,
    'readTime' => null,
    'section' => null,
    'tags' => null,
    'url' => '#',
    'featured' => false,
    'compact' => false,
    'schemaType' => null,
    'breadcrumbs' => null,
])

<header {{ $attributes->merge(['class' => $featured ? 'relative bg-white pt-12 pb-16 lg:pt-20 lg:pb-24 overflow-hidden group/hero' : 'bg-white pt-10 pb-12 lg:pt-16 lg:pb-16 border-b border-neutral-100']) }} @if($schemaType) itemscope itemtype="{{ $schemaType }}" @endif>
@if($featured)
    <div class="absolute inset-0 opacity-30 pointer-events-none" aria-hidden="true">
        <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-primary-100 blur-3xl"></div>
        <div class="absolute top-1/2 left-0 w-72 h-72 rounded-full bg-orange-100 blur-3xl"></div>
    </div>
@endif

    <div class="{{ $featured ? 'max-w-7xl' : 'max-w-4xl' }} mx-auto px-4 sm:px-6 lg:px-8 relative z-10 {{ $featured ? '' : 'text-center' }}">
        @if(!$featured && $breadcrumbs)
            <div class="mb-8 flex justify-center">
                <x-blog.breadcrumbs :items="$breadcrumbs" />
            </div>
        @endif

        @if($featured)
            <div class="grid lg:grid-cols-12 gap-12 items-center">
                <div class="lg:col-span-7 order-2 lg:order-1 relative">
                    <a href="{{ $url }}" class="block group/hero-link">
                        <span class="block text-sm font-bold uppercase tracking-wider text-primary-600 mb-4">Latest</span>
                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-neutral-900 tracking-tight leading-tight mb-6 group-hover/hero-link:text-primary-600 transition-colors" itemprop="headline">
                            {!! $title !!}
                        </h1>
                        @if($excerpt)
                            <p class="text-lg sm:text-xl text-neutral-600 mb-8 leading-relaxed max-w-2xl" itemprop="description">
                                {{ $excerpt }}
                            </p>
                        @endif
                    </a>

                    <div class="flex flex-wrap items-center gap-6 text-sm text-neutral-500 font-medium mb-8">
                        @if($date)
                            <div class="flex items-center gap-2">
                                <x-ui.icon name="calendar" class="text-primary-500" />
                                <time itemprop="datePublished" datetime="{{ $date }}">{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</time>
                            </div>
                        @endif
                        @if($readTime)
                            <div class="flex items-center gap-2">
                                <x-ui.icon name="clock" class="text-primary-500" />
                                <span>{{ $readTime }}</span>
                            </div>
                        @endif
                        @if($tags && $tags->isNotEmpty())
                            <div class="flex items-center gap-2 relative z-30">
                                <x-ui.icon name="tag" class="text-primary-500" />
                                <div class="flex gap-2">
                                    @foreach($tags as $tag)
                                        <a href="{{ route('tags.show', $tag->slug) }}" class="text-neutral-900 hover:text-primary-600 transition-colors" itemprop="articleSection">{{ $tag->name }}</a>
                                        @if(!$loop->last) <span class="text-neutral-300">/</span> @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex gap-4">
                        <x-ui.button :href="$url" variant="dark" size="lg" class="relative z-30">
                            Read Article
                            <x-ui.icon name="arrow-right" class="ml-2" />
                        </x-ui.button>
                    </div>
                </div>

                @if($image)
                    <div class="lg:col-span-5 order-1 lg:order-2">
                        <a href="{{ $url }}" class="block group/hero-image relative">
                            <div class="absolute inset-0 bg-primary-600 rounded-2xl transition-transform opacity-20"></div>
                            <img itemprop="image" src="{{ $image }}" alt="{{ strip_tags($title) }}" class="relative rounded-2xl shadow-2xl w-full h-auto object-contain bg-neutral-50 aspect-[1200/630] border border-neutral-100 group-hover/hero-image:scale-[1.02] transition-transform duration-500">
                        </a>
                    </div>
                @endif
            </div>
        @else
            @if($section)
                <div class="mb-4">
                    <span class="text-sm font-bold uppercase tracking-wider text-primary-600" itemprop="articleSection">
                        {{ $section }}
                    </span>
                </div>
            @endif

            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-neutral-900 tracking-tight leading-tight mb-6" itemprop="headline">
                {!! $title !!}
            </h1>

            @if($excerpt)
                <p class="text-lg text-neutral-600 max-w-2xl mx-auto mb-8 leading-relaxed">
                    {!! $excerpt !!}
                </p>
            @endif

            <div class="flex flex-wrap items-center justify-center gap-6 text-sm text-neutral-500 font-medium mb-10">
                @if($date)
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="calendar" class="text-primary-500" />
                        <time itemprop="datePublished" datetime="{{ $date }}">{{ \Carbon\Carbon::parse($date)->format('M d, Y') }}</time>
                    </div>
                @endif
                @if($readTime)
                    <div class="flex items-center gap-2">
                        <x-ui.icon name="clock" class="text-primary-500" />
                        <span>{{ $readTime }}</span>
                    </div>
                @endif
                {{ $meta ?? '' }}
            </div>
        @endif
    </div>

    @if(!$featured && $image)
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="relative rounded-2xl overflow-hidden shadow-2xl shadow-primary-500/10 aspect-[1200/630] bg-neutral-50">
                <img itemprop="image" src="{{ $image }}" alt="{{ strip_tags($title) }}" class="w-full h-full object-contain">
                <div class="absolute inset-0 bg-gradient-to-t from-neutral-900/40 to-transparent"></div>
            </div>
        </div>
    @endif
</header>

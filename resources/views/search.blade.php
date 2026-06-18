<x-layouts.app
    :title="$query ? 'Search: ' . $query : 'Search'"
    description="Search results for articles, pages, and tags."
    ogType="website"
>
    <x-layouts.hero
        :title="$query ? 'Search: ' . e($query) : 'Search'"
        excerpt="Search across all articles, pages, and tags."
    />

    <section class="py-16 lg:py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <form action="{{ route('search') }}" method="GET" class="mb-12">
                <div class="relative">
                    <x-ui.icon name="search" class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-neutral-400" />
                    <input
                        type="search"
                        name="q"
                        value="{{ e($query) }}"
                        placeholder="Search articles, pages, tags..."
                        class="w-full pl-12 pr-4 py-3 text-lg bg-neutral-100 rounded-xl border border-transparent focus:border-primary-500 focus:bg-white focus:ring-4 focus:ring-primary-500/10 outline-none transition-all"
                    >
                </div>
            </form>

            @if($query)
                @if($posts->isEmpty() && $pages->isEmpty() && $tags->isEmpty())
                    <div class="text-center py-16">
                        <x-ui.icon name="search-x" class="h-12 w-12 text-neutral-300 mx-auto mb-4" />
                        <h2 class="text-xl font-bold text-neutral-800 mb-2">No results found</h2>
                        <p class="text-neutral-500">Try a different search term.</p>
                    </div>
                @else
                    @if($posts->isNotEmpty())
                        <h2 class="text-xl font-extrabold text-neutral-900 mb-6">Articles ({{ $posts->count() }})</h2>
                        <div class="space-y-6 mb-12">
                            @foreach($posts as $post)
                                <a href="{{ route('posts.show', $post->slug) }}" class="block p-6 bg-neutral-50 rounded-xl hover:bg-neutral-100 transition-colors">
                                    <h3 class="font-bold text-neutral-900">{{ $post->title }}</h3>
                                    @if($post->excerpt || $post->meta_description)
                                        <p class="mt-2 text-neutral-500 text-sm">{{ Str::limit($post->excerpt ?? $post->meta_description, 200) }}</p>
                                    @endif
                                    <div class="mt-3 flex items-center gap-2">
                                        @foreach($post->tags as $tag)
                                            <span class="text-xs font-medium text-primary-600 bg-primary-50 px-2 py-0.5 rounded">{{ $tag->name }}</span>
                                        @endforeach
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if($pages->isNotEmpty())
                        <h2 class="text-xl font-extrabold text-neutral-900 mb-6">Pages ({{ $pages->count() }})</h2>
                        <div class="space-y-4 mb-12">
                            @foreach($pages as $page)
                                <a href="{{ route('pages.show', $page->slug) }}" class="block p-4 bg-neutral-50 rounded-xl hover:bg-neutral-100 transition-colors">
                                    <h3 class="font-bold text-neutral-900">{{ $page->title }}</h3>
                                    @if($page->excerpt || $page->meta_description)
                                        <p class="mt-1 text-neutral-500 text-sm">{{ Str::limit($page->excerpt ?? $page->meta_description, 200) }}</p>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if($tags->isNotEmpty())
                        <h2 class="text-xl font-extrabold text-neutral-900 mb-6">Tags ({{ $tags->count() }})</h2>
                        <div class="flex flex-wrap gap-3 mb-12">
                            @foreach($tags as $tag)
                                <a href="{{ route('tags.show', $tag->slug) }}" class="inline-block px-4 py-2 bg-neutral-50 rounded-xl hover:bg-primary-50 hover:text-primary-600 transition-colors font-medium text-neutral-700">
                                    {{ $tag->name }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endif
            @endif
        </div>
    </section>
</x-layouts.app>

@php
    use App\Classes\Business\OgImageBusiness;

    $metaImage = OgImageBusiness::generateForHomepage();
@endphp

<x-layouts.app
    title="Daniel Petrica - Tech Blog"
    description="Personal blog project for Daniel Petrica, focusing on tech articles."
    :metaImage="$metaImage"
>
    @if($featuredPost)
        <x-layouts.hero
            featured
            :title="$featuredPost->title"
            :excerpt="$featuredPost->excerpt ?? $featuredPost->meta_description ?? ''"
            :date="$featuredPost->published_at?->toDateString()"
            readTime="5 min read"
            :tags="$featuredPost->tags"
            :url="route('posts.show', $featuredPost->slug)"
            :image="$featuredPost->feature_image_path ? Storage::url($featuredPost->feature_image_path) : null"
        />
    @endif

    <section class="py-20 bg-neutral-50 border-y border-neutral-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-12 flex justify-between items-end">
                <div>
                    <h2 class="text-3xl font-extrabold text-neutral-900 mb-4">Top Articles</h2>
                    <p class="text-neutral-500">Most popular insights and analysis from the blog.</p>
                </div>
            </div>

            <div class="flex overflow-x-auto pb-8 gap-8 no-scrollbar snap-x">
                @foreach($topPosts as $post)
                    <div class="snap-start">
                        <x-blog.article-card
                            layout="carousel"
                            :title="$post->title"
                            :date="$post->published_at?->toDateString()"
                            :tags="$post->tags"
                            :url="route('posts.show', $post->slug)"
                            :image="$post->feature_image_path ? Storage::url($post->feature_image_path) : null"
                        />
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-primary-600 py-10 text-white overflow-hidden relative">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-white blur-3xl"></div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 rounded-full bg-white blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <x-forms.newsletter layout="inline" slug="homepage" class="text-white" />
        </div>
    </section>

    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-12 flex justify-between items-end">
                <div>
                    <h2 class="text-3xl font-extrabold text-neutral-900 mb-4">Recent Articles</h2>
                    <p class="text-neutral-500">Stay up to date with the latest insights and tutorials.</p>
                </div>
                @if($popularTags->isNotEmpty())
                    <div class="hidden md:flex items-center gap-4">
                        <span class="text-sm font-bold text-neutral-400 uppercase tracking-wider">Popular Tags:</span>
                        <div class="flex gap-2">
                            @foreach($popularTags as $tag)
                                <x-ui.badge href="{{ route('tags.show', $tag->slug) }}" variant="secondary" size="sm">
                                    {{ $tag->name }}
                                </x-ui.badge>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-8">
                @foreach($recentPosts as $post)
                    <x-blog.article-card
                        :title="$post->title"
                        :excerpt="$post->excerpt ?? $post->meta_description ?? ''"
                        :date="$post->published_at?->toDateString()"
                        readTime="8 min"
                        :tags="$post->tags"
                        :url="route('posts.show', $post->slug)"
                        :image="$post->feature_image_path ? Storage::url($post->feature_image_path) : null"
                    />
                @endforeach
            </div>

            <div class="mt-16 text-center">
                <x-ui.button href="{{ route('posts.index') }}" variant="outline" size="lg">
                    View All Articles
                    <x-ui.icon name="arrow-right" class="ml-2" />
                </x-ui.button>
            </div>
        </div>
    </section>
</x-layouts.app>

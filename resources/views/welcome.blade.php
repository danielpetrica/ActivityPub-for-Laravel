@php
    use App\Classes\Business\MediaUrlBusiness;
@endphp

<x-layouts.app
    title="Daniel Petrica - Tech Blog"
    description="Personal blog project for Daniel Petrica, focusing on tech articles."
    :metaImage="$metaImage"
>
    @if($featuredPost)
        <x-layouts.hero
            featured
            :schemaType="'https://schema.org/WebSite'"
            :title="$featuredPost->title"
            :excerpt="$featuredPost->excerpt ?? $featuredPost->meta_description ?? ''"
            :date="$featuredPost->published_at?->toDateString()"
            readTime="5 min read"
            :tags="$featuredPost->tags"
            :url="route('posts.show', $featuredPost->slug)"
            :image="$featuredPost->feature_image_path ? MediaUrlBusiness::forMedia($featuredPost->feature_image_path) : null"
        />
    @endif

    <section class="py-8 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-neutral-500 text-sm">I write about Laravel, Docker, and freelance development. Here's what's new.</p>
        </div>
    </section>

    {{-- Dual-Column: Featured Post + Sidebar --}}
    <section class="py-16 lg:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-3 gap-12">
                <div class="lg:col-span-2">
                    @php $mainPost = $recentCreatedPosts->skip(1)->first(); @endphp
                    @if($mainPost)
                        <h2 class="text-2xl font-bold text-neutral-900 mb-8">Featured Article</h2>
                        <x-blog.post-card-featured
                            :title="$mainPost->title"
                            :excerpt="$mainPost->excerpt ?? $mainPost->meta_description ?? ''"
                            :date="$mainPost->published_at?->toDateString()"
                            readTime="8 min read"
                            :tags="$mainPost->tags"
                            :url="route('posts.show', $mainPost->slug)"
                            :image="$mainPost->feature_image_path ? MediaUrlBusiness::forMedia($mainPost->feature_image_path) : null"
                        />
                    @endif
                </div>

                <div class="lg:col-span-1">
                    <h2 class="text-2xl font-bold text-neutral-900 mb-8">Recent Posts</h2>
                    <div class="divide-y divide-neutral-100">
                        @foreach($recentPosts as $post)
                            <x-blog.post-link-item
                                :title="$post->title"
                                :excerpt="$post->excerpt ?? $post->meta_description ?? ''"
                                :date="$post->published_at?->toDateString()"
                                readTime="5 min read"
                                :tags="$post->tags"
                                :url="route('posts.show', $post->slug)"
                                :image="$post->feature_image_path ? MediaUrlBusiness::forMedia($post->feature_image_path) : null"
                            />
                        @endforeach
                    </div>
                    <div class="mt-8">
                        <x-ui.button href="{{ route('posts.index') }}" variant="outline" size="sm" class="w-full justify-center">
                            View All Articles
                            <x-ui.icon name="arrow-right" class="ml-2" />
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Newsletter CTA --}}
    <section class="bg-primary-600 py-16 text-white overflow-hidden relative">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-white blur-3xl"></div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 rounded-full bg-white blur-3xl"></div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <x-forms.newsletter layout="inline" slug="homepage" class="text-white" />
            <p class="text-center text-xs text-white/50 mt-4">Join developers reading about Laravel &amp; Docker.</p>
        </div>
    </section>

    {{-- Popular Tags --}}
    @if($popularTags->isNotEmpty())
        <section class="py-12 bg-neutral-50 border-y border-neutral-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-center text-sm font-medium text-neutral-400 uppercase tracking-widest mb-6">Browse by Topic</h2>
                <div class="flex flex-wrap items-center justify-center gap-3">
                    @foreach($popularTags as $tag)
                        <x-ui.badge href="{{ route('tags.show', $tag->slug) }}" variant="secondary" size="md">
                            {{ $tag->name }}
                        </x-ui.badge>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.app>

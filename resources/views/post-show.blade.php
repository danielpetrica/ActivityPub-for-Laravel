@php
    use App\Actions\RenderPostHtmlAction;
    use App\Classes\Business\MediaUrlBusiness;

    $canonical = route('posts.show', $post->slug);
    $metaTitle = $post->meta_title ?? $post->title;
    $metaDescription = $post->meta_description ?? $post->excerpt ?? '';

    if ($post->feature_image_path) {
        $metaImage = MediaUrlBusiness::forMedia($post->feature_image_path);
    } elseif ($post->og_image && $post->og_image_generated_at === null) {
        $metaImage = $post->og_image;
    } elseif ($post->og_image && $post->og_image_generated_at !== null) {
        $metaImage = MediaUrlBusiness::forOgImage($post->og_image);
    } else {
        $metaImage = null;
    }

    $ogType = 'article';
    $articlePublished = $post->published_at?->toDateString();
    $articleModified = $post->updated_at?->toDateString();

    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        '@id' => $canonical,
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $canonical,
        ],
        'headline' => $metaTitle,
        'description' => $metaDescription,
        'datePublished' => $articlePublished,
        'dateModified' => $articleModified,
        'image' => $metaImage,
        'author' => [
            '@type' => 'Person',
            'name' => 'Daniel Petrica',
            'url' => url('/about'),
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'danielpetrica.com',
        ],
        'keywords' => $post->tags->pluck('name')->implode(', '),
        'articleSection' => $post->primaryTag?->name ?? $post->tags->first()?->name,
    ];

    $postHtml = RenderPostHtmlAction::execute($post);

    $primaryCrumb = $post->primaryTag ?? $post->tags->first();
    $breadcrumbs = [
        ['label' => 'Home', 'url' => url('/')],
    ];
    if ($primaryCrumb) {
        $breadcrumbs[] = ['label' => $primaryCrumb->name, 'url' => route('tags.show', $primaryCrumb->slug)];
    }
    $breadcrumbs[] = ['label' => $post->title];
@endphp

<x-layouts.app
    :title="$metaTitle"
    :description="$metaDescription"
    :structuredData="$structuredData"
    :canonical="$canonical"
    :metaTitle="$metaTitle"
    :metaDescription="$metaDescription"
    :metaImage="$metaImage"
    :ogType="$ogType"
    :articlePublished="$articlePublished"
    :articleModified="$articleModified"
    :post="$post"
>
    <x-layouts.hero
        :breadcrumbs="$breadcrumbs"
        :title="$post->title"
        :excerpt="$post->meta_description ?? ''"
        :date="$post->published_at?->toDateString()"
        readTime="5 min read"
        :section="$post->primaryTag?->name ?? $post->tags->first()?->name"
        :image="$metaImage"
    />

    <article class="py-16 lg:py-20 bg-white" itemscope itemtype="https://schema.org/BlogPosting">
        {{-- Microdata for SEO --}}
        <meta itemprop="mainEntityOfPage" content="{{ $canonical }}" />
        <meta itemprop="headline" content="{{ $metaTitle }}" />
        <meta itemprop="description" content="{{ $metaDescription }}" />
        @if($metaImage)
            <meta itemprop="image" content="{{ $metaImage }}" />
        @endif
        @if($articlePublished)
            <meta itemprop="datePublished" content="{{ $articlePublished }}" />
        @endif
        @if($articleModified)
            <meta itemprop="dateModified" content="{{ $articleModified }}" />
        @endif
        <meta itemprop="articleSection" content="{{ $post->primaryTag?->name ?? $post->tags->first()?->name }}" />
        <meta itemprop="author" content="Daniel Petrica" />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:grid lg:grid-cols-12 lg:gap-12">
                {{-- Article body --}}
                <div class="lg:col-span-8">
                    <div class="prose prose-lg prose-primary max-w-none">
                        {!! $postHtml !!}
                    </div>

                    <div class="mt-12 pt-12 border-t border-neutral-100">
                        <div class="flex flex-wrap gap-2">
                            @foreach($post->tags as $tag)
                                <a href="{{ route('tags.show', $tag->slug) }}">
                                    <x-ui.badge variant="secondary" class="hover:bg-primary-100 transition-colors">
                                        {{ $tag->name }}
                                    </x-ui.badge>
                                </a>
                            @endforeach
                        </div>
                    </div>

                    {{-- Interactive Section: Likes & Comments --}}
                    <div class="mt-16 pt-16 border-t border-neutral-100" id="interactive-section" data-post-id="{{ $post->id }}" data-likes-count="{{ $post->likes()->count() }}">
                        <div class="flex items-center justify-between mb-12">
                            <div id="like-button-container">
                                {{-- JS will render the like button here --}}
                            </div>
                        </div>

                        <div id="comments-container">
                            {{-- JS will render the comments widget here --}}
                        </div>
                    </div>
                </div>

                {{-- Sticky sidebar: table of contents + author --}}
                <aside class="lg:col-span-4 hidden lg:block">
                    <div class="sticky top-24 space-y-8">
                        @if($tocItems)
                            <x-blog.toc :items="$tocItems" />
                        @endif

                        <x-blog.author-widget
                            name="Daniel Petrica"
                            title="Software Analyst"
                            bio="Laravel developer and DevOps consultant based between Reggio Emilia and Tokyo. I write about Laravel, self-hosting, Docker and automation."
                            initials="DP"
                            twitterUrl="https://x.com/daniel_petrica"
                        />
                    </div>
                </aside>
            </div>
        </div>
    </article>

    {{-- Related articles --}}
    @if($relatedPosts->isNotEmpty())
        <section class="py-16 lg:py-20 bg-neutral-50 border-y border-neutral-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-end justify-between mb-10">
                    <div>
                        <h2 class="text-2xl lg:text-3xl font-extrabold text-neutral-900">Related articles</h2>
                        <p class="text-neutral-500 mt-2">More posts on similar topics you might enjoy.</p>
                    </div>
                    <x-ui.button href="{{ route('posts.index') }}" variant="outline" size="sm">
                        All articles
                        <x-ui.icon name="arrow-right" class="ml-2" />
                    </x-ui.button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    @foreach($relatedPosts as $related)
                        <x-blog.article-card
                            layout="vertical"
                            :title="$related->title"
                            :excerpt="$related->excerpt ?? $related->meta_description ?? ''"
                            :date="$related->published_at?->toDateString()"
                            readTime="8 min"
                            :tags="$related->tags"
                            :url="route('posts.show', $related->slug)"
                            :image="$related->feature_image_path ? MediaUrlBusiness::forMedia($related->feature_image_path) : null"
                        />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Newsletter CTA --}}
    <section class="bg-primary-600 py-10 text-white overflow-hidden relative">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-white blur-3xl"></div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 rounded-full bg-white blur-3xl"></div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <x-forms.newsletter layout="inline" slug="homepage" class="text-white" />
        </div>
    </section>

    {{-- View Tracker --}}
    <img src="{{ route('tracker', ['type' => 'post', 'slug' => $post->slug]) }}" alt="" class="hidden" aria-hidden="true">
</x-layouts.app>

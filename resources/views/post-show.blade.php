@php
    use App\Actions\RenderPostHtmlAction;

    $canonical = route('posts.show', $post->slug);
    $metaTitle = $post->meta_title ?? $post->title;
    $metaDescription = $post->meta_description ?? $post->excerpt ?? '';
    $metaImage = $post->feature_image_path ? Storage::url($post->feature_image_path) : null;
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
        :title="$post->title"
        :excerpt="$post->meta_description ?? ''"
        :date="$post->published_at?->toDateString()"
        readTime="5 min read"
        :section="$post->primaryTag?->name ?? $post->tags->first()?->name"
        :image="$metaImage"
    />

    <article class="py-20 bg-white" itemscope itemtype="https://schema.org/BlogPosting">
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

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="prose prose-lg prose-primary mx-auto">
                {!! RenderPostHtmlAction::execute($post) !!}
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
    </article>

    {{-- View Tracker --}}
    <img src="{{ route('tracker', ['type' => 'post', 'slug' => $post->slug]) }}" alt="" class="hidden" aria-hidden="true">
</x-layouts.app>

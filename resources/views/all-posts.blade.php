@php
    use App\Classes\Business\MediaUrlBusiness;
    use App\Classes\Business\OgImageBusiness;

    $metaImage = OgImageBusiness::generateForAllPosts();

    $allPostsUrl = route('posts.index');

    // Build the ItemList from the current page's posts for JSON-LD structured data.
    $itemListElement = [];
    foreach ($posts as $position => $post) {
        $itemListElement[] = [
            '@type' => 'ListItem',
            'position' => $position + 1,
            'url' => route('posts.show', $post->slug),
        ];
    }

    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        '@id' => $allPostsUrl,
        'name' => 'All Articles on Laravel, DevOps and More',
        'description' => 'Browsing all articles about Laravel, DevOps, and more.',
        'url' => $allPostsUrl,
        'mainEntity' => [
            '@type' => 'ItemList',
            'itemListElement' => $itemListElement,
        ],
        'breadcrumb' => [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => url('/'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'All Posts',
                    'item' => $allPostsUrl,
                ],
            ],
        ],
    ];
@endphp

<x-layouts.app
    title="All Articles on Laravel, DevOps and More - Daniel Petrica"
    description="Browsing all articles about Laravel, DevOps, and more."
    :metaImage="$metaImage"
    :structuredData="$structuredData"
>
    <x-layouts.hero
        :schemaType="'https://schema.org/CollectionPage'"
        title="All Posts"
        excerpt="Stay up to date with my latest articles on Laravel, DevOps, and more."
    />

    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-8">
                @foreach($posts as $post)
                    <x-blog.article-card
                        :title="$post->title"
                        :excerpt="$post->excerpt ?? $post->meta_description ?? ''"
                        :date="$post->published_at?->toDateString()"
                        readTime="8 min"
                        :tags="$post->tags"
                        :url="route('posts.show', $post->slug)"
                        :image="$post->feature_image_path ? MediaUrlBusiness::forMedia($post->feature_image_path) : null"
                    />
                @endforeach
            </div>

            <div class="mt-12">
                {{ $posts->links('vendor.pagination.allposts') }}
            </div>
        </div>
    </section>
</x-layouts.app>

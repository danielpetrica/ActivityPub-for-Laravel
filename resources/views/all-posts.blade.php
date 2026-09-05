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
        :breadcrumbs="[
            ['label' => 'Home', 'url' => url('/')],
            ['label' => 'All Posts'],
        ]"
        title="All Posts"
        excerpt="Stay up to date with my latest articles on Laravel, DevOps, and more."
    />

    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">
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

    <section class="bg-primary-600 py-10 text-white overflow-hidden relative">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-white blur-3xl"></div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 rounded-full bg-white blur-3xl"></div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <x-forms.newsletter layout="inline" slug="homepage" class="text-white" />
        </div>
    </section>
</x-layouts.app>

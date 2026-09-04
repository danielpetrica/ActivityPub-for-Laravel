@php
    use App\Classes\Business\MediaUrlBusiness;
    use Illuminate\Support\Str;

    if ($tag->image_path) {
        $metaImage = MediaUrlBusiness::forMedia($tag->image_path);
    } elseif ($tag->og_image && $tag->og_image_generated_at === null) {
        $metaImage = $tag->og_image;
    } elseif ($tag->og_image && $tag->og_image_generated_at !== null) {
        $metaImage = MediaUrlBusiness::forOgImage($tag->og_image);
    } else {
        $metaImage = null;
    }

    $tagUrl = route('tags.show', $tag->slug);

    $metaTitle = $tag->meta_title ?? 'Posts tagged with ' . $tag->name . ' - Daniel Petrica';

    // Meta description fallback chain: explicit meta first, then a cleaned-up
    // tag description, then an enriched generic fallback unique per tag.
    if ($tag->meta_description) {
        $metaDescription = $tag->meta_description;
    } elseif ($tag->description) {
        $metaDescription = Str::of($tag->description)
            ->stripTags()
            ->squish()
            ->limit(limit: 150)
            ->toString();
    } else {
        $metaDescription = 'All articles tagged with ' . $tag->name . ' — tutorials and practical guides on Laravel, DevOps, Docker, self-hosting and automation from Daniel Petrica.';
    }

    // JSON-LD structured data: CollectionPage with an ItemList of the posts
    // shown on the current page, plus a BreadcrumbList for navigation context.
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        '@id' => $tagUrl,
        'name' => $metaTitle,
        'description' => $metaDescription,
        'url' => $tagUrl,
        'mainEntity' => [
            '@type' => 'ItemList',
            'itemListElement' => collect($posts->items())
                ->map(
                    fn ($post, $index) => [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'url' => route('posts.show', $post->slug),
                    ]
                )
                ->values()
                ->all(),
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
                    'name' => $tag->name,
                    'item' => $tagUrl,
                ],
            ],
        ],
    ];
@endphp

<x-layouts.app
    :title="$metaTitle"
    :description="$metaDescription"
    :metaImage="$metaImage"
    :structuredData="$structuredData"
>
    <x-layouts.hero
        :schemaType="'https://schema.org/CollectionPage'"
        :breadcrumbs="[
            ['label' => 'Home', 'url' => url('/')],
            ['label' => $tag->name],
        ]"
        :title="'Tag: ' . $tag->name"
        :excerpt="$tag->description ?? 'Browsing all articles tagged with ' . $tag->name"
        :image="$tag->image_path ? MediaUrlBusiness::forMedia($tag->image_path) : null"
    />

    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-8">
                @foreach($posts as $post)
                    <x-blog.article-card
                        :title="$post->title"
                        :excerpt="$post->seo_metadata['description'] ?? ''"
                        :date="$post->published_at?->toDateString()"
                        readTime="8 min"
                        :tags="$post->tags"
                        :url="route('posts.show', $post->slug)"
                        :image="$post->feature_image_path ? MediaUrlBusiness::forMedia($post->feature_image_path) : null"
                    />
                @endforeach
            </div>

            <div class="mt-12">
                {{ $posts->links() }}
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

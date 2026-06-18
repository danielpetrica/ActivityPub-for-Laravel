@php
    use App\Classes\Business\MediaUrlBusiness;

    if ($tag->image_path) {
        $metaImage = MediaUrlBusiness::forMedia($tag->image_path);
    } elseif ($tag->og_image && $tag->og_image_generated_at === null) {
        $metaImage = $tag->og_image;
    } elseif ($tag->og_image && $tag->og_image_generated_at !== null) {
        $metaImage = MediaUrlBusiness::forOgImage($tag->og_image);
    } else {
        $metaImage = null;
    }
@endphp

<x-layouts.app
    :title="$tag->meta_title ?? 'Posts tagged with ' . $tag->name . ' - Daniel Petrica'"
    :description="$tag->meta_description ?? 'Browsing all articles tagged with ' . $tag->name"
    :metaImage="$metaImage"
>
    <x-layouts.hero
        :schemaType="'https://schema.org/CollectionPage'"
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
</x-layouts.app>

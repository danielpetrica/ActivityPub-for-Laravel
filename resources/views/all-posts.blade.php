<x-layouts.app
    title="All Posts - Daniel Petrica"
    description="Browsing all articles about Laravel, DevOps, and more."
>
    <x-layouts.hero
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
                        :image="$post->feature_image_path ? Storage::url($post->feature_image_path) : null"
                    />
                @endforeach
            </div>

            <div class="mt-12">
                {{ $posts->links('vendor.pagination.allposts') }}
            </div>
        </div>
    </section>
</x-layouts.app>

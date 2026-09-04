@php
    use App\Actions\RenderPostHtmlAction;
    use App\Classes\Business\MediaUrlBusiness;

    $canonical = route('pages.show', $page->slug);
    $metaTitle = $page->meta_title ?? $page->title;
    $metaDescription = $page->meta_description ?? $page->excerpt ?? '';

    if ($page->feature_image_path) {
        $metaImage = MediaUrlBusiness::forMedia($page->feature_image_path);
    } elseif ($page->og_image && $page->og_image_generated_at === null) {
        $metaImage = $page->og_image;
    } elseif ($page->og_image && $page->og_image_generated_at !== null) {
        $metaImage = MediaUrlBusiness::forOgImage($page->og_image);
    } else {
        $metaImage = null;
    }

    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        '@id' => $canonical,
        'name' => $metaTitle,
        'description' => $metaDescription,
        'url' => $canonical,
    ];
@endphp

<x-layouts.app
    :title="$metaTitle"
    :description="$metaDescription"
    :metaImage="$metaImage"
    :structuredData="$structuredData"
    :canonical="$canonical"
>
    <x-layouts.hero
        :breadcrumbs="[
            ['label' => 'Home', 'url' => url('/')],
            ['label' => $page->title],
        ]"
        :title="$page->title"
        :excerpt="$page->excerpt ?? $page->meta_description ?? ''"
    />

    <article class="py-20 bg-white" itemscope itemtype="https://schema.org/WebPage">
        <meta itemprop="name" content="{{ $metaTitle }}" />
        <meta itemprop="description" content="{{ $metaDescription }}" />
        <meta itemprop="mainEntityOfPage" content="{{ $canonical }}" />
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="prose prose-lg prose-primary mx-auto">
                {!! RenderPostHtmlAction::execute($page) !!}
            </div>
        </div>
    </article>

    {{-- View Tracker --}}
    <img src="{{ route('tracker', ['type' => 'page', 'slug' => $page->slug]) }}" alt="" class="hidden" aria-hidden="true">
</x-layouts.app>

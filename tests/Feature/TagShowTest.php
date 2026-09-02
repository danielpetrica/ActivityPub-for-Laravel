<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    // ActivityPub federation is enabled in .env and Post::factory()->create()
    // saves with a Published status, which triggers the saved event and calls
    // activityPubActor(). A User must exist or that call throws.
    User::factory()->create();
});

/**
 * @return array<string, mixed>
 */
function tagShowJsonLd(TestResponse $response): array
{
    preg_match(
        pattern: '/<script type="application\/ld\+json">(.*?)<\/script>/s',
        subject: $response->getContent(),
        matches: $matches,
    );

    return json_decode(json: $matches[1], associative: true);
}

function tagShowMetaDescription(TestResponse $response): string
{
    preg_match(
        pattern: '/<meta name="description" content="([^"]*)"\s*>/',
        subject: $response->getContent(),
        matches: $matches,
    );

    return $matches[1];
}

test('uses an enriched generic meta description when the tag has no description', function () {
    $tag = Tag::factory()->create();

    $response = $this->get(route('tags.show', $tag->slug));

    $response->assertStatus(200);

    $metaDescription = tagShowMetaDescription($response);

    expect($metaDescription)->not->toBeEmpty();
    expect(strlen($metaDescription))->toBeGreaterThanOrEqual(110);
    expect(strlen($metaDescription))->toBeLessThanOrEqual(160);
    expect($metaDescription)->toContain($tag->name);
});

test('derives the meta description from the tag description and truncates it', function () {
    $tag = Tag::factory()->create([
        'description' => '<p>'.Str::repeat('This is a long descriptive paragraph about the tag content. ', 20).'</p>',
    ]);

    $response = $this->get(route('tags.show', $tag->slug));

    $response->assertStatus(200);

    $metaDescription = tagShowMetaDescription($response);

    expect($metaDescription)->not->toBeEmpty();
    expect(strlen($metaDescription))->toBeLessThanOrEqual(160);
    expect($metaDescription)->toContain('This is a long descriptive paragraph');
});

test('renders CollectionPage JSON-LD with the tag url and the page posts', function () {
    $tag = Tag::factory()->create();
    $posts = collect([1, 2, 3])->map(
        fn (int $daysAgo) => Post::factory()->create([
            'status' => PostStatus::Published,
            'published_at' => now()->subDays($daysAgo),
        ])
    );
    $tag->posts()->attach($posts->pluck('id'));

    $response = $this->get(route('tags.show', $tag->slug));

    $response->assertStatus(200);
    $response->assertSee('<script type="application/ld+json">', false);

    $structuredData = tagShowJsonLd($response);

    expect($structuredData['@type'])->toBe('CollectionPage');
    expect($structuredData['url'])->toBe(route('tags.show', $tag->slug));
    expect($structuredData['mainEntity']['@type'])->toBe('ItemList');
    expect($structuredData['mainEntity']['itemListElement'])->toHaveCount(3);
    expect($structuredData['mainEntity']['itemListElement'][0]['url'])->toBe(route('posts.show', $posts[0]->slug));
    expect($structuredData['breadcrumb']['@type'])->toBe('BreadcrumbList');
});

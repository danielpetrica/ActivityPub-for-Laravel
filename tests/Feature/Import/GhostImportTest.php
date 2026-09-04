<?php

namespace Tests\Feature\Import;

use App\Actions\RenderPostHtmlAction;
use App\Classes\Business\Import\Ghost\GhostImportBusiness;
use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // ActivityPub federation is enabled in .env and published post saves fire
    // the federation event, which resolves the actor user. Create one so the
    // importer can create published posts in tests.
    User::factory()->create();
});

it('imports ghost data correctly', function () {
    Storage::fake('public');
    Http::fake([
        '*' => Http::response('fake-image-content', 200),
    ]);

    $jsonContent = [
        'db' => [
            [
                'data' => [
                    'tags' => [
                        [
                            'id' => 'tag1',
                            'name' => 'News',
                            'slug' => 'news',
                            'description' => 'News tag description',
                            'feature_image' => 'https://example.com/tag.jpg',
                            'meta_title' => 'Tag Meta Title',
                            'meta_description' => 'Tag Meta Desc',
                            'og_image' => null,
                            'og_title' => null,
                            'og_description' => null,
                            'twitter_image' => null,
                            'twitter_title' => null,
                            'twitter_description' => null,
                            'accent_color' => '#ffffff',
                        ],
                    ],
                    'posts' => [
                        [
                            'id' => 'post1',
                            'uuid' => 'uuid1',
                            'title' => 'My Post',
                            'slug' => 'my-post',
                            'html' => '<p>Post content</p>',
                            'feature_image' => '__GHOST_URL__/content/images/post.png',
                            'featured' => 0,
                            'type' => 'post',
                            'status' => 'published',
                            'visibility' => 'public',
                            'created_at' => '2023-01-01T00:00:00.000Z',
                            'updated_at' => '2023-01-01T00:00:00.000Z',
                            'published_at' => '2023-01-01T00:00:00.000Z',
                            'custom_excerpt' => 'Excerpt',
                            'codeinjection_head' => '<script>head</script>',
                            'codeinjection_foot' => '<script>foot</script>',
                            'canonical_url' => 'https://canonical.com',
                            'show_title_and_feature_image' => 1,
                        ],
                        [
                            'id' => 'page1',
                            'uuid' => 'uuid2',
                            'title' => 'My Page',
                            'slug' => 'my-page',
                            'html' => '<p>Page content</p>',
                            'feature_image' => null,
                            'featured' => 0,
                            'type' => 'page',
                            'status' => 'published',
                            'visibility' => 'public',
                            'created_at' => '2023-01-01T00:00:00.000Z',
                            'updated_at' => '2023-01-01T00:00:00.000Z',
                            'published_at' => null,
                            'custom_excerpt' => null,
                            'codeinjection_head' => null,
                            'codeinjection_foot' => null,
                            'canonical_url' => null,
                            'show_title_and_feature_image' => 1,
                        ],
                        [
                            'id' => 'private1',
                            'uuid' => 'uuid3',
                            'title' => 'Private Post',
                            'slug' => 'private-post',
                            'html' => '<p>Private content</p>',
                            'feature_image' => null,
                            'featured' => 0,
                            'type' => 'post',
                            'status' => 'published',
                            'visibility' => 'members',
                            'created_at' => '2023-01-01T00:00:00.000Z',
                            'updated_at' => '2023-01-01T00:00:00.000Z',
                            'published_at' => '2023-01-01T00:00:00.000Z',
                            'custom_excerpt' => null,
                            'codeinjection_head' => null,
                            'codeinjection_foot' => null,
                            'canonical_url' => null,
                            'show_title_and_feature_image' => 1,
                        ],
                    ],
                    'posts_meta' => [
                        [
                            'post_id' => 'post1',
                            'meta_title' => 'Post Meta Title',
                            'meta_description' => 'Post Meta Desc',
                            'feature_image_alt' => 'Alt text',
                            'feature_image_caption' => 'Caption text',
                        ],
                    ],
                    'posts_tags' => [
                        [
                            'post_id' => 'post1',
                            'tag_id' => 'tag1',
                        ],
                    ],
                ],
            ],
        ],
    ];

    $tempFile = tempnam(sys_get_temp_dir(), 'ghost_export');
    File::put($tempFile, json_encode($jsonContent));

    $importer = new GhostImportBusiness(ghostBaseUrl: 'https://ghost.test');
    $report = $importer->run($tempFile);

    expect($report['tags']['created'])->toBe(1);
    expect($report['posts']['created'])->toBe(2); // post1 + private1
    expect($report['pages']['created'])->toBe(1);

    // Verify Tag
    $tag = Tag::where('slug', 'news')->first();
    expect($tag)->not->toBeNull();
    expect($tag->name)->toBe('News');
    expect($tag->image_path)->not->toBeNull();
    expect($tag->meta_title)->toBe('Tag Meta Title');

    // Verify Post
    $post = Post::where('slug', 'my-post')->first();
    expect($post)->not->toBeNull();
    expect($post->title)->toBe('My Post');
    expect($post->status)->toBe(PostStatus::Published);
    expect($post->meta_title)->toBe('Post Meta Title');
    expect($post->feature_image_alt)->toBe('Alt text');
    expect($post->codeinjection_head)->toBe('<script>head</script>');
    expect($post->tags)->toHaveCount(1);
    expect($post->tags->first()->slug)->toBe('news');
    expect($post->content)->toBeArray();
    expect($post->content['type'])->toBe('doc');

    // Verify Page
    $page = Page::where('slug', 'my-page')->first();
    expect($page)->not->toBeNull();
    expect($page->title)->toBe('My Page');

    // Verify Private Post
    $private = Post::where('slug', 'private-post')->first();
    expect($private->title)->toBe('Private: Private Post');
    expect($private->status)->toBe(PostStatus::Draft);

    // Verify Redirect
    $redirect = DB::table('redirects')->where('path', '/my-post/')->first();
    expect($redirect)->not->toBeNull();
    expect($redirect->destination_url)->toBe('/posts/my-post/');

    unlink($tempFile);
});

it('resolves __GHOST_URL__ and typo domains for inline images', function () {
    Storage::fake('public');

    Http::fake([
        'https://ghost.test/content/images/a.jpg*' => Http::response('img-a', 200),
        'https://ghost.test/content/images/c.jpg*' => Http::response('img-c', 200),
        'https://danielpetrica.com/content/images/b.jpg*' => Http::response('img-b', 200),
        'https://danielpetrica.com/content/images/d.jpg*' => Http::response('img-d', 200),
        'https://danielpetrica.com/content/images/e.jpg*' => Http::response('img-e', 200),
        '*' => Http::response('nope', 404),
    ]);

    $html = '<p>Images:</p>'
        .'<img src="__GHOST_URL__/content/images/a.jpg" alt="a">'
        .'<img src="https://danielpetrica.co/content/images/b.jpg" alt="b">'
        .'<img src="https://danielpetrica.com/content/images/d.jpg" alt="d">'
        .'<img src="https://danielpetrica.comm/content/images/e.jpg" alt="e">'
        .'<img src="media/__GHOST_URL__/content/images/c.jpg" alt="c">'
        .'<img src="__GHOST_URL__/content/images/broken.jpg" alt="broken">'
        .'<a href="__GHOST_URL__/about">About</a>';

    $jsonContent = [
        'db' => [['data' => [
            'tags' => [],
            'posts' => [[
                'id' => 'p1', 'uuid' => 'u1', 'title' => 'Ghost URLs', 'slug' => 'ghost-urls',
                'html' => $html, 'feature_image' => null, 'featured' => 0, 'type' => 'post',
                'status' => 'published', 'visibility' => 'public',
                'created_at' => '2023-01-01T00:00:00.000Z', 'updated_at' => '2023-01-01T00:00:00.000Z',
                'published_at' => '2023-01-01T00:00:00.000Z', 'custom_excerpt' => null,
                'codeinjection_head' => null, 'codeinjection_foot' => null,
                'canonical_url' => null, 'show_title_and_feature_image' => 1,
            ]],
            'posts_meta' => [], 'posts_tags' => [],
        ]]],
    ];

    $tempFile = tempnam(sys_get_temp_dir(), 'ghost_export');
    File::put($tempFile, json_encode($jsonContent));

    $importer = new GhostImportBusiness(ghostBaseUrl: 'https://ghost.test');
    $importer->run($tempFile);

    $post = Post::where('slug', 'ghost-urls')->first();
    expect($post)->not->toBeNull();

    // Extract the rendered HTML from the stored Tiptap content.
    $htmlOut = RenderPostHtmlAction::execute($post);

    // Downloaded images are rewritten to the local media proxy.
    // Downloaded images are rewritten to the local media proxy (path convention
    // keeps the media/ prefix, matching how files are stored on the S3 disk).
    expect($htmlOut)->toContain('/objectproxy/media/media/content/');
    // The three successful downloads must not keep the placeholder or typo.
    expect($htmlOut)->not->toContain('__GHOST_URL__');
    expect($htmlOut)->not->toContain('danielpetrica.co');
    // Correct .com URLs must not be corrupted into .comm by the typo fix.
    expect($htmlOut)->not->toContain('.comm');
    // The failed download keeps a resolved (placeholder-free) absolute URL.
    expect($htmlOut)->toContain('https://ghost.test/content/images/broken.jpg');
    // Links get the placeholder replaced with the base URL.
    expect($htmlOut)->toContain('https://ghost.test/about');

    unlink($tempFile);
});

it('artisan command finds latest ghost export and runs', function () {
    Storage::fake('public');
    Http::fake([
        '*' => Http::response('fake-image-content', 200),
    ]);

    $jsonContent = [
        'db' => [['data' => [
            'tags' => [['id' => 't1', 'name' => 'T', 'slug' => 't', 'description' => 'd', 'feature_image' => null, 'meta_title' => null, 'meta_description' => null, 'og_image' => null, 'og_title' => null, 'og_description' => null, 'twitter_image' => null, 'twitter_title' => null, 'twitter_description' => null, 'accent_color' => null]],
            'posts' => [],
            'posts_meta' => [],
            'posts_tags' => [],
        ]]],
    ];

    // Ensure storage/app exists for glob
    if (! File::isDirectory(storage_path('app'))) {
        File::makeDirectory(storage_path('app'), 0755, true);
    }

    $filePath = storage_path('app/zzzz.ghost.json');
    File::put($filePath, json_encode($jsonContent));

    $this->artisan('app:import-ghost', ['--dry' => true])
        ->expectsOutputToContain('Starting Ghost import from '.$filePath)
        ->expectsOutputToContain('Tags      | 1')
        ->assertExitCode(0);

    unlink($filePath);
});

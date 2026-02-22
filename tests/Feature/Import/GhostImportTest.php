<?php

namespace Tests\Feature\Import;

use App\Classes\Business\Import\Ghost\GhostImportBusiness;
use App\Enums\PostStatus;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

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

<?php

namespace Tests\Feature\Import;

use App\Actions\RenderPostHtmlAction;
use App\Classes\Business\Import\Ghost\GhostImportBusiness;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('imports and renders advanced html like links and iframes', function () {
    Storage::fake('public');
    Http::fake([
        '*' => Http::response('fake-image-content', 200),
    ]);

    $advancedHtml = <<<'HTML'
<p>Check out <a href="https://danielpetrica.com">my website</a>.</p>
<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" width="560" height="315" frameborder="0" allowfullscreen></iframe>
<p>Another link <a href="/internal-link" target="_blank" rel="noopener noreferrer">Internal</a></p>
HTML;

    $jsonContent = [
        'db' => [
            [
                'data' => [
                    'tags' => [],
                    'posts' => [
                        [
                            'id' => 'post_advanced',
                            'uuid' => 'uuid_advanced',
                            'title' => 'Advanced HTML Post',
                            'slug' => 'advanced-html-post',
                            'html' => $advancedHtml,
                            'type' => 'post',
                            'status' => 'published',
                            'visibility' => 'public',
                            'created_at' => '2023-01-01T00:00:00.000Z',
                            'updated_at' => '2023-01-01T00:00:00.000Z',
                            'published_at' => '2023-01-01T00:00:00.000Z',
                        ],
                    ],
                    'posts_meta' => [],
                    'posts_tags' => [],
                ],
            ],
        ],
    ];

    $path = storage_path('app/test-advanced.json');
    file_put_contents($path, json_encode($jsonContent));

    $importer = new GhostImportBusiness('https://old-site.com');
    $importer->run($path);

    $post = Post::where('slug', 'advanced-html-post')->first();
    expect($post)->not->toBeNull();

    $renderedHtml = RenderPostHtmlAction::execute($post);

    // Verify links are preserved
    expect($renderedHtml)->toContain('href="https://danielpetrica.com"');
    expect($renderedHtml)->toContain('my website');
    expect($renderedHtml)->toContain('href="/internal-link"');
    expect($renderedHtml)->toContain('target="_blank"');

    // Verify iframes are preserved
    expect($renderedHtml)->toContain('<iframe');
    expect($renderedHtml)->toContain('src="https://www.youtube.com/embed/dQw4w9WgXcQ"');
    expect($renderedHtml)->toContain('width="560"');
    expect($renderedHtml)->toContain('height="315"');

    unlink($path);
});

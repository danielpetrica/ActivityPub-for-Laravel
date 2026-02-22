<?php

namespace Tests\Feature\Actions;

use App\Actions\RenderPostHtmlAction;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenderPostHtmlActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_render_post_with_array_content()
    {
        $post = Post::factory()->create([
            'content' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => 'Hello World'],
                        ],
                    ],
                ],
            ],
        ]);

        $html = RenderPostHtmlAction::execute($post);

        $this->assertStringContainsString('<p>Hello World</p>', $html);
    }

    public function test_it_can_render_post_with_string_content()
    {
        $post = Post::factory()->create();

        // Directly set the content on the model instance and bypass the DB/refresh logic.
        // We want to test RenderPostHtmlAction's ability to handle string,
        // regardless of how it got there (e.g., failed JSON decode or raw HTML).
        $post->content = '<p>Raw HTML content</p>';

        $html = RenderPostHtmlAction::execute($post);

        $this->assertStringContainsString('<p>Raw HTML content</p>', $html);
    }
}

<?php

namespace Tests\Feature\Actions;

use App\Actions\RenderPostHtmlAction;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenderPostHtmlActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ActivityPub federation is enabled in .env and Post::factory()->create()
        // saves with a Published status, which triggers the saved event and calls
        // activityPubActor(). A User must exist or that call throws.
        User::factory()->create();
    }

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

    public function test_it_adds_the_post_title_as_alt_when_the_image_has_no_alt()
    {
        $post = Post::factory()->create(['title' => 'My Awesome Post']);

        $post->content = '<p><img src="media/content/2026/01/example.jpg"></p>';

        $html = RenderPostHtmlAction::execute($post);

        $this->assertStringContainsString('alt="My Awesome Post"', $html);
    }

    public function test_it_uses_the_figcaption_text_as_alt_for_a_figure_image()
    {
        $post = Post::factory()->create(['title' => 'Fallback Title']);

        $post->content = <<<'HTML'
<figure>
    <img src="media/content/2026/01/example.jpg">
    <figcaption>An illustrative caption for the image.</figcaption>
</figure>
HTML;

        $html = RenderPostHtmlAction::execute($post);

        $this->assertStringContainsString('alt="An illustrative caption for the image."', $html);
    }

    public function test_it_leaves_an_existing_alt_attribute_untouched()
    {
        $post = Post::factory()->create(['title' => 'My Awesome Post']);

        $post->content = '<p><img src="media/content/2026/01/example.jpg" alt="Handwritten alt"></p>';

        $html = RenderPostHtmlAction::execute($post);

        $this->assertStringContainsString('alt="Handwritten alt"', $html);
        $this->assertStringNotContainsString('alt="My Awesome Post"', $html);
    }
}

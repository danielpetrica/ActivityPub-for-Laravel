<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence();

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => fake()->paragraph()],
                        ],
                    ],
                ],
            ],
            'status' => PostStatus::Published,
            'published_at' => now(),
            'seo_metadata' => [
                'title' => $title,
                'description' => fake()->sentence(),
                'keywords' => implode(',', fake()->words(5)),
            ],
        ];
    }
}

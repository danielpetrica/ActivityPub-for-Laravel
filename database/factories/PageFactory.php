<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
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
            'seo_metadata' => json_encode([
                'title' => $title,
                'description' => fake()->sentence(),
                'keywords' => implode(',', fake()->words(5)),
            ]),
        ];
    }
}

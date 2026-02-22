<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Page>
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
        $title = $this->faker->sentence();

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => $this->faker->paragraph()],
                        ],
                    ],
                ],
            ],
            'status' => PostStatus::Published,
            'seo_metadata' => json_encode([
                'title' => $title,
                'description' => $this->faker->sentence(),
                'keywords' => implode(',', $this->faker->words(5)),
            ]),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\PageView;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageView>
 */
class PageViewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viewable_id' => 1,
            'viewable_type' => Post::class,
            'ip_address' => fake()->ipv4(),
            'referer' => fake()->url(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}

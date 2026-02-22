<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PageView>
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
            'viewable_type' => \App\Models\Post::class,
            'ip_address' => $this->faker->ipv4(),
            'referer' => $this->faker->url(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\LinkPosition;
use App\Models\Link;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Link>
 */
class LinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position' => LinkPosition::Footer,
            'label' => fake()->words(3, true),
            'url' => fake()->url(),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_external' => fake()->boolean(),
        ];
    }
}

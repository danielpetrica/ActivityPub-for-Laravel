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
            'label' => $this->faker->words(3, true),
            'url' => $this->faker->url(),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_external' => $this->faker->boolean(),
        ];
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(nb: 3, asText: true);

        return [
            'name' => $name,
            'slug' => str($name)->slug(),
            'intro_content' => fake()->paragraph(),
            'main_content' => fake()->paragraph(),
            'seo_metadata' => null,
            'is_active' => true,
        ];
    }
}

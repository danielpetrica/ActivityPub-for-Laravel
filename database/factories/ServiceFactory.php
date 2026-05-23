<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
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
        $name = $this->faker->words(nb: 3, asText: true);

        return [
            'name' => $name,
            'slug' => str($name)->slug(),
            'intro_content' => $this->faker->paragraph(),
            'main_content' => $this->faker->paragraph(),
            'seo_metadata' => null,
            'is_active' => true,
        ];
    }
}

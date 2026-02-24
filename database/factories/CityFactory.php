<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\City>
 */
class CityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->city();

        return [
            'name' => $name,
            'slug' => str($name)->slug(),
            'province' => $this->faker->stateAbbr(),
            'region' => $this->faker->word(),
            'is_capital' => false,
            'description' => $this->faker->sentence(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
        ];
    }
}

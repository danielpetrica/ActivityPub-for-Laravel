<?php

namespace Database\Factories;

use App\Models\NewsletterForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterForm>
 */
class NewsletterFormFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, asText: true);

        return [
            'name' => $name,
            'slug' => str($name)->slug(),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'button_text' => 'Subscribe',
            'success_message' => 'Thank you for subscribing!',
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Tool;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tool>
 */
class ToolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'html_content' => '<div class="tool-container"><p>This is a custom tool: '.$name.'</p></div>',
            'seo_metadata' => [
                'title' => ucfirst($name),
                'description' => $this->faker->sentence(),
                'keywords' => implode(',', $this->faker->words(5)),
            ],
        ];
    }
}

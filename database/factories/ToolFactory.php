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
        $name = fake()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'html_content' => '<div class="tool-container"><p>This is a custom tool: '.$name.'</p></div>',
            'seo_metadata' => [
                'title' => ucfirst($name),
                'description' => fake()->sentence(),
                'keywords' => implode(',', fake()->words(5)),
            ],
        ];
    }
}

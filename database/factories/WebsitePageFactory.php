<?php

namespace Database\Factories;

use App\Models\WebsitePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsitePage>
 */
class WebsitePageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'content' => ['heading' => fake()->sentence()],
            'meta_title' => fake()->sentence(3),
            'meta_description' => fake()->sentence(),
            'is_published' => true,
        ];
    }
}

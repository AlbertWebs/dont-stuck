<?php

namespace Database\Factories;

use App\Models\Borrower;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Borrower>
 */
class BorrowerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'national_id' => fake()->unique()->numerify('########'),
            'phone' => '+2547'.fake()->numerify('########'),
            'email' => fake()->safeEmail(),
            'residence' => fake()->city(),
            'employer' => fake()->company(),
            'employer_location' => fake()->city(),
            'job_title' => fake()->jobTitle(),
            'net_salary' => '50000.00',
            'next_of_kin_name' => fake()->name(),
            'next_of_kin_id' => fake()->unique()->numerify('########'),
            'next_of_kin_relation' => 'Sibling',
            'next_of_kin_phone' => '+2547'.fake()->numerify('########'),
        ];
    }
}

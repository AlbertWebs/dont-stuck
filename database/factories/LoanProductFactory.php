<?php

namespace Database\Factories;

use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanProduct>
 */
class LoanProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'minimum_amount' => null,
            'maximum_amount' => null,
            'repayment_months' => null,
            'interest_rate' => null,
            'interest_basis' => null,
            'processing_fee_rate' => null,
            'commissioning_fee' => null,
            'daily_late_fee' => null,
            'late_penalty_start' => null,
            'allocation_order' => null,
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Borrower;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanApplication>
 */
class LoanApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'DSS-'.now()->format('Ymd').'-'.fake()->unique()->regexify('[A-Z0-9]{8}'),
            'borrower_id' => Borrower::factory(),
            'loan_product_id' => LoanProduct::factory(),
            'assigned_user_id' => null,
            'requested_amount' => '25000.00',
            'requested_months' => 3,
            'purpose' => fake()->sentence(8),
            'status' => 'submitted',
            'consented_at' => now(),
            'applicant_notes' => null,
            'decision_notes' => null,
            'application_data' => [],
        ];
    }
}

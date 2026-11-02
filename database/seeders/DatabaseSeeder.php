<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        LoanProduct::query()->updateOrCreate(
            ['slug' => 'employed-loan'],
            ['name' => 'Employed loan', 'description' => 'For employed applicants seeking support for a planned expense. Terms are confirmed after assessment.', 'is_active' => true],
        );

        LoanProduct::query()->updateOrCreate(
            ['slug' => 'refinance'],
            ['name' => 'Refinance', 'description' => 'A refinance request for applicants who want the team to review an existing borrowing need. Terms are confirmed after assessment.', 'is_active' => true],
        );

        $this->call(LoanPortfolioDemoSeeder::class);
    }
}

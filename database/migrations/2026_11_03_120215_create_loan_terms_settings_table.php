<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_terms_settings', function (Blueprint $table): void {
            $table->id();
            $table->decimal('interest_rate', 8, 4)->default('20.0000');
            $table->string('interest_basis')->default('flat_monthly_equal_principal');
            $table->decimal('processing_fee_rate', 8, 4)->default('3.0000');
            $table->decimal('legal_fee_amount', 14, 2)->default('200.00');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('loan_terms_settings')->insert([
            'id' => 1,
            'interest_rate' => '20.0000',
            'interest_basis' => 'flat_monthly_equal_principal',
            'processing_fee_rate' => '3.0000',
            'legal_fee_amount' => '200.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_terms_settings');
    }
};

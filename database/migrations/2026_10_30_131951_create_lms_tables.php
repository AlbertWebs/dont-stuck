<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('loan_officer')->index();
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::create('loan_products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->decimal('minimum_amount', 14, 2)->nullable();
            $table->decimal('maximum_amount', 14, 2)->nullable();
            $table->unsignedSmallInteger('repayment_months')->nullable();
            $table->decimal('interest_rate', 8, 4)->nullable();
            $table->string('interest_basis')->nullable();
            $table->decimal('processing_fee_rate', 8, 4)->nullable();
            $table->decimal('commissioning_fee', 14, 2)->nullable();
            $table->decimal('daily_late_fee', 14, 2)->nullable();
            $table->string('late_penalty_start')->nullable();
            $table->string('allocation_order')->nullable();
            $table->string('fee_collection_method')->nullable();
            $table->unsignedSmallInteger('default_threshold_days')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('borrowers', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name');
            $table->string('national_id')->unique();
            $table->string('phone', 32)->index();
            $table->string('email')->nullable()->index();
            $table->string('residence')->nullable();
            $table->string('employer')->nullable();
            $table->string('employer_location')->nullable();
            $table->string('job_title')->nullable();
            $table->decimal('net_salary', 14, 2)->nullable();
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_id')->nullable();
            $table->string('next_of_kin_relation')->nullable();
            $table->string('next_of_kin_phone', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('loan_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('borrower_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('requested_amount', 14, 2);
            $table->unsignedSmallInteger('requested_months')->nullable();
            $table->decimal('approved_amount', 14, 2)->nullable();
            $table->unsignedSmallInteger('approved_months')->nullable();
            $table->text('purpose');
            $table->string('status')->default('submitted')->index();
            $table->timestamp('consented_at');
            $table->text('applicant_notes')->nullable();
            $table->text('decision_notes')->nullable();
            $table->json('application_data')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('guarantors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('national_id');
            $table->string('phone', 32);
            $table->string('relationship')->nullable();
            $table->boolean('is_colleague')->default(false);
            $table->timestamps();
        });

        Schema::create('collateral_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('make_model')->nullable();
            $table->string('serial_number')->nullable();
            $table->decimal('estimated_value', 14, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('application_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('application_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['loan_application_id', 'created_at']);
        });

        Schema::create('application_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('recommendation')->nullable();
            $table->text('notes');
            $table->timestamps();
        });

        Schema::create('loan_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('loan_application_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('borrower_id')->constrained()->restrictOnDelete();
            $table->decimal('principal_amount', 14, 2);
            $table->decimal('disbursed_amount', 14, 2)->nullable();
            $table->decimal('interest_rate', 8, 4)->nullable();
            $table->string('interest_basis')->nullable();
            $table->decimal('processing_fee', 14, 2)->nullable();
            $table->decimal('commissioning_fee', 14, 2)->nullable();
            $table->decimal('daily_late_fee', 14, 2)->nullable();
            $table->string('late_penalty_start')->nullable();
            $table->string('allocation_order')->nullable();
            $table->string('fee_collection_method')->nullable();
            $table->unsignedSmallInteger('default_threshold_days')->nullable();
            $table->unsignedSmallInteger('term_months')->nullable();
            $table->date('disbursed_at')->nullable();
            $table->date('maturity_date')->nullable();
            $table->string('status')->default('pending_disbursement')->index();
            $table->timestamps();
            $table->index(['status', 'maturity_date']);
        });

        Schema::create('disbursements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_account_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->decimal('gross_amount', 14, 2);
            $table->decimal('fees_withheld', 14, 2)->default(0);
            $table->date('disbursed_on');
            $table->string('method');
            $table->string('provider_reference')->nullable()->unique();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('repayment_schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('charge_type');
            $table->decimal('amount', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->date('charged_on');
            $table->string('status')->default('due')->index();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('repayment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');
            $table->decimal('principal_due', 14, 2);
            $table->decimal('interest_due', 14, 2);
            $table->decimal('fees_due', 14, 2)->default(0);
            $table->decimal('principal_paid', 14, 2)->default(0);
            $table->decimal('interest_paid', 14, 2)->default(0);
            $table->decimal('fees_paid', 14, 2)->default(0);
            $table->decimal('penalties_due', 14, 2)->default(0);
            $table->decimal('penalties_paid', 14, 2)->default(0);
            $table->unsignedInteger('penalty_days_accrued')->default(0);
            $table->string('status')->default('pending')->index();
            $table->timestamps();
            $table->unique(['loan_account_id', 'installment_number']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_account_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->string('method');
            $table->string('external_reference')->nullable()->unique();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('posted')->index();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['loan_account_id', 'paid_on']);
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('repayment_schedule_id')->constrained()->restrictOnDelete();
            $table->decimal('principal_amount', 14, 2)->default(0);
            $table->decimal('interest_amount', 14, 2)->default(0);
            $table->decimal('fees_amount', 14, 2)->default(0);
            $table->decimal('penalty_amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('contact_enquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('contact_enquiries');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('repayment_schedules');
        Schema::dropIfExists('loan_charges');
        Schema::dropIfExists('disbursements');
        Schema::dropIfExists('loan_accounts');
        Schema::dropIfExists('application_reviews');
        Schema::dropIfExists('application_status_histories');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('collateral_items');
        Schema::dropIfExists('guarantors');
        Schema::dropIfExists('loan_applications');
        Schema::dropIfExists('borrowers');
        Schema::dropIfExists('loan_products');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};

<?php

namespace Tests\Feature;

use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\DisbursementService;
use App\Services\LoanStatusRefreshService;
use App\Services\PaymentRecordingService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_disbursement_is_blocked_while_product_financial_terms_are_unset(): void
    {
        $finance = User::factory()->create(['role' => 'finance_officer']);
        $application = LoanApplication::factory()->create([
            'status' => 'pending_disbursement',
            'approved_amount' => '1200.00',
            'approved_months' => 2,
        ]);

        try {
            app(DisbursementService::class)->confirm($application, $finance, [
                'disbursed_on' => today()->toDateString(),
                'first_repayment_due_on' => today()->addMonth()->toDateString(),
                'method' => 'manual_bank',
            ], Request::create('/'));
            $this->fail('Disbursement should require configured product terms.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('application', $exception->errors());
        }

        $this->assertDatabaseCount('loan_accounts', 0);
        $this->assertDatabaseCount('disbursements', 0);
    }

    public function test_disbursement_creates_snapshot_schedule_and_manual_payment_allocations(): void
    {
        $finance = User::factory()->create(['role' => 'finance_officer']);
        $product = LoanProduct::factory()->create([
            'minimum_amount' => '100.00',
            'maximum_amount' => '50000.00',
            'interest_rate' => '20.0000',
            'interest_basis' => 'flat_monthly_equal_principal',
            'processing_fee_rate' => '3.0000',
            'commissioning_fee' => '200.00',
            'daily_late_fee' => '200.00',
            'late_penalty_start' => 'day_after_due',
            'allocation_order' => 'penalties_fees_interest_principal',
            'fee_collection_method' => 'first_installment',
            'default_threshold_days' => 30,
        ]);
        $application = LoanApplication::factory()->create([
            'loan_product_id' => $product->id,
            'status' => 'pending_disbursement',
            'approved_amount' => '1200.00',
            'approved_months' => 2,
        ]);
        $firstDueDate = today()->addMonth()->toDateString();

        $account = app(DisbursementService::class)->confirm($application, $finance, [
            'disbursed_on' => today()->toDateString(),
            'first_repayment_due_on' => $firstDueDate,
            'method' => 'manual_bank',
            'provider_reference' => 'BANK-TEST-001',
        ], Request::create('/'));

        $this->assertSame('1200.00', Money::fromCents(Money::toCents((string) $account->principal_amount)));
        $this->assertSame('1200.00', Money::fromCents(Money::toCents((string) $account->disbursed_amount)));
        $this->assertDatabaseHas('repayment_schedules', [
            'loan_account_id' => $account->id,
            'installment_number' => 1,
            'principal_due' => '600.00',
            'interest_due' => '240.00',
            'fees_due' => '236.00',
        ]);
        $this->assertDatabaseHas('disbursements', [
            'loan_account_id' => $account->id,
            'gross_amount' => '1200.00',
            'amount' => '1200.00',
            'fees_withheld' => '0.00',
        ]);

        $payment = app(PaymentRecordingService::class)->record($account, $finance, [
            'amount' => '500.00',
            'paid_on' => today()->toDateString(),
            'method' => 'manual_cash',
            'external_reference' => 'RCPT-TEST-001',
        ], Request::create('/'));

        $allocation = $payment->allocations->first();
        $this->assertSame('236.00', Money::fromCents(Money::toCents((string) $allocation->fees_amount)));
        $this->assertSame('240.00', Money::fromCents(Money::toCents((string) $allocation->interest_amount)));
        $this->assertSame('24.00', Money::fromCents(Money::toCents((string) $allocation->principal_amount)));
        $this->assertSame('0.00', Money::fromCents(Money::toCents((string) $allocation->penalty_amount)));
        $this->assertSame('1416.00', $account->fresh()->outstandingBalance());

        app(PaymentRecordingService::class)->reverse($payment, $finance, 'Receipt entered in error.', Request::create('/'));

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'reversed']);
        $this->assertDatabaseHas('repayment_schedules', [
            'loan_account_id' => $account->id,
            'installment_number' => 1,
            'principal_paid' => '0.00',
            'interest_paid' => '0.00',
            'fees_paid' => '0.00',
        ]);
        $this->assertSame('0.00', Money::fromCents(Money::toCents((string) $account->fresh()->schedules()->first()->fees_paid)));
        $this->assertSame('1916.00', $account->fresh()->outstandingBalance());
    }

    public function test_overdue_status_refresh_uses_configured_default_threshold(): void
    {
        Carbon::setTestNow('2026-10-30 09:00:00');
        $finance = User::factory()->create(['role' => 'finance_officer']);
        $product = LoanProduct::factory()->create([
            'interest_rate' => '0.0000',
            'interest_basis' => 'flat_monthly_equal_principal',
            'processing_fee_rate' => '0.0000',
            'commissioning_fee' => '0.00',
            'daily_late_fee' => '2.00',
            'late_penalty_start' => 'day_after_due',
            'allocation_order' => 'penalties_fees_interest_principal',
            'fee_collection_method' => 'first_installment',
            'default_threshold_days' => 30,
        ]);
        $application = LoanApplication::factory()->create([
            'loan_product_id' => $product->id,
            'status' => 'pending_disbursement',
            'approved_amount' => '1200.00',
            'approved_months' => 2,
        ]);
        $account = app(DisbursementService::class)->confirm($application, $finance, [
            'disbursed_on' => '2026-08-01',
            'first_repayment_due_on' => '2026-08-30',
            'method' => 'manual_bank',
        ], Request::create('/'));

        app(LoanStatusRefreshService::class)->refresh();

        $this->assertSame('defaulted', $account->fresh()->status);
        $this->assertDatabaseHas('repayment_schedules', [
            'loan_account_id' => $account->id,
            'installment_number' => 1,
            'penalties_due' => '122.00',
            'penalty_days_accrued' => 61,
            'status' => 'overdue',
        ]);
        $this->assertDatabaseHas('loan_charges', [
            'loan_account_id' => $account->id,
            'charge_type' => 'late_penalty',
            'amount' => '122.00',
            'recorded_by' => null,
        ]);
    }

    public function test_staff_can_open_portfolio_repayment_and_overdue_views(): void
    {
        $finance = User::factory()->create(['role' => 'finance_officer']);

        $this->actingAs($finance)->get(route('admin.loans.index'))->assertOk();
        $this->actingAs($finance)->get(route('admin.repayments.index'))->assertOk();
        $this->actingAs($finance)->get(route('admin.overdue.index'))->assertOk();
        $this->actingAs($finance)->get(route('admin.reports'))->assertOk();
    }
}

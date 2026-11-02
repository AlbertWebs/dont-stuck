<?php

namespace Tests\Feature;

use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\User;
use App\Services\DisbursementService;
use App\Services\PaymentRecordingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_filter_the_report_and_download_excel_and_pdf(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $selectedProduct = LoanProduct::factory()->create(['name' => 'Selected product']);
        $otherProduct = LoanProduct::factory()->create(['name' => 'Other product']);
        $selected = LoanApplication::factory()->create([
            'loan_product_id' => $selectedProduct->id,
            'reference' => 'DSS-20261102-A1B2C3D4',
        ]);
        $excluded = LoanApplication::factory()->create([
            'loan_product_id' => $otherProduct->id,
            'reference' => 'DSS-20261102-E5F6G7H8',
        ]);

        $query = ['report_type' => 'applications', 'product_id' => $selectedProduct->id];
        $this->actingAs($administrator)->get(route('admin.reports', $query))
            ->assertOk()
            ->assertSee($selected->reference)
            ->assertDontSee($excluded->reference);

        $excel = $this->actingAs($administrator)->get(route('admin.reports.excel', $query));
        $excel->assertDownload('loan-applications-'.today()->toDateString().'.xls');
        $excelContents = $excel->streamedContent();
        $this->assertStringContainsString('urn:schemas-microsoft-com:office:spreadsheet', $excelContents);
        $this->assertStringContainsString($selected->reference, $excelContents);
        $this->assertStringNotContainsString($excluded->reference, $excelContents);

        $pdf = $this->actingAs($administrator)->get(route('admin.reports.pdf', $query));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdfContents = $pdf->streamedContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdfContents);
        $this->assertStringContainsString('startxref', $pdfContents);
        $this->assertStringContainsString($selected->reference, $pdfContents);
        $this->assertStringNotContainsString($excluded->reference, $pdfContents);
    }

    public function test_loan_officer_reports_only_include_applications_assigned_to_them(): void
    {
        $officer = User::factory()->create(['role' => 'loan_officer']);
        $otherOfficer = User::factory()->create(['role' => 'loan_officer']);
        $assigned = LoanApplication::factory()->create(['assigned_user_id' => $officer->id]);
        $notAssigned = LoanApplication::factory()->create(['assigned_user_id' => $otherOfficer->id]);

        $this->actingAs($officer)->get(route('admin.reports', ['report_type' => 'applications']))
            ->assertOk()
            ->assertSee($assigned->reference)
            ->assertDontSee($notAssigned->reference);
    }

    public function test_guest_cannot_download_financial_reports(): void
    {
        $this->get(route('admin.reports.excel'))->assertRedirect(route('login'));
        $this->get(route('admin.reports.pdf'))->assertRedirect(route('login'));
    }

    public function test_report_filter_rejects_invalid_date_ranges_and_report_types(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($administrator)->get(route('admin.reports', [
            'report_type' => 'unknown',
            'date_from' => '2026-12-01',
            'date_to' => '2026-11-01',
        ]))->assertSessionHasErrors(['report_type', 'date_to']);
    }

    public function test_each_report_type_includes_its_matching_financial_records(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);
        $finance = User::factory()->create(['role' => 'finance_officer']);
        $product = LoanProduct::factory()->create([
            'interest_rate' => '5.0000',
            'interest_basis' => 'reducing_monthly_equal_principal',
            'processing_fee_rate' => '0.0000',
            'commissioning_fee' => '0.00',
            'daily_late_fee' => '1.00',
            'late_penalty_start' => 'day_after_due',
            'allocation_order' => 'penalties_fees_interest_principal',
            'fee_collection_method' => 'first_installment',
            'default_threshold_days' => 30,
        ]);
        $application = LoanApplication::factory()->create([
            'loan_product_id' => $product->id,
            'assigned_user_id' => $finance->id,
            'status' => 'pending_disbursement',
            'approved_amount' => '1200.00',
            'approved_months' => 2,
        ]);
        $account = app(DisbursementService::class)->confirm($application, $finance, [
            'disbursed_on' => today()->subDays(35)->toDateString(),
            'first_repayment_due_on' => today()->subDays(1)->toDateString(),
            'method' => 'manual_bank',
            'provider_reference' => 'BANK-REPORT-001',
        ], Request::create('/'));
        app(PaymentRecordingService::class)->record($account, $finance, [
            'amount' => '200.00',
            'paid_on' => today()->toDateString(),
            'method' => 'manual_cash',
            'external_reference' => 'RCPT-REPORT-001',
        ], Request::create('/'));

        foreach (['applications', 'disbursements', 'repayments', 'outstanding', 'overdue'] as $type) {
            $response = $this->actingAs($administrator)->get(route('admin.reports', ['report_type' => $type]))->assertOk();
            $response->assertSee($type === 'applications' ? $application->reference : $account->reference);
            $excel = $this->actingAs($administrator)->get(route('admin.reports.excel', ['report_type' => $type]));
            $excel->assertOk();
            $this->assertStringContainsString($type === 'applications' ? $application->reference : $account->reference, $excel->streamedContent());
        }

        $statementFilters = [
            'report_type' => 'borrower_statement',
            'borrower_id' => $application->borrower_id,
        ];
        $this->actingAs($administrator)->get(route('admin.reports', $statementFilters))->assertOk()
            ->assertSee($account->reference)
            ->assertSee('Disbursement confirmed')
            ->assertSee('Repayment received');
        $statementExcel = $this->actingAs($administrator)->get(route('admin.reports.excel', $statementFilters));
        $statementExcel->assertOk();
        $this->assertStringContainsString('Late penalty accrued', $statementExcel->streamedContent());
        $statementPdf = $this->actingAs($administrator)->get(route('admin.reports.pdf', $statementFilters));
        $statementPdf->assertOk();
        $this->assertStringContainsString('Loan reference: '.$account->reference, $statementPdf->streamedContent());
    }

    public function test_borrower_statement_requires_a_borrower_filter(): void
    {
        $administrator = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($administrator)->get(route('admin.reports', ['report_type' => 'borrower_statement']))
            ->assertSessionHasErrors('borrower_id');
    }
}

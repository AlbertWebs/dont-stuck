<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExcelXmlExporter;
use App\Services\PdfReportExporter;
use App\Services\PortfolioReportService;
use App\Services\PrintReportPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function index(Request $request, PortfolioReportService $reports, PrintReportPresenter $presenter): View
    {
        $this->authorizeStaff($request);
        $filters = $this->filters($request, $reports);
        $type = $filters['report_type'];
        $summary = $reports->summary($request->user(), $filters);
        $options = $reports->options($request->user());
        $filters['product_name'] = $options['products']->firstWhere('id', $filters['product_id'] ?? null)?->name;
        $filters['borrower_name'] = $options['borrowers']->firstWhere('id', $filters['borrower_id'] ?? null)?->full_name;

        return view('admin.reports', [
            'reportType' => $type,
            'reportTitle' => $reports->titles()[$type],
            'reportHeaders' => $reports->headers($type),
            'reportRows' => $reports->displayRows($type, $request->user(), $filters),
            'summary' => $summary,
            'options' => $options,
            'filters' => $filters,
            'filterSummary' => $presenter->subtitle($filters),
            'reportTypes' => $reports->titles(),
        ]);
    }

    public function excel(Request $request, PortfolioReportService $reports, ExcelXmlExporter $exporter): StreamedResponse
    {
        $this->authorizeStaff($request);
        $filters = $this->filters($request, $reports);
        $type = $filters['report_type'];

        return $exporter->download(
            Str::slug($reports->titles()[$type]).'-'.today()->toDateString().'.xls',
            $reports->headers($type),
            $reports->exportRows($type, $request->user(), $filters),
        );
    }

    public function pdf(Request $request, PortfolioReportService $reports, PdfReportExporter $exporter, PrintReportPresenter $presenter): StreamedResponse
    {
        $this->authorizeStaff($request);
        $filters = $this->filters($request, $reports);
        $type = $filters['report_type'];
        $options = $reports->options($request->user());
        $filters['product_name'] = $options['products']->firstWhere('id', $filters['product_id'] ?? null)?->name;
        $filters['borrower_name'] = $options['borrowers']->firstWhere('id', $filters['borrower_id'] ?? null)?->full_name;

        return $exporter->download(
            Str::slug($reports->titles()[$type]).'-'.today()->toDateString().'.pdf',
            $reports->titles()[$type],
            $presenter->subtitle($filters),
            $reports->headers($type),
            $reports->exportRows($type, $request->user(), $filters),
        );
    }

    /** @return array<string, mixed> */
    private function filters(Request $request, PortfolioReportService $reports): array
    {
        $statuses = ['submitted', 'under_review', 'verification_assessment', 'pending_approval', 'approved', 'rejected', 'finance_processing', 'pending_disbursement', 'disbursed', 'active', 'partially_repaid', 'overdue', 'defaulted', 'fully_repaid', 'closed', 'additional_information_required', 'on_hold', 'cancelled'];

        $filters = $request->validate([
            'report_type' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_keys($reports->titles()))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'product_id' => ['nullable', 'integer', 'exists:loan_products,id'],
            'officer_id' => ['nullable', 'integer', 'exists:users,id'],
            'borrower_id' => ['nullable', 'integer', 'exists:borrowers,id'],
            'application_status' => ['nullable', 'string', 'in:'.implode(',', $statuses)],
            'loan_status' => ['nullable', 'string', 'in:'.implode(',', $statuses)],
        ]);
        $filters['report_type'] ??= $request->query('report_type', 'applications');

        if ($filters['report_type'] === 'borrower_statement' && empty($filters['borrower_id'])) {
            throw ValidationException::withMessages(['borrower_id' => 'Select a borrower to generate an individual statement.']);
        }

        return $filters;
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless($request->user()->hasAnyRole(['administrator', 'loan_officer', 'finance_officer', 'management']), 403);
    }
}

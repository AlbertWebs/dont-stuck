<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExcelXmlExporter;
use App\Services\PdfReportExporter;
use App\Services\PortfolioReportService;
use App\Services\PrintReportPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $showReportDetail = $request->query->has('report_type');
        $summary = $reports->summary($request->user(), $filters);
        $options = $reports->options($request->user());
        $filters['product_name'] = $options['products']->firstWhere('id', $filters['product_id'] ?? null)?->name;
        $filters['borrower_name'] = $options['borrowers']->firstWhere('id', $filters['borrower_id'] ?? null)?->full_name;

        $applicationPalette = ['submitted' => '#d6a81e', 'under_review' => '#5687b2', 'verification_assessment' => '#8f74b7', 'pending_approval' => '#d88a43', 'approved' => '#4e9b67', 'finance_processing' => '#478d8a', 'pending_disbursement' => '#7956a8', 'disbursed' => '#377cb0', 'active' => '#418f66', 'fully_repaid' => '#69ad7e', 'rejected' => '#ba5b50', 'additional_information_required' => '#c98536', 'on_hold' => '#7e8492', 'cancelled' => '#777f86'];
        $accountPalette = ['pending_disbursement' => '#8f74b7', 'active' => '#4e9b67', 'partially_repaid' => '#5687b2', 'overdue' => '#d88a43', 'defaulted' => '#ba5b50', 'fully_repaid' => '#69ad7e', 'closed' => '#777f86'];
        $applicationTotal = (int) $summary['application_statuses']->sum('total');
        $accountTotal = (int) $summary['account_statuses']->sum('total');
        $applicationSlices = $this->donutSlices($summary['application_statuses'], $applicationTotal, $applicationPalette);
        $accountSlices = $this->donutSlices($summary['account_statuses'], $accountTotal, $accountPalette);
        $trendMaximum = max(1, ...array_map(fn (array $month): float => max((float) $month['disbursed'], (float) $month['collected'], (float) $month['expected']), $summary['monthly_trends']));
        $agingMaximum = max(1, ...array_map(fn (array $bucket): float => (float) $bucket['amount'], array_values($summary['aging'])));
        $reportRows = $reports->displayRows($type, $request->user(), $filters);
        $moneyColumns = match ($type) {
            'applications' => [3, 4],
            'disbursements' => [3, 4, 5],
            'repayments' => [3, 4, 5, 6, 7],
            'outstanding' => [3, 4, 5, 6, 7],
            'overdue' => [5, 6, 7, 8, 9],
            'borrower_statement' => [3, 4, 5, 6, 7],
        };
        $reportTotals = [];
        foreach ($moneyColumns as $column) {
            $total = array_sum(array_map(fn (array $row): float => (float) ($row[$column] ?? 0), $reportRows));
            $reportTotals[$column] = 'KSh '.number_format($total, 2);
        }

        return view('admin.reports', [
            'reportType' => $type,
            'reportTitle' => $reports->titles()[$type],
            'reportHeaders' => $reports->headers($type),
            'reportRows' => $reportRows,
            'reportTotals' => $reportTotals,
            'showReportDetail' => $showReportDetail,
            'summary' => $summary,
            'options' => $options,
            'filters' => $filters,
            'filterSummary' => $presenter->subtitle($filters),
            'reportTypes' => $reports->titles(),
            'applicationPalette' => $applicationPalette,
            'accountPalette' => $accountPalette,
            'applicationTotal' => $applicationTotal,
            'accountTotal' => $accountTotal,
            'applicationSlices' => $applicationSlices,
            'accountSlices' => $accountSlices,
            'trendMaximum' => $trendMaximum,
            'agingMaximum' => $agingMaximum,
        ]);
    }

    /** @param Collection<int, object> $statuses
     * @param  array<string, string>  $palette
     * @return array<int, string>
     */
    private function donutSlices(Collection $statuses, int $total, array $palette): array
    {
        $slices = [];
        $offset = 0.0;

        foreach ($statuses as $status) {
            $end = $offset + ($total > 0 ? ((float) $status->total / $total) * 100 : 0);
            $slices[] = ($palette[$status->status] ?? '#91999c').' '.$offset.'% '.$end.'%';
            $offset = $end;
        }

        return $slices;
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

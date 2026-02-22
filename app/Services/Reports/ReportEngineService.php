<?php

namespace App\Services\Reports;

use App\Actions\GenerateReportReferenceAction;
use App\Exports\ArrayReportExport;
use App\Models\CompanyProfile;
use App\Models\ReportRun;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportEngineService
{
    public function __construct(private readonly GenerateReportReferenceAction $generateReportReference) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function json(string $reportType, array $rows, string $fromDate, string $toDate, ?int $branchId): JsonResponse
    {
        $meta = $this->recordRun($reportType, $rows, $fromDate, $toDate, $branchId);

        return response()->json([
            'reference_number' => $meta->reference_number,
            'page_count' => $meta->page_count,
            'rows' => $rows,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function pdf(string $reportType, array $rows, string $fromDate, string $toDate, ?int $branchId): Response
    {
        $meta = $this->recordRun($reportType, $rows, $fromDate, $toDate, $branchId);
        $company = CompanyProfile::query()->first();

        $pdf = Pdf::loadView('reports.generic', [
            'title' => strtoupper(str_replace('_', ' ', $reportType)),
            'rows' => $rows,
            'referenceNumber' => $meta->reference_number,
            'pageCount' => $meta->page_count,
            'company' => $company,
        ]);

        return $pdf->download($reportType.'-'.$meta->reference_number.'.pdf');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function excel(string $reportType, array $rows, string $fromDate, string $toDate, ?int $branchId): BinaryFileResponse
    {
        $meta = $this->recordRun($reportType, $rows, $fromDate, $toDate, $branchId);
        $headings = isset($rows[0]) ? array_keys($rows[0]) : [];

        return Excel::download(
            new ArrayReportExport($headings, $rows),
            $reportType.'-'.$meta->reference_number.'.xlsx',
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function recordRun(string $reportType, array $rows, string $fromDate, string $toDate, ?int $branchId): ReportRun
    {
        $pageCount = max(1, (int) ceil(max(count($rows), 1) / 50));

        return ReportRun::query()->create([
            'generated_by' => Auth::id(),
            'branch_id' => $branchId,
            'report_type' => $reportType,
            'reference_number' => $this->generateReportReference->execute($reportType),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'page_count' => $pageCount,
            'generated_at' => now(),
        ]);
    }
}

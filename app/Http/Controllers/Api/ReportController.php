<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ReportFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReportFilterRequest;
use App\Services\Reports\BooksReportService;
use App\Services\Reports\FinancialStatementService;
use App\Services\Reports\ReportEngineService;
use App\Services\Reports\TrialBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly TrialBalanceService $trialBalanceService,
        private readonly FinancialStatementService $financialStatementService,
        private readonly BooksReportService $booksReportService,
        private readonly ReportEngineService $reportEngineService,
    ) {}

    public function trialBalance(ReportFilterRequest $request): JsonResponse|Response|BinaryFileResponse
    {
        $filter = $this->buildFilter($request);
        $rows = $this->trialBalanceService->generate($filter);

        return $this->respond('trial_balance', $rows, $request);
    }

    public function balanceSheet(ReportFilterRequest $request): JsonResponse|Response|BinaryFileResponse
    {
        $filter = $this->buildFilter($request);
        $rows = [$this->financialStatementService->balanceSheet($filter)];

        return $this->respond('balance_sheet', $rows, $request);
    }

    public function incomeStatement(ReportFilterRequest $request): JsonResponse|Response|BinaryFileResponse
    {
        $filter = $this->buildFilter($request);
        $rows = [$this->financialStatementService->incomeStatement($filter)];

        return $this->respond('income_statement', $rows, $request);
    }

    public function journalBook(ReportFilterRequest $request): JsonResponse|Response|BinaryFileResponse
    {
        $filter = $this->buildFilter($request);
        $rows = $this->booksReportService->journalBook($filter);

        return $this->respond('journal_book', $rows, $request);
    }

    public function generalLedgerBook(ReportFilterRequest $request): JsonResponse|Response|BinaryFileResponse
    {
        $filter = $this->buildFilter($request);
        $rows = $this->booksReportService->generalLedgerBook($filter);

        return $this->respond('general_ledger_book', $rows, $request);
    }

    public function accountsReceivableLedger(ReportFilterRequest $request): JsonResponse|Response|BinaryFileResponse
    {
        $filter = $this->buildFilter($request);
        $customerId = $request->integer('customer_id') ?: null;
        $rows = $this->booksReportService->accountsReceivableLedger($filter, $customerId);

        return $this->respond('accounts_receivable_ledger', $rows, $request);
    }

    public function accountsPayableLedger(ReportFilterRequest $request): JsonResponse|Response|BinaryFileResponse
    {
        $filter = $this->buildFilter($request);
        $supplierId = $request->integer('supplier_id') ?: null;
        $rows = $this->booksReportService->accountsPayableLedger($filter, $supplierId);

        return $this->respond('accounts_payable_ledger', $rows, $request);
    }

    public function customerLedger(ReportFilterRequest $request): JsonResponse|Response|BinaryFileResponse
    {
        return $this->accountsReceivableLedger($request);
    }

    public function supplierLedger(ReportFilterRequest $request): JsonResponse|Response|BinaryFileResponse
    {
        return $this->accountsPayableLedger($request);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function respond(string $reportType, array $rows, ReportFilterRequest $request): JsonResponse|Response|BinaryFileResponse
    {
        $format = $request->string('format', 'json')->toString();
        $fromDate = $request->string('from_date')->toString();
        $toDate = $request->string('to_date')->toString();
        $branchId = $request->integer('branch_id') ?: null;

        return match ($format) {
            'pdf' => $this->reportEngineService->pdf($reportType, $rows, $fromDate, $toDate, $branchId),
            'excel' => $this->reportEngineService->excel($reportType, $rows, $fromDate, $toDate, $branchId),
            default => $this->reportEngineService->json($reportType, $rows, $fromDate, $toDate, $branchId),
        };
    }

    private function buildFilter(ReportFilterRequest $request): ReportFilterData
    {
        return new ReportFilterData(
            fromDate: CarbonImmutable::parse($request->string('from_date')->toString()),
            toDate: CarbonImmutable::parse($request->string('to_date')->toString()),
            branchId: $request->integer('branch_id') ?: null,
            period: $request->string('period')->toString() ?: null,
        );
    }
}

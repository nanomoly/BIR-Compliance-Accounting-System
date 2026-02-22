<?php

namespace App\Services\Reports;

use App\DTOs\ReportFilterData;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;

class FinancialStatementService
{
    /**
     * @return array<string, float>
     */
    public function balanceSheet(ReportFilterData $filter): array
    {
        return [
            'assets' => $this->netByType('asset', $filter),
            'liabilities' => $this->netByType('liability', $filter),
            'equity' => $this->netByType('equity', $filter),
        ];
    }

    /**
     * @return array<string, float>
     */
    public function incomeStatement(ReportFilterData $filter): array
    {
        $revenue = $this->netByType('revenue', $filter);
        $expense = $this->netByType('expense', $filter);

        return [
            'revenue' => $revenue,
            'expense' => $expense,
            'net_income' => $revenue - $expense,
        ];
    }

    private function netByType(string $type, ReportFilterData $filter): float
    {
        $query = Ledger::query()
            ->join('accounts', 'accounts.id', '=', 'ledgers.account_id')
            ->where('accounts.type', $type)
            ->whereBetween('posting_date', [$filter->fromDate->toDateString(), $filter->toDate->toDateString()]);

        if ($filter->branchId !== null) {
            $query->where('ledgers.branch_id', $filter->branchId);
        }

        $result = $query->select([
            DB::raw('COALESCE(SUM(ledgers.debit),0) as debit_total'),
            DB::raw('COALESCE(SUM(ledgers.credit),0) as credit_total'),
        ])->first();

        return ((float) ($result->debit_total ?? 0)) - ((float) ($result->credit_total ?? 0));
    }
}

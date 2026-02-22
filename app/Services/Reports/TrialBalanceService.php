<?php

namespace App\Services\Reports;

use App\DTOs\ReportFilterData;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;

class TrialBalanceService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function generate(ReportFilterData $filter): array
    {
        $query = Ledger::query()
            ->select([
                'accounts.code',
                'accounts.name',
                DB::raw('SUM(ledgers.debit) as debit_total'),
                DB::raw('SUM(ledgers.credit) as credit_total'),
            ])
            ->join('accounts', 'accounts.id', '=', 'ledgers.account_id')
            ->whereBetween('posting_date', [$filter->fromDate->toDateString(), $filter->toDate->toDateString()])
            ->groupBy('accounts.code', 'accounts.name')
            ->orderBy('accounts.code');

        if ($filter->branchId !== null) {
            $query->where('ledgers.branch_id', $filter->branchId);
        }

        return $query->get()->map(fn ($row): array => [
            'account_code' => $row->code,
            'account_name' => $row->name,
            'debit' => (float) $row->debit_total,
            'credit' => (float) $row->credit_total,
        ])->all();
    }
}

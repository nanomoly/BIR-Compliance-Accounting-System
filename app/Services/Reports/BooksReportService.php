<?php

namespace App\Services\Reports;

use App\DTOs\ReportFilterData;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Ledger;

class BooksReportService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function journalBook(ReportFilterData $filter): array
    {
        $query = JournalEntry::query()
            ->with('lines.account')
            ->whereBetween('entry_date', [$filter->fromDate->toDateString(), $filter->toDate->toDateString()])
            ->latest('entry_date');

        if ($filter->branchId !== null) {
            $query->where('branch_id', $filter->branchId);
        }

        return $query->get()->map(fn (JournalEntry $entry): array => [
            'entry_number' => $entry->entry_number,
            'control_number' => $entry->control_number,
            'entry_date' => $entry->entry_date?->toDateString(),
            'journal_type' => $entry->journal_type->value,
            'description' => $entry->description,
            'total_debit' => (float) $entry->total_debit,
            'total_credit' => (float) $entry->total_credit,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generalLedgerBook(ReportFilterData $filter): array
    {
        $query = Ledger::query()
            ->with('account')
            ->whereBetween('posting_date', [$filter->fromDate->toDateString(), $filter->toDate->toDateString()])
            ->orderBy('posting_date');

        if ($filter->branchId !== null) {
            $query->where('branch_id', $filter->branchId);
        }

        return $query->get()->map(fn (Ledger $ledger): array => [
            'date' => $ledger->posting_date?->toDateString(),
            'account_code' => $ledger->account?->code,
            'account_name' => $ledger->account?->name,
            'debit' => (float) $ledger->debit,
            'credit' => (float) $ledger->credit,
            'running_balance' => (float) $ledger->running_balance,
            'control_number' => $ledger->control_number,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function accountsReceivableLedger(ReportFilterData $filter, ?int $customerId = null): array
    {
        return $this->subsidiaryLedger($filter, 'customer_id', $customerId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function accountsPayableLedger(ReportFilterData $filter, ?int $supplierId = null): array
    {
        return $this->subsidiaryLedger($filter, 'supplier_id', $supplierId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function subsidiaryLedger(ReportFilterData $filter, string $foreignKey, ?int $partyId): array
    {
        $query = JournalEntryLine::query()
            ->with(['journalEntry', 'account'])
            ->whereNotNull($foreignKey)
            ->whereHas('journalEntry', function ($q) use ($filter): void {
                $q->whereBetween('entry_date', [$filter->fromDate->toDateString(), $filter->toDate->toDateString()]);

                if ($filter->branchId !== null) {
                    $q->where('branch_id', $filter->branchId);
                }
            });

        if ($partyId !== null) {
            $query->where($foreignKey, $partyId);
        }

        return $query->get()->map(fn (JournalEntryLine $line): array => [
            'entry_number' => $line->journalEntry?->entry_number,
            'entry_date' => $line->journalEntry?->entry_date?->toDateString(),
            'account_code' => $line->account?->code,
            'account_name' => $line->account?->name,
            'debit' => (float) $line->debit,
            'credit' => (float) $line->credit,
            'particulars' => $line->particulars,
        ])->all();
    }
}

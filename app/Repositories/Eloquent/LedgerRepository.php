<?php

namespace App\Repositories\Eloquent;

use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Repositories\Contracts\LedgerRepositoryInterface;

class LedgerRepository implements LedgerRepositoryInterface
{
    public function getLatestRunningBalance(int $accountId): float
    {
        $last = Ledger::query()
            ->where('account_id', $accountId)
            ->latest('id')
            ->first();

        return $last?->running_balance ? (float) $last->running_balance : 0.0;
    }

    public function postJournalEntry(JournalEntry $journalEntry): void
    {
        foreach ($journalEntry->lines as $line) {
            $balance = $this->getLatestRunningBalance($line->account_id);
            $newBalance = $balance + (float) $line->debit - (float) $line->credit;

            Ledger::query()->create([
                'branch_id' => $journalEntry->branch_id,
                'account_id' => $line->account_id,
                'journal_entry_id' => $journalEntry->id,
                'journal_entry_line_id' => $line->id,
                'posting_date' => $journalEntry->entry_date,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'running_balance' => $newBalance,
                'control_number' => $journalEntry->control_number,
            ]);
        }
    }
}

<?php

namespace App\Repositories\Contracts;

use App\Models\JournalEntry;

interface LedgerRepositoryInterface
{
    public function getLatestRunningBalance(int $accountId): float;

    public function postJournalEntry(JournalEntry $journalEntry): void;
}

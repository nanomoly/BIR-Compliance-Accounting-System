<?php

namespace App\Listeners;

use App\Events\JournalEntryPosted;
use App\Models\AuditLog;
use App\Repositories\Contracts\LedgerRepositoryInterface;

class PostJournalEntryToLedger
{
    public function __construct(private readonly LedgerRepositoryInterface $ledgerRepository) {}

    public function handle(JournalEntryPosted $event): void
    {
        $this->ledgerRepository->postJournalEntry($event->journalEntry);

        AuditLog::query()->create([
            'user_id' => $event->journalEntry->approved_by,
            'event' => 'posted',
            'auditable_type' => $event->journalEntry::class,
            'auditable_id' => $event->journalEntry->id,
            'old_values' => null,
            'new_values' => $event->journalEntry->toArray(),
            'occurred_at' => now(),
        ]);
    }
}

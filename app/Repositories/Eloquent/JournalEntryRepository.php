<?php

namespace App\Repositories\Eloquent;

use App\Models\JournalEntry;
use App\Repositories\Contracts\JournalEntryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class JournalEntryRepository implements JournalEntryRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return JournalEntry::query()
            ->with(['lines.account', 'creator', 'approver'])
            ->latest('entry_date')
            ->paginate($perPage);
    }

    public function findByIdWithLines(int $id): JournalEntry
    {
        return JournalEntry::query()->with(['lines.account'])->findOrFail($id);
    }

    public function createWithLines(array $payload, array $lines): JournalEntry
    {
        $entry = JournalEntry::query()->create($payload);
        $entry->lines()->createMany($lines);

        return $entry->load('lines.account');
    }

    public function save(JournalEntry $journalEntry): JournalEntry
    {
        $journalEntry->save();

        return $journalEntry->refresh();
    }
}

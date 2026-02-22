<?php

namespace App\Repositories\Contracts;

use App\Models\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface JournalEntryRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function findByIdWithLines(int $id): JournalEntry;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createWithLines(array $payload, array $lines): JournalEntry;

    public function save(JournalEntry $journalEntry): JournalEntry;
}

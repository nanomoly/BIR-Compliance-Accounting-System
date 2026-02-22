<?php

namespace App\Services\Accounting;

use App\Actions\GenerateControlNumberAction;
use App\DTOs\StoreJournalEntryData;
use App\Enums\JournalStatus;
use App\Events\JournalEntryPosted;
use App\Models\JournalEntry;
use App\Repositories\Contracts\JournalEntryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalEntryService
{
    public function __construct(
        private readonly JournalEntryRepositoryInterface $journalEntryRepository,
        private readonly GenerateControlNumberAction $generateControlNumber,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->journalEntryRepository->paginate($perPage);
    }

    public function find(int $id): JournalEntry
    {
        return $this->journalEntryRepository->findByIdWithLines($id);
    }

    public function create(StoreJournalEntryData $dto, int $userId): JournalEntry
    {
        $lines = array_map(fn ($line) => $line->toArray(), $dto->lines);
        $totalDebit = collect($lines)->sum('debit');
        $totalCredit = collect($lines)->sum('credit');

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw ValidationException::withMessages([
                'lines' => 'Debit and credit totals must be equal.',
            ]);
        }

        return DB::transaction(function () use ($dto, $userId, $lines, $totalDebit, $totalCredit): JournalEntry {
            return $this->journalEntryRepository->createWithLines([
                'branch_id' => $dto->branchId,
                'created_by' => $userId,
                'journal_type' => $dto->journalType->value,
                'entry_number' => $this->generateControlNumber->execute('JE'),
                'control_number' => $this->generateControlNumber->execute('CTL'),
                'entry_date' => $dto->entryDate,
                'reference_no' => $dto->referenceNo,
                'description' => $dto->description,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => JournalStatus::DRAFT->value,
            ], $lines);
        });
    }

    public function post(JournalEntry $journalEntry, int $approvedBy): JournalEntry
    {
        if ($journalEntry->status !== JournalStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft journal entries can be posted.',
            ]);
        }

        $journalEntry = DB::transaction(function () use ($journalEntry, $approvedBy): JournalEntry {
            $journalEntry->status = JournalStatus::POSTED;
            $journalEntry->approved_by = $approvedBy;
            $journalEntry->posted_at = now();
            $journalEntry->locked_at = now();

            return $this->journalEntryRepository->save($journalEntry);
        });

        event(new JournalEntryPosted($journalEntry->load('lines')));

        return $journalEntry;
    }

    public function reverse(JournalEntry $postedEntry, int $userId): JournalEntry
    {
        if ($postedEntry->status !== JournalStatus::POSTED) {
            throw ValidationException::withMessages([
                'status' => 'Only posted entries can be reversed.',
            ]);
        }

        return DB::transaction(function () use ($postedEntry, $userId): JournalEntry {
            $lines = $postedEntry->lines->map(function ($line): array {
                return [
                    'account_id' => $line->account_id,
                    'customer_id' => $line->customer_id,
                    'supplier_id' => $line->supplier_id,
                    'particulars' => 'Reversal: '.$line->particulars,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                ];
            })->all();

            $reversal = $this->journalEntryRepository->createWithLines([
                'branch_id' => $postedEntry->branch_id,
                'created_by' => $userId,
                'journal_type' => $postedEntry->journal_type->value,
                'entry_number' => $this->generateControlNumber->execute('REV'),
                'control_number' => $this->generateControlNumber->execute('CTL'),
                'entry_date' => now()->toDateString(),
                'reference_no' => $postedEntry->entry_number,
                'description' => 'Reversal entry for '.$postedEntry->entry_number,
                'total_debit' => $postedEntry->total_credit,
                'total_credit' => $postedEntry->total_debit,
                'status' => JournalStatus::DRAFT->value,
                'reversed_from_id' => $postedEntry->id,
            ], $lines);

            $postedEntry->status = JournalStatus::REVERSED;
            $this->journalEntryRepository->save($postedEntry);

            return $reversal;
        });
    }
}

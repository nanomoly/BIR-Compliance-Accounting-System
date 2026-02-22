<?php

namespace App\DTOs;

use App\Enums\JournalType;
use Carbon\CarbonImmutable;

class StoreJournalEntryData
{
    /**
     * @param  array<int, JournalEntryLineData>  $lines
     */
    public function __construct(
        public readonly int $branchId,
        public readonly JournalType $journalType,
        public readonly CarbonImmutable $entryDate,
        public readonly string $description,
        public readonly ?string $referenceNo,
        public readonly array $lines,
    ) {}
}

<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;

class ReportFilterData
{
    public function __construct(
        public readonly CarbonImmutable $fromDate,
        public readonly CarbonImmutable $toDate,
        public readonly ?int $branchId = null,
        public readonly ?string $period = null,
    ) {}
}

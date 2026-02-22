<?php

namespace App\Actions;

use Carbon\CarbonImmutable;

class GenerateReportReferenceAction
{
    public function execute(string $reportType): string
    {
        return sprintf(
            'RPT-%s-%s-%s',
            strtoupper(substr($reportType, 0, 4)),
            CarbonImmutable::now()->format('YmdHis'),
            str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
        );
    }
}

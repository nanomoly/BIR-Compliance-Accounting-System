<?php

namespace App\Actions;

use Carbon\CarbonImmutable;

class GenerateControlNumberAction
{
    public function execute(string $prefix = 'TXN'): string
    {
        return sprintf(
            '%s-%s-%s',
            strtoupper($prefix),
            CarbonImmutable::now()->format('YmdHis'),
            str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        );
    }
}

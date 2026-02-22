<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ArrayReportExport implements FromArray, WithHeadings
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
    ) {}

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return array_map(static fn (array $row): array => array_values($row), $this->rows);
    }
}

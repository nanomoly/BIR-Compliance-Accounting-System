<?php

namespace App\Repositories\Contracts;

use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InvoiceRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id): Invoice;

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $lines
     */
    public function createWithLines(array $payload, array $lines): Invoice;

    public function save(Invoice $invoice): Invoice;
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Invoice::query()
            ->with(['customer', 'supplier', 'lines', 'transmissions'])
            ->latest('invoice_date')
            ->paginate($perPage);
    }

    public function findById(int $id): Invoice
    {
        return Invoice::query()
            ->with(['customer', 'supplier', 'lines', 'transmissions'])
            ->findOrFail($id);
    }

    public function createWithLines(array $payload, array $lines): Invoice
    {
        $invoice = Invoice::query()->create($payload);
        $invoice->lines()->createMany($lines);

        return $invoice->load(['customer', 'supplier', 'lines', 'transmissions']);
    }

    public function save(Invoice $invoice): Invoice
    {
        $invoice->save();

        return $invoice->refresh()->load(['customer', 'supplier', 'lines', 'transmissions']);
    }
}

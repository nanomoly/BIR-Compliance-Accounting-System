<?php

namespace App\Services\Accounting;

use App\Actions\GenerateControlNumberAction;
use App\Enums\InvoiceStatus;
use App\Models\CompanyProfile;
use App\Models\EInvoiceTransmission;
use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EInvoicingService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly GenerateControlNumberAction $generateControlNumber,
    ) {
    }

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->invoiceRepository->paginate($perPage);
    }

    public function find(int $id): Invoice
    {
        return $this->invoiceRepository->findById($id);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload, int $userId): Invoice
    {
        $lines = collect($payload['lines'] ?? [])->map(function (array $line): array {
            $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];

            return [
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'line_total' => $lineTotal,
            ];
        })->all();

        $subtotal = collect($lines)->sum('line_total');
        $vatAmount = isset($payload['vat_amount']) ? (float) $payload['vat_amount'] : 0.0;
        $total = $subtotal + $vatAmount;

        return DB::transaction(function () use ($payload, $userId, $lines, $subtotal, $vatAmount, $total): Invoice {
            return $this->invoiceRepository->createWithLines([
                'branch_id' => $payload['branch_id'],
                'customer_id' => $payload['customer_id'] ?? null,
                'supplier_id' => $payload['supplier_id'] ?? null,
                'journal_entry_id' => $payload['journal_entry_id'] ?? null,
                'created_by' => $userId,
                'invoice_number' => $this->generateControlNumber->execute('INV'),
                'control_number' => $this->generateControlNumber->execute('EINV'),
                'invoice_type' => $payload['invoice_type'],
                'invoice_date' => $payload['invoice_date'],
                'due_date' => $payload['due_date'] ?? null,
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total_amount' => $total,
                'status' => InvoiceStatus::DRAFT->value,
                'remarks' => $payload['remarks'] ?? null,
            ], $lines);
        });
    }

    public function issue(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft invoices can be issued.',
            ]);
        }

        $invoice->status = InvoiceStatus::ISSUED;
        $invoice->issued_at = now();
        $invoice->locked_at = now();

        return $this->invoiceRepository->save($invoice);
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::ISSUED) {
            throw ValidationException::withMessages([
                'status' => 'Only issued invoices can be cancelled.',
            ]);
        }

        $invoice->status = InvoiceStatus::CANCELLED;

        return $this->invoiceRepository->save($invoice);
    }

    public function transmit(Invoice $invoice): EInvoiceTransmission
    {
        if ($invoice->status !== InvoiceStatus::ISSUED) {
            throw ValidationException::withMessages([
                'status' => 'Invoice must be issued before transmission.',
            ]);
        }

        $invoice = $invoice->loadMissing(['branch', 'customer', 'supplier', 'lines']);
        $company = CompanyProfile::query()->first();

        $requestPayload = [
            'document_type' => strtoupper($invoice->invoice_type->value),
            'invoice_number' => $invoice->invoice_number,
            'control_number' => $invoice->control_number,
            'invoice_date' => optional($invoice->invoice_date)->format('Y-m-d'),
            'due_date' => optional($invoice->due_date)->format('Y-m-d'),
            'currency' => 'PHP',
            'seller' => [
                'name' => $company?->name,
                'tin' => $company?->tin,
                'address' => $company?->registered_address,
                'branch_code' => $invoice->branch?->code,
                'branch_name' => $invoice->branch?->name,
            ],
            'buyer' => [
                'name' => $invoice->customer?->name ?? $invoice->supplier?->name,
                'tin' => $invoice->customer?->tin ?? $invoice->supplier?->tin,
                'address' => $invoice->customer?->address ?? $invoice->supplier?->address,
                'email' => $invoice->customer?->email ?? $invoice->supplier?->email,
            ],
            'line_items' => $invoice->lines->map(fn ($line): array => [
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'line_total' => (float) $line->line_total,
            ])->values()->all(),
            'totals' => [
                'subtotal' => (float) $invoice->subtotal,
                'vat_amount' => (float) $invoice->vat_amount,
                'grand_total' => (float) $invoice->total_amount,
            ],
            'remarks' => $invoice->remarks,
        ];

        return $invoice->transmissions()->create([
            'provider' => 'bir-eis',
            'status' => 'submitted',
            'reference_number' => $this->generateControlNumber->execute('BIRREF'),
            'request_payload' => $requestPayload,
            'response_payload' => [
                'message' => 'Transmission accepted (simulated).',
            ],
            'transmitted_at' => now(),
        ]);
    }
}

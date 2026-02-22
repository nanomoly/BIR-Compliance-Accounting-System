<?php

namespace App\Http\Controllers\Api;

use App\Actions\GenerateControlNumberAction;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSalesOrderRequest;
use App\Http\Requests\Api\UpdateSalesOrderRequest;
use App\Models\Invoice;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    public function __construct(private readonly GenerateControlNumberAction $generateControlNumber)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('sales.view'), 403);

        $query = SalesOrder::query()
            ->with([
                'customer:id,code,name',
                'branch:id,code,name',
                'invoice:id,invoice_number,status',
            ])
            ->latest('order_date')
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->string('status'));
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreSalesOrderRequest $request): JsonResponse
    {
        $order = DB::transaction(function () use ($request): SalesOrder {
            $payload = $request->validated();
            $lines = collect($payload['lines'])->map(function (array $line): array {
                $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];

                return [
                    'description' => $line['description'],
                    'quantity' => (float) $line['quantity'],
                    'unit_price' => (float) $line['unit_price'],
                    'line_total' => $lineTotal,
                ];
            })->all();

            $subtotal = collect($lines)->sum('line_total');
            $vatAmount = (float) ($payload['vat_amount'] ?? 0);
            $totalAmount = $subtotal + $vatAmount;

            $order = SalesOrder::query()->create([
                'branch_id' => $payload['branch_id'],
                'customer_id' => $payload['customer_id'],
                'created_by' => $request->user()?->id,
                'order_number' => $this->generateControlNumber->execute('SO'),
                'order_date' => $payload['order_date'],
                'due_date' => $payload['due_date'] ?? null,
                'status' => 'draft',
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total_amount' => $totalAmount,
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $order->lines()->createMany($lines);

            return $order;
        });

        return response()->json($order->load(['lines', 'customer:id,code,name', 'branch:id,code,name']), 201);
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder): JsonResponse
    {
        if ($salesOrder->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft sales orders can be updated.',
            ], 422);
        }

        $updated = DB::transaction(function () use ($request, $salesOrder): SalesOrder {
            $payload = $request->validated();
            $lines = collect($payload['lines'])->map(function (array $line): array {
                $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];

                return [
                    'description' => $line['description'],
                    'quantity' => (float) $line['quantity'],
                    'unit_price' => (float) $line['unit_price'],
                    'line_total' => $lineTotal,
                ];
            })->all();

            $subtotal = collect($lines)->sum('line_total');
            $vatAmount = (float) ($payload['vat_amount'] ?? 0);
            $totalAmount = $subtotal + $vatAmount;

            $salesOrder->update([
                'branch_id' => $payload['branch_id'],
                'customer_id' => $payload['customer_id'],
                'order_date' => $payload['order_date'],
                'due_date' => $payload['due_date'] ?? null,
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total_amount' => $totalAmount,
                'remarks' => $payload['remarks'] ?? null,
            ]);

            $salesOrder->lines()->delete();
            $salesOrder->lines()->createMany($lines);

            return $salesOrder->refresh();
        });

        return response()->json($updated->load(['lines', 'customer:id,code,name', 'branch:id,code,name']));
    }

    public function confirm(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        abort_unless($request->user()?->can('sales.update'), 403);

        if ($salesOrder->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft sales orders can be confirmed.',
            ], 422);
        }

        $salesOrder->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return response()->json($salesOrder->refresh()->load(['lines', 'customer:id,code,name', 'branch:id,code,name']));
    }

    public function convertToInvoice(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        abort_unless($request->user()?->can('sales.update'), 403);

        if (! in_array($salesOrder->status, ['confirmed', 'invoiced'], true)) {
            return response()->json([
                'message' => 'Sales order must be confirmed before converting to invoice.',
            ], 422);
        }

        if ($salesOrder->invoice_id) {
            $invoice = Invoice::query()->find($salesOrder->invoice_id);

            return response()->json([
                'message' => 'Sales order already has an invoice.',
                'invoice' => $invoice,
            ]);
        }

        $invoice = DB::transaction(function () use ($salesOrder, $request): Invoice {
            $salesOrder->loadMissing('lines');

            $invoice = Invoice::query()->create([
                'branch_id' => $salesOrder->branch_id,
                'customer_id' => $salesOrder->customer_id,
                'created_by' => $request->user()?->id,
                'invoice_number' => $this->generateControlNumber->execute('INV'),
                'control_number' => $this->generateControlNumber->execute('EINV'),
                'invoice_type' => InvoiceType::SALES->value,
                'invoice_date' => now()->toDateString(),
                'due_date' => $salesOrder->due_date,
                'subtotal' => $salesOrder->subtotal,
                'vat_amount' => $salesOrder->vat_amount,
                'total_amount' => $salesOrder->total_amount,
                'status' => InvoiceStatus::DRAFT->value,
                'remarks' => 'Generated from sales order '.$salesOrder->order_number,
            ]);

            $invoiceLines = $salesOrder->lines->map(fn ($line): array => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'line_total' => $line->line_total,
            ])->all();

            $invoice->lines()->createMany($invoiceLines);

            $salesOrder->update([
                'invoice_id' => $invoice->id,
                'status' => 'invoiced',
            ]);

            return $invoice;
        });

        return response()->json([
            'message' => 'Sales order converted to draft invoice.',
            'invoice' => $invoice,
        ], 201);
    }

    public function destroy(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        abort_unless($request->user()?->can('sales.delete'), 403);

        if ($salesOrder->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft sales orders can be deleted.',
            ], 422);
        }

        $salesOrder->delete();

        return response()->json(status: 204);
    }
}

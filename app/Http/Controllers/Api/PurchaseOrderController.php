<?php

namespace App\Http\Controllers\Api;

use App\Actions\GenerateControlNumberAction;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePurchaseOrderRequest;
use App\Http\Requests\Api\UpdatePurchaseOrderRequest;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly GenerateControlNumberAction $generateControlNumber)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('purchases.view'), 403);

        $query = PurchaseOrder::query()
            ->with([
                'supplier:id,code,name',
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

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $order = DB::transaction(function () use ($request): PurchaseOrder {
            $payload = $request->validated();
            $lines = collect($payload['lines'])->map(function (array $line): array {
                $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];

                return [
                    'description' => $line['description'],
                    'quantity' => (float) $line['quantity'],
                    'received_quantity' => 0,
                    'unit_price' => (float) $line['unit_price'],
                    'line_total' => $lineTotal,
                ];
            })->all();

            $subtotal = collect($lines)->sum('line_total');
            $vatAmount = (float) ($payload['vat_amount'] ?? 0);
            $totalAmount = $subtotal + $vatAmount;

            $order = PurchaseOrder::query()->create([
                'branch_id' => $payload['branch_id'],
                'supplier_id' => $payload['supplier_id'],
                'created_by' => $request->user()?->id,
                'order_number' => $this->generateControlNumber->execute('PO'),
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

        return response()->json($order->load(['lines', 'supplier:id,code,name', 'branch:id,code,name']), 201);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (! in_array($purchaseOrder->status, ['draft', 'ordered'], true)) {
            return response()->json([
                'message' => 'Only draft or ordered purchase orders can be updated.',
            ], 422);
        }

        $updated = DB::transaction(function () use ($request, $purchaseOrder): PurchaseOrder {
            $payload = $request->validated();
            $lines = collect($payload['lines'])->map(function (array $line): array {
                $lineTotal = (float) $line['quantity'] * (float) $line['unit_price'];

                return [
                    'description' => $line['description'],
                    'quantity' => (float) $line['quantity'],
                    'received_quantity' => 0,
                    'unit_price' => (float) $line['unit_price'],
                    'line_total' => $lineTotal,
                ];
            })->all();

            $subtotal = collect($lines)->sum('line_total');
            $vatAmount = (float) ($payload['vat_amount'] ?? 0);
            $totalAmount = $subtotal + $vatAmount;

            $purchaseOrder->update([
                'branch_id' => $payload['branch_id'],
                'supplier_id' => $payload['supplier_id'],
                'order_date' => $payload['order_date'],
                'due_date' => $payload['due_date'] ?? null,
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total_amount' => $totalAmount,
                'remarks' => $payload['remarks'] ?? null,
                'status' => 'ordered',
            ]);

            $purchaseOrder->lines()->delete();
            $purchaseOrder->lines()->createMany($lines);

            return $purchaseOrder->refresh();
        });

        return response()->json($updated->load(['lines', 'supplier:id,code,name', 'branch:id,code,name']));
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        abort_unless($request->user()?->can('purchases.update'), 403);

        if (! in_array($purchaseOrder->status, ['draft', 'ordered'], true)) {
            return response()->json([
                'message' => 'Purchase order cannot be received in its current status.',
            ], 422);
        }

        DB::transaction(function () use ($purchaseOrder): void {
            $purchaseOrder->loadMissing('lines');

            foreach ($purchaseOrder->lines as $line) {
                $line->update([
                    'received_quantity' => $line->quantity,
                ]);
            }

            $purchaseOrder->update([
                'status' => 'received',
                'received_at' => now(),
            ]);
        });

        return response()->json($purchaseOrder->refresh()->load(['lines', 'supplier:id,code,name', 'branch:id,code,name']));
    }

    public function convertToBill(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        abort_unless($request->user()?->can('purchases.update'), 403);

        if (! in_array($purchaseOrder->status, ['received', 'billed'], true)) {
            return response()->json([
                'message' => 'Purchase order must be received before converting to bill.',
            ], 422);
        }

        if ($purchaseOrder->invoice_id) {
            $invoice = Invoice::query()->find($purchaseOrder->invoice_id);

            return response()->json([
                'message' => 'Purchase order already has a bill.',
                'invoice' => $invoice,
            ]);
        }

        $invoice = DB::transaction(function () use ($purchaseOrder, $request): Invoice {
            $purchaseOrder->loadMissing('lines');

            $invoice = Invoice::query()->create([
                'branch_id' => $purchaseOrder->branch_id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'created_by' => $request->user()?->id,
                'invoice_number' => $this->generateControlNumber->execute('BILL'),
                'control_number' => $this->generateControlNumber->execute('EINV'),
                'invoice_type' => InvoiceType::PURCHASE->value,
                'invoice_date' => now()->toDateString(),
                'due_date' => $purchaseOrder->due_date,
                'subtotal' => $purchaseOrder->subtotal,
                'vat_amount' => $purchaseOrder->vat_amount,
                'total_amount' => $purchaseOrder->total_amount,
                'status' => InvoiceStatus::DRAFT->value,
                'remarks' => 'Generated from purchase order '.$purchaseOrder->order_number,
            ]);

            $invoiceLines = $purchaseOrder->lines->map(fn ($line): array => [
                'description' => $line->description,
                'quantity' => $line->received_quantity > 0 ? $line->received_quantity : $line->quantity,
                'unit_price' => $line->unit_price,
                'line_total' => ($line->received_quantity > 0 ? $line->received_quantity : $line->quantity) * $line->unit_price,
            ])->all();

            $invoice->lines()->createMany($invoiceLines);

            $purchaseOrder->update([
                'invoice_id' => $invoice->id,
                'status' => 'billed',
                'billed_at' => now(),
            ]);

            return $invoice;
        });

        return response()->json([
            'message' => 'Purchase order converted to draft supplier bill.',
            'invoice' => $invoice,
        ], 201);
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        abort_unless($request->user()?->can('purchases.delete'), 403);

        if ($purchaseOrder->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft purchase orders can be deleted.',
            ], 422);
        }

        $purchaseOrder->delete();

        return response()->json(status: 204);
    }
}

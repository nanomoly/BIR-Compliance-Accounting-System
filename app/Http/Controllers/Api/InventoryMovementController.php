<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreInventoryMovementRequest;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('inventory.view'), 403);

        $query = InventoryMovement::query()
            ->with('inventoryItem:id,sku,name,unit')
            ->latest('movement_date')
            ->latest('id');

        if ($request->filled('inventory_item_id')) {
            $query->where('inventory_item_id', (int) $request->integer('inventory_item_id'));
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreInventoryMovementRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $result = DB::transaction(function () use ($payload, $request): InventoryMovement {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail((int) $payload['inventory_item_id']);
            $quantity = (float) $payload['quantity'];
            $isOut = in_array($payload['movement_type'], ['out', 'adjustment_out'], true);
            $delta = $isOut ? -$quantity : $quantity;

            if ($isOut && (float) $item->quantity_on_hand < $quantity) {
                abort(422, 'Insufficient quantity on hand for this movement.');
            }

            $movement = InventoryMovement::query()->create([
                ...$payload,
                'created_by' => $request->user()?->id,
            ]);

            $item->increment('quantity_on_hand', $delta);

            return $movement;
        });

        return response()->json($result->load('inventoryItem:id,sku,name,unit'), 201);
    }
}

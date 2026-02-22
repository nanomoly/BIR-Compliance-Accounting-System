<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreInventoryItemRequest;
use App\Http\Requests\Api\UpdateInventoryItemRequest;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('inventory.view'), 403);

        $items = InventoryItem::query()
            ->with('branch:id,name,code')
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json($items);
    }

    public function store(StoreInventoryItemRequest $request): JsonResponse
    {
        $item = InventoryItem::query()->create($request->validated());

        return response()->json($item->load('branch:id,name,code'), 201);
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventoryItem): JsonResponse
    {
        $inventoryItem->update($request->validated());

        return response()->json($inventoryItem->refresh()->load('branch:id,name,code'));
    }

    public function destroy(Request $request, InventoryItem $inventoryItem): JsonResponse
    {
        abort_unless($request->user()?->can('inventory.delete'), 403);

        $inventoryItem->delete();

        return response()->json(status: 204);
    }
}

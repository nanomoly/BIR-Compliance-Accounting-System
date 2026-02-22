<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSupplierRequest;
use App\Http\Requests\Api\UpdateSupplierRequest;
use App\Models\Branch;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('suppliers.view'), 403);

        $suppliers = Supplier::query()
            ->with('branch')
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 50));

        return response()->json($suppliers);
    }

    public function catalog(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('suppliers.view'), 403);

        return response()->json([
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name', 'code'])->toArray(),
        ]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $supplier = Supplier::withTrashed()->firstOrNew([
            'code' => $payload['code'],
        ]);

        $supplier->fill($payload);
        $supplier->deleted_at = null;
        $supplier->save();

        return response()->json($supplier->load('branch'), 201);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return response()->json($supplier->refresh()->load('branch'));
    }
}

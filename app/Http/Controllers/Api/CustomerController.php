<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCustomerRequest;
use App\Http\Requests\Api\UpdateCustomerRequest;
use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('customers.view'), 403);

        $customers = Customer::query()
            ->with('branch')
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 50));

        return response()->json($customers);
    }

    public function catalog(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('customers.view'), 403);

        return response()->json([
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name', 'code'])->toArray(),
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $customer = Customer::withTrashed()->firstOrNew([
            'code' => $payload['code'],
        ]);

        $customer->fill($payload);
        $customer->deleted_at = null;
        $customer->save();

        return response()->json($customer->load('branch'), 201);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        return response()->json($customer->refresh()->load('branch'));
    }
}

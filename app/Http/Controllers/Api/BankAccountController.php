<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBankAccountRequest;
use App\Http\Requests\Api\UpdateBankAccountRequest;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('banking.view'), 403);

        $accounts = BankAccount::query()
            ->with('branch:id,name,code')
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json($accounts);
    }

    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        $account = BankAccount::query()->create($request->validated());

        return response()->json($account->load('branch:id,name,code'), 201);
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): JsonResponse
    {
        $bankAccount->update($request->validated());

        return response()->json($bankAccount->refresh()->load('branch:id,name,code'));
    }

    public function destroy(Request $request, BankAccount $bankAccount): JsonResponse
    {
        abort_unless($request->user()?->can('banking.delete'), 403);

        if ($bankAccount->transactions()->exists()) {
            return response()->json([
                'message' => 'Bank account with transactions cannot be deleted.',
            ], 422);
        }

        $bankAccount->delete();

        return response()->json(status: 204);
    }
}

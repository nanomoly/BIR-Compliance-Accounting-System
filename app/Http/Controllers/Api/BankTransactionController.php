<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBankTransactionRequest;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('banking.view'), 403);

        $query = BankTransaction::query()
            ->with('bankAccount:id,bank_name,account_number')
            ->latest('transaction_date')
            ->latest('id');

        if ($request->filled('bank_account_id')) {
            $query->where('bank_account_id', (int) $request->integer('bank_account_id'));
        }

        return response()->json($query->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreBankTransactionRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $transaction = BankTransaction::query()->create([
            ...$payload,
            'created_by' => $request->user()?->id,
        ]);

        $account = BankAccount::query()->findOrFail($payload['bank_account_id']);
        $delta = (float) $payload['amount'] * ($payload['transaction_type'] === 'credit' ? 1 : -1);
        $account->increment('current_balance', $delta);

        return response()->json($transaction->load('bankAccount:id,bank_name,account_number'), 201);
    }

    public function destroy(Request $request, BankTransaction $bankTransaction): JsonResponse
    {
        abort_unless($request->user()?->can('banking.delete'), 403);

        $delta = (float) $bankTransaction->amount * ($bankTransaction->transaction_type === 'credit' ? -1 : 1);
        $bankTransaction->bankAccount()->increment('current_balance', $delta);
        $bankTransaction->delete();

        return response()->json(status: 204);
    }
}

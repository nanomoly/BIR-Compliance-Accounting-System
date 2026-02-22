<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAccountRequest;
use App\Http\Requests\Api\UpdateAccountRequest;
use App\Models\Account;
use App\Services\Accounting\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accountService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Account::class);

        return response()->json($this->accountService->list((int) $request->integer('per_page', 15)));
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        return response()->json($this->accountService->create($request->validated()), 201);
    }

    public function show(Account $account): JsonResponse
    {
        $this->authorize('view', $account);

        return response()->json($account->load(['parent', 'children']));
    }

    public function update(UpdateAccountRequest $request, Account $account): JsonResponse
    {
        return response()->json($this->accountService->update($account, $request->validated()));
    }

    public function destroy(Account $account): JsonResponse
    {
        $this->authorize('delete', $account);
        $this->accountService->delete($account);

        return response()->json(status: 204);
    }
}

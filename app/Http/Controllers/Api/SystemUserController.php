<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSystemUserRequest;
use App\Models\Branch;
use App\Services\Accounting\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class SystemUserController extends Controller
{
    public function __construct(private readonly UserManagementService $userManagementService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('users.view'), 403);

        return response()->json($this->userManagementService->list((int) $request->integer('per_page', 20)));
    }

    public function store(StoreSystemUserRequest $request): JsonResponse
    {
        return response()->json($this->userManagementService->create($request->validated()), 201);
    }

    public function catalog(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('users.view'), 403);

        return response()->json([
            'roles' => Role::query()->orderBy('name')->pluck('name')->values()->all(),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name', 'code'])->toArray(),
        ]);
    }
}

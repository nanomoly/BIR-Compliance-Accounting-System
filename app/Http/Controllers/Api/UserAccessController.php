<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssignUserAccessRequest;
use App\Models\User;
use App\Services\Accounting\AccessControlService;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserAccessController extends Controller
{
    public function __construct(private readonly AccessControlService $accessControlService) {}

    public function modules(): JsonResponse
    {
        abort_unless(auth()->user()?->can('user_access.view'), 403);

        return response()->json([
            'modules' => $this->accessControlService->modules(),
        ]);
    }

    public function catalog(): JsonResponse
    {
        abort_unless(auth()->user()?->can('user_access.view'), 403);

        $users = User::query()
            ->withCount(['roles', 'permissions'])
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'has_access' => $user->roles_count > 0 || $user->permissions_count > 0,
                'is_default_admin' => $this->accessControlService->isDefaultCasAdmin($user),
            ])
            ->values()
            ->all();

        return response()->json([
            'modules' => $this->accessControlService->modules(),
            'roles' => Role::query()->orderBy('name')->pluck('name')->values()->all(),
            'permissions' => Permission::query()->orderBy('name')->pluck('name')->values()->all(),
            'users' => $users,
        ]);
    }

    public function show(User $user): JsonResponse
    {
        abort_unless(auth()->user()?->can('user_access.view'), 403);

        return response()->json([
            'user_id' => $user->id,
            'email' => $user->email,
            'access' => $this->accessControlService->getUserAccess($user),
        ]);
    }

    public function assign(AssignUserAccessRequest $request, User $user): JsonResponse
    {
        $access = $this->accessControlService->assign(
            $user,
            $request->validated('roles', []),
            $request->validated('permissions', []),
        );

        return response()->json([
            'message' => 'User module/function access updated.',
            'user_id' => $user->id,
            'access' => $access,
        ]);
    }
}

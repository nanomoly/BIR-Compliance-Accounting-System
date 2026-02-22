<?php

namespace App\Services\Accounting;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccessControlService
{
    /**
     * @return array<string, array<int, string>>
     */
    public function modules(): array
    {
        /** @var array<string, array<int, string>> $modules */
        $modules = config('cas_permissions.modules', []);

        return $modules;
    }

    /**
     * @param  array<int, string>  $roles
     * @param  array<int, string>  $permissions
     * @return array<string, array<int, string>>
     */
    public function assign(User $user, array $roles = [], array $permissions = []): array
    {
        $existingRoles = Role::query()->whereIn('name', $roles)->pluck('name')->all();
        $user->syncRoles($existingRoles);

        $existingPermissions = Permission::query()->whereIn('name', $permissions)->pluck('name')->all();
        $user->syncPermissions($existingPermissions);

        return $this->getUserAccess($user->refresh());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getUserAccess(User $user): array
    {
        return [
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getPermissionNames()->values()->all(),
        ];
    }
}

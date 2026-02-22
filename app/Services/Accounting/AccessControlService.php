<?php

namespace App\Services\Accounting;

use App\Enums\UserRole;
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

        if ($this->isDefaultCasAdmin($user)) {
            $existingRoles = [UserRole::ADMIN->value];

            if ($user->role !== UserRole::ADMIN) {
                $user->forceFill(['role' => UserRole::ADMIN])->save();
            }
        }

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
        $directPermissions = $user->getPermissionNames()->values()->all();
        $rolePermissions = $user->getPermissionsViaRoles()->pluck('name')->values()->all();

        return [
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $directPermissions,
            'role_permissions' => $rolePermissions,
            'effective_permissions' => array_values(array_unique([
                ...$directPermissions,
                ...$rolePermissions,
            ])),
        ];
    }

    public function isDefaultCasAdmin(User $user): bool
    {
        return mb_strtolower($user->email) === 'admin@cas.local';
    }
}

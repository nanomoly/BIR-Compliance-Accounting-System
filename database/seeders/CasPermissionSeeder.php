<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CasPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modules = config('cas_permissions.modules', []);

        $allPermissions = [];
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $permissionName = $module.'.'.$action;
                Permission::query()->firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
                $allPermissions[] = $permissionName;
            }
        }

        $adminRole = Role::query()->firstOrCreate([
            'name' => UserRole::ADMIN->value,
            'guard_name' => 'web',
        ]);
        $adminRole->syncPermissions($allPermissions);

        $accountantRole = Role::query()->firstOrCreate([
            'name' => UserRole::ACCOUNTANT->value,
            'guard_name' => 'web',
        ]);
        $accountantRole->syncPermissions([
            'accounts.view',
            'accounts.create',
            'accounts.update',
            'customers.view',
            'customers.create',
            'customers.update',
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'sales.view',
            'sales.create',
            'sales.update',
            'collections.view',
            'collections.create',
            'purchases.view',
            'purchases.create',
            'purchases.update',
            'payroll.view',
            'payroll.create',
            'payroll.update',
            'journals.view',
            'journals.create',
            'journals.post',
            'journals.reverse',
            'reports.view',
            'reports.export',
            'ledgers.view',
            'e_invoices.view',
            'e_invoices.create',
            'e_invoices.issue',
            'e_invoices.cancel',
            'e_invoices.transmit',
            'users.view',
            'audit_trail.view',
            'backups.view',
            'system_info.view',
            'system_info.update',
        ]);

        $auditorRole = Role::query()->firstOrCreate([
            'name' => UserRole::AUDITOR->value,
            'guard_name' => 'web',
        ]);
        $auditorRole->syncPermissions([
            'accounts.view',
            'customers.view',
            'suppliers.view',
            'sales.view',
            'collections.view',
            'purchases.view',
            'payroll.view',
            'journals.view',
            'reports.view',
            'reports.export',
            'ledgers.view',
            'backups.view',
            'system_info.view',
            'e_invoices.view',
            'audit_trail.view',
        ]);
    }
}

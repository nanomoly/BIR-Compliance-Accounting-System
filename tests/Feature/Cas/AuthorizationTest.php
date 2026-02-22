<?php

use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use App\Models\User;
use Spatie\Permission\Models\Permission;

it('blocks customers page when customers.view is missing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/cas/customers')->assertForbidden();
});

it('allows customers page when customers.view is granted', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('customers.view', 'web');
    $user->givePermissionTo($permission);

    $this->actingAs($user);

    $this->get('/cas/customers')->assertOk();
});

it('blocks customers api when customers.view is missing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->getJson('/api/customers')->assertForbidden();
});

it('allows customers api when customers.view is granted', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('customers.view', 'web');
    $user->givePermissionTo($permission);

    $this->actingAs($user);

    $this->getJson('/api/customers')->assertOk()->assertJsonStructure([
        'data',
    ]);
});

it('requires system_info.update to edit company profile', function () {
    $user = User::factory()->create();
    $viewPermission = Permission::findOrCreate('system_info.view', 'web');
    $user->givePermissionTo($viewPermission);

    $this->actingAs($user);

    $payload = [
        'name' => 'Test Company',
        'tin' => '123-456-789-000',
        'registered_address' => 'Makati City',
    ];

    $this->putJson('/api/system-info/company-profile', $payload)->assertForbidden();

    $updatePermission = Permission::findOrCreate('system_info.update', 'web');
    $user->givePermissionTo($updatePermission);

    $this->putJson('/api/system-info/company-profile', $payload)
        ->assertOk()
        ->assertJsonPath('company.name', 'Test Company');
});

it('blocks suppliers page and api when suppliers.view is missing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/cas/suppliers')->assertForbidden();
    $this->getJson('/api/suppliers')->assertForbidden();
});

it('allows suppliers page and api when suppliers.view is granted', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('suppliers.view', 'web');
    $user->givePermissionTo($permission);

    $this->actingAs($user);

    $this->get('/cas/suppliers')->assertOk();
    $this->getJson('/api/suppliers')->assertOk()->assertJsonStructure(['data']);
});

it('blocks reports page and api when reports.view is missing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/cas/reports')->assertForbidden();
    $this->getJson('/api/reports/trial-balance?from_date=2026-01-01&to_date=2026-01-31')->assertForbidden();
});

it('allows reports api json when reports.view is granted', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('reports.view', 'web');
    $user->givePermissionTo($permission);

    $this->actingAs($user);

    $this->get('/cas/reports')->assertOk();
    $this->getJson('/api/reports/trial-balance?from_date=2026-01-01&to_date=2026-01-31')
        ->assertOk();
});

it('requires reports.export for non-json report formats', function () {
    $user = User::factory()->create();
    $viewPermission = Permission::findOrCreate('reports.view', 'web');
    $user->givePermissionTo($viewPermission);

    $this->actingAs($user);

    $this->get('/api/reports/trial-balance?from_date=2026-01-01&to_date=2026-01-31&format=excel')
        ->assertForbidden();
});

it('blocks user access page and api when user_access.view is missing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/cas/user-access')->assertForbidden();
    $this->getJson('/api/access/catalog')->assertForbidden();
});

it('allows user access page and api when user_access.view is granted', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('user_access.view', 'web');
    $user->givePermissionTo($permission);

    $this->actingAs($user);

    $this->get('/cas/user-access')->assertOk();
    $this->getJson('/api/access/catalog')->assertOk()->assertJsonStructure(['users', 'roles', 'permissions']);
});

it('blocks audit trail page and api when audit_trail.view is missing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/cas/audit-trail')->assertForbidden();
    $this->getJson('/api/audit-logs')->assertForbidden();
});

it('allows audit trail page and api when audit_trail.view is granted', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('audit_trail.view', 'web');
    $user->givePermissionTo($permission);

    $this->actingAs($user);

    $this->get('/cas/audit-trail')->assertOk();
    $this->getJson('/api/audit-logs')->assertOk()->assertJsonStructure(['data']);
});

it('blocks backups page and api when backups.view is missing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/cas/backups')->assertForbidden();
    $this->getJson('/api/backups')->assertForbidden();
});

it('allows backups page and api when backups.view is granted', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('backups.view', 'web');
    $user->givePermissionTo($permission);

    $this->actingAs($user);

    $this->get('/cas/backups')->assertOk();
    $this->getJson('/api/backups')->assertOk()->assertJsonStructure(['data']);
});

it('blocks system users page and api when users.view is missing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/cas/users')->assertForbidden();
    $this->getJson('/api/system-users')->assertForbidden();
});

it('allows system users page and api when users.view is granted', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('users.view', 'web');
    $user->givePermissionTo($permission);

    $this->actingAs($user);

    $this->get('/cas/users')->assertOk();
    $this->getJson('/api/system-users')->assertOk()->assertJsonStructure(['data']);
});

it('blocks e-invoice print when e_invoices.view is missing', function () {
    $user = User::factory()->create();

    $company = CompanyProfile::query()->create([
        'name' => 'Print Auth Company',
        'tin' => '777-777-777-000',
        'registered_address' => 'Makati City',
        'software_version' => '1.0.0',
        'database_version' => 'SQLite',
        'developer_name' => 'Standard CAS Team',
        'developer_tin' => '111-111-111-000',
    ]);

    $branch = Branch::query()->create([
        'company_profile_id' => $company->id,
        'code' => 'PRNT-01',
        'name' => 'Print Branch',
        'tin' => '777-777-777-001',
        'address' => 'Makati City',
        'is_main' => true,
    ]);

    $invoice = Invoice::query()->create([
        'branch_id' => $branch->id,
        'customer_id' => null,
        'supplier_id' => null,
        'journal_entry_id' => null,
        'created_by' => $user->id,
        'invoice_number' => 'INV-PRINT-001',
        'control_number' => 'EINV-PRINT-001',
        'invoice_type' => 'sales',
        'invoice_date' => now()->toDateString(),
        'due_date' => null,
        'subtotal' => 0,
        'vat_amount' => 0,
        'total_amount' => 0,
        'status' => 'draft',
        'issued_at' => null,
        'locked_at' => null,
        'remarks' => null,
    ]);

    $this->actingAs($user);

    $this->get("/api/e-invoices/{$invoice->id}/print")->assertForbidden();
});

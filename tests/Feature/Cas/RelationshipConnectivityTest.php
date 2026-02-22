<?php

use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Permission;

function createConnectivityBranch(): Branch
{
    $company = CompanyProfile::query()->create([
        'name' => 'Connectivity Test Company',
        'tin' => '888-888-888-000',
        'registered_address' => 'Taguig City',
        'software_version' => '1.0.0',
        'database_version' => 'SQLite',
        'developer_name' => 'Standard CAS Team',
        'developer_tin' => '111-111-111-000',
    ]);

    return Branch::query()->create([
        'company_profile_id' => $company->id,
        'code' => 'REL-01',
        'name' => 'Relationship Branch',
        'tin' => '888-888-888-001',
        'address' => 'Taguig City',
        'is_main' => true,
    ]);
}

function grantUserPermissions(User $user, array $permissions): void
{
    foreach ($permissions as $permissionName) {
        $permission = Permission::findOrCreate($permissionName, 'web');
        $user->givePermissionTo($permission);
    }
}

it('keeps customer and supplier connected to branch relationship through module apis', function () {
    $user = User::factory()->create();
    grantUserPermissions($user, [
        'customers.view',
        'customers.create',
        'suppliers.view',
        'suppliers.create',
    ]);

    $branch = createConnectivityBranch();

    $this->actingAs($user);

    $customerResponse = $this->postJson('/api/customers', [
        'branch_id' => $branch->id,
        'code' => 'REL-CUST-001',
        'name' => 'Rel Customer',
        'tin' => '888-000-000-000',
        'address' => 'Makati',
        'email' => 'customer@rel.test',
        'phone' => '09171234567',
    ])->assertCreated();

    $customerResponse->assertJsonPath('branch.id', $branch->id);
    $customerResponse->assertJsonPath('branch.name', $branch->name);

    $supplierResponse = $this->postJson('/api/suppliers', [
        'branch_id' => $branch->id,
        'code' => 'REL-SUP-001',
        'name' => 'Rel Supplier',
        'tin' => '888-111-111-111',
        'address' => 'Pasig',
        'email' => 'supplier@rel.test',
        'phone' => '09179876543',
    ])->assertCreated();

    $supplierResponse->assertJsonPath('branch.id', $branch->id);
    $supplierResponse->assertJsonPath('branch.name', $branch->name);

    $this->getJson('/api/customers?per_page=10')
        ->assertOk()
        ->assertJsonPath('data.0.branch.id', $branch->id);

    $this->getJson('/api/suppliers?per_page=10')
        ->assertOk()
        ->assertJsonPath('data.0.branch.id', $branch->id);
});

it('keeps e-invoice relationships connected to customer lines and transmissions', function () {
    $user = User::factory()->create();
    grantUserPermissions($user, [
        'customers.create',
        'customers.view',
        'e_invoices.create',
        'e_invoices.view',
        'e_invoices.issue',
        'e_invoices.transmit',
    ]);

    $branch = createConnectivityBranch();

    $this->actingAs($user);

    $customerId = (int) $this->postJson('/api/customers', [
        'branch_id' => $branch->id,
        'code' => 'REL-CUST-002',
        'name' => 'Invoice Customer',
        'tin' => null,
        'address' => null,
        'email' => null,
        'phone' => null,
    ])->assertCreated()->json('id');

    $invoiceResponse = $this->postJson('/api/e-invoices', [
        'branch_id' => $branch->id,
        'customer_id' => $customerId,
        'invoice_type' => 'sales',
        'invoice_date' => CarbonImmutable::today()->toDateString(),
        'due_date' => CarbonImmutable::today()->toDateString(),
        'vat_amount' => 120,
        'remarks' => 'relationship-check',
        'lines' => [
            [
                'description' => 'Relationship line item',
                'quantity' => 2,
                'unit_price' => 500,
            ],
        ],
    ])->assertCreated();

    $invoiceId = (int) $invoiceResponse->json('id');

    $invoiceResponse->assertJsonPath('customer.id', $customerId);
    $invoiceResponse->assertJsonPath('lines.0.description', 'Relationship line item');

    $this->postJson("/api/e-invoices/{$invoiceId}/issue")
        ->assertOk()
        ->assertJsonPath('status', 'issued');

    $this->postJson("/api/e-invoices/{$invoiceId}/transmit")
        ->assertCreated()
        ->assertJsonStructure([
            'id',
            'invoice_id',
            'reference_number',
            'request_payload' => [
                'document_type',
                'invoice_number',
                'control_number',
                'seller',
                'buyer',
                'line_items',
                'totals',
            ],
        ]);

    $this->getJson('/api/e-invoices?per_page=10')
        ->assertOk()
        ->assertJsonPath('data.0.customer.id', $customerId)
        ->assertJsonStructure([
            'data' => [
                [
                    'lines',
                    'transmissions',
                ],
            ],
        ]);
});

it('creates purchase ap invoice with supplier linkage', function () {
    $user = User::factory()->create();
    grantUserPermissions($user, [
        'suppliers.create',
        'suppliers.view',
        'e_invoices.create',
        'e_invoices.view',
    ]);

    $branch = createConnectivityBranch();

    $this->actingAs($user);

    $supplierId = (int) $this->postJson('/api/suppliers', [
        'branch_id' => $branch->id,
        'code' => 'REL-SUP-002',
        'name' => 'Invoice Supplier',
        'tin' => null,
        'address' => null,
        'email' => null,
        'phone' => null,
    ])->assertCreated()->json('id');

    $invoiceResponse = $this->postJson('/api/e-invoices', [
        'branch_id' => $branch->id,
        'supplier_id' => $supplierId,
        'invoice_type' => 'purchase',
        'invoice_date' => CarbonImmutable::today()->toDateString(),
        'due_date' => CarbonImmutable::today()->toDateString(),
        'vat_amount' => 0,
        'remarks' => 'purchase-ap-check',
        'lines' => [
            [
                'description' => 'AP line item',
                'quantity' => 1,
                'unit_price' => 900,
            ],
        ],
    ])->assertCreated();

    $invoiceResponse->assertJsonPath('supplier.id', $supplierId);
    $invoiceResponse->assertJsonPath('invoice_type', 'purchase');

    $this->postJson('/api/e-invoices', [
        'branch_id' => $branch->id,
        'invoice_type' => 'purchase',
        'invoice_date' => CarbonImmutable::today()->toDateString(),
        'lines' => [
            [
                'description' => 'Missing supplier should fail',
                'quantity' => 1,
                'unit_price' => 100,
            ],
        ],
    ])->assertStatus(422);
});

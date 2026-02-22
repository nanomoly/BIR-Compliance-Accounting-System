<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Permission;

it('blocks posting a journal entry by the same user who created it', function () {
    $maker = User::factory()->create();

    foreach (['journals.create', 'journals.post', 'journals.view'] as $permissionName) {
        $permission = Permission::findOrCreate($permissionName, 'web');
        $maker->givePermissionTo($permission);
    }

    $company = CompanyProfile::query()->create([
        'name' => 'Maker Checker Co',
        'tin' => '333-333-333-000',
        'registered_address' => 'Makati City',
        'software_version' => '1.0.0',
        'database_version' => 'SQLite',
        'developer_name' => 'Standard CAS Team',
        'developer_tin' => '111-111-111-000',
    ]);

    $branch = Branch::query()->create([
        'company_profile_id' => $company->id,
        'code' => 'MKR-01',
        'name' => 'Maker Branch',
        'tin' => '333-333-333-001',
        'address' => 'Makati City',
        'is_main' => true,
    ]);

    $cashAccount = Account::query()->create([
        'branch_id' => $branch->id,
        'code' => '1-7100',
        'name' => 'Cash - Maker Check',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_active' => true,
        'is_control_account' => false,
    ]);

    $salesAccount = Account::query()->create([
        'branch_id' => $branch->id,
        'code' => '4-7100',
        'name' => 'Sales - Maker Check',
        'type' => 'revenue',
        'normal_balance' => 'credit',
        'is_active' => true,
        'is_control_account' => false,
    ]);

    $this->actingAs($maker);

    $entryDate = CarbonImmutable::today()->toDateString();

    $journalResponse = $this->postJson('/api/journal-entries', [
        'branch_id' => $branch->id,
        'journal_type' => 'sales',
        'entry_date' => $entryDate,
        'description' => 'Maker checker journal',
        'reference_no' => 'MC-JE-001',
        'lines' => [
            [
                'account_id' => $cashAccount->id,
                'debit' => 1000,
                'credit' => 0,
            ],
            [
                'account_id' => $salesAccount->id,
                'debit' => 0,
                'credit' => 1000,
            ],
        ],
    ])->assertCreated();

    $journalId = (int) $journalResponse->json('id');

    $this->postJson("/api/journal-entries/{$journalId}/post")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Maker-checker violation: you cannot post your own journal entry.');

    $journal = JournalEntry::query()->findOrFail($journalId);
    expect($journal->status->value)->toBe('draft');
    expect($journal->approved_by)->toBeNull();
});

it('blocks approving a payroll run by the same user who created it', function () {
    $maker = User::factory()->create();

    foreach (['payroll.create', 'payroll.update', 'payroll.view'] as $permissionName) {
        $permission = Permission::findOrCreate($permissionName, 'web');
        $maker->givePermissionTo($permission);
    }

    $company = CompanyProfile::query()->create([
        'name' => 'Payroll Maker Checker Co',
        'tin' => '444-444-444-000',
        'registered_address' => 'Taguig City',
        'software_version' => '1.0.0',
        'database_version' => 'SQLite',
        'developer_name' => 'Standard CAS Team',
        'developer_tin' => '111-111-111-000',
    ]);

    $branch = Branch::query()->create([
        'company_profile_id' => $company->id,
        'code' => 'PAY-01',
        'name' => 'Payroll Branch',
        'tin' => '444-444-444-001',
        'address' => 'Taguig City',
        'is_main' => true,
    ]);

    Employee::query()->create([
        'branch_id' => $branch->id,
        'employee_no' => 'EMP-MC-001',
        'first_name' => 'Maker',
        'last_name' => 'Employee',
        'position' => 'Staff',
        'department' => 'Finance',
        'hire_date' => CarbonImmutable::today()->subMonth()->toDateString(),
        'monthly_rate' => 30000,
        'is_active' => true,
    ]);

    $this->actingAs($maker);

    $periodResponse = $this->postJson('/api/payroll-periods', [
        'name' => 'Feb 2026 - 1st Half',
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-15',
        'pay_date' => '2026-02-16',
    ])->assertCreated();

    $periodId = (int) $periodResponse->json('id');

    $runResponse = $this->postJson('/api/payroll-runs/generate', [
        'payroll_period_id' => $periodId,
        'sss_employee_rate' => 0.045,
        'sss_employee_cap' => 1125,
        'philhealth_rate' => 0.05,
        'philhealth_employee_cap' => 2500,
        'pagibig_employee_rate' => 0.02,
        'pagibig_employee_cap' => 100,
        'withholding_tax_rate' => 0,
    ])->assertCreated();

    $runId = (int) $runResponse->json('id');

    $this->postJson("/api/payroll-runs/{$runId}/approve")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Maker-checker violation: you cannot approve your own payroll run.');

    $run = PayrollRun::query()->findOrFail($runId);
    expect($run->status)->toBe('draft');
    expect($run->approved_by)->toBeNull();
    expect($run->approved_at)->toBeNull();
});

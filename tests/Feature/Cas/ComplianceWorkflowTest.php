<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\JournalEntry;
use App\Models\ReportRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Permission;

function createCasBranch(): Branch
{
    $company = CompanyProfile::query()->create([
        'name' => 'Smoke Test Company',
        'tin' => '999-999-999-000',
        'registered_address' => 'Makati City',
        'software_version' => '1.0.0',
        'database_version' => 'SQLite',
        'developer_name' => 'Standard CAS Team',
        'developer_tin' => '111-111-111-000',
    ]);

    return Branch::query()->create([
        'company_profile_id' => $company->id,
        'code' => 'MAIN',
        'name' => 'Main Branch',
        'tin' => '999-999-999-001',
        'address' => 'Makati City',
        'is_main' => true,
    ]);
}

function grantPermissions(User $user, array $permissions): void
{
    foreach ($permissions as $permissionName) {
        $permission = Permission::findOrCreate($permissionName, 'web');
        $user->givePermissionTo($permission);
    }
}

it('runs a core CAS bookkeeping flow end-to-end for an authorized user', function () {
    $user = User::factory()->create();
    grantPermissions($user, [
        'accounts.create',
        'accounts.view',
        'journals.create',
        'journals.post',
        'journals.view',
        'reports.view',
    ]);

    $branch = createCasBranch();

    $this->actingAs($user);

    $cashAccountResponse = $this->postJson('/api/accounts', [
        'branch_id' => $branch->id,
        'code' => '1-9000',
        'name' => 'Cash - Smoke Test',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_active' => true,
    ])->assertCreated();

    $salesAccountResponse = $this->postJson('/api/accounts', [
        'branch_id' => $branch->id,
        'code' => '4-9000',
        'name' => 'Sales - Smoke Test',
        'type' => 'revenue',
        'normal_balance' => 'credit',
        'is_active' => true,
    ])->assertCreated();

    $cashAccountId = (int) $cashAccountResponse->json('id');
    $salesAccountId = (int) $salesAccountResponse->json('id');

    $entryDate = CarbonImmutable::today()->toDateString();

    $journalResponse = $this->postJson('/api/journal-entries', [
        'branch_id' => $branch->id,
        'journal_type' => 'sales',
        'entry_date' => $entryDate,
        'description' => 'Compliance smoke test sale',
        'reference_no' => 'SMOKE-001',
        'lines' => [
            [
                'account_id' => $cashAccountId,
                'debit' => 1500,
                'credit' => 0,
            ],
            [
                'account_id' => $salesAccountId,
                'debit' => 0,
                'credit' => 1500,
            ],
        ],
    ])->assertCreated();

    $journalId = (int) $journalResponse->json('id');

    $this->postJson("/api/journal-entries/{$journalId}/post")
        ->assertOk()
        ->assertJsonPath('status', 'posted');

    $this->getJson("/api/reports/trial-balance?from_date={$entryDate}&to_date={$entryDate}")
        ->assertOk()
        ->assertJsonStructure([
            'reference_number',
            'page_count',
            'rows',
        ]);

    $postedEntry = JournalEntry::query()->findOrFail($journalId);
    expect($postedEntry->status->value)->toBe('posted');
    expect(ReportRun::query()->where('report_type', 'trial_balance')->exists())->toBeTrue();
});

it('prevents posting journal entries when user lacks journals.post permission', function () {
    $user = User::factory()->create();
    grantPermissions($user, [
        'journals.create',
        'journals.view',
    ]);

    $branch = createCasBranch();

    $cashAccount = Account::query()->create([
        'branch_id' => $branch->id,
        'code' => '1-8000',
        'name' => 'Cash - Restricted Test',
        'type' => 'asset',
        'normal_balance' => 'debit',
        'is_active' => true,
        'is_control_account' => false,
    ]);

    $salesAccount = Account::query()->create([
        'branch_id' => $branch->id,
        'code' => '4-8000',
        'name' => 'Sales - Restricted Test',
        'type' => 'revenue',
        'normal_balance' => 'credit',
        'is_active' => true,
        'is_control_account' => false,
    ]);

    $this->actingAs($user);

    $entryDate = CarbonImmutable::today()->toDateString();

    $journalResponse = $this->postJson('/api/journal-entries', [
        'branch_id' => $branch->id,
        'journal_type' => 'sales',
        'entry_date' => $entryDate,
        'description' => 'Restricted post attempt',
        'reference_no' => 'SMOKE-002',
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
        ->assertForbidden();

    $draftEntry = JournalEntry::query()->findOrFail($journalId);
    expect($draftEntry->status->value)->toBe('draft');
});

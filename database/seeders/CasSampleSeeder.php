<?php

namespace Database\Seeders;

use App\DTOs\JournalEntryLineData;
use App\DTOs\StoreJournalEntryData;
use App\Enums\JournalType;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\JournalEntryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CasSampleSeeder extends Seeder
{
    public function run(): void
    {
        $company = CompanyProfile::query()->firstOrCreate([
            'tin' => '000-000-000-000',
        ], [
            'name' => 'Standard CAS Demo Corp',
            'registered_address' => 'Makati City, Philippines',
            'software_version' => '1.0.0',
            'database_version' => 'MySQL 8+',
            'developer_name' => 'Standard CAS Team',
            'developer_tin' => '111-111-111-000',
        ]);

        $branch = Branch::query()->firstOrCreate([
            'code' => 'MAIN',
        ], [
            'company_profile_id' => $company->id,
            'name' => 'Main Branch',
            'tin' => '000-000-000-001',
            'address' => 'Makati City, Philippines',
            'is_main' => true,
        ]);

        $admin = User::query()->firstOrCreate([
            'email' => 'admin@cas.local',
        ], [
            'name' => 'CAS Admin',
            'password' => Hash::make('Password@123456'),
            'role' => UserRole::ADMIN,
            'branch_id' => $branch->id,
        ]);
        $admin->syncRoles([UserRole::ADMIN->value]);

        $auditor = User::query()->firstOrCreate([
            'email' => 'auditor@cas.local',
        ], [
            'name' => 'CAS Auditor',
            'password' => Hash::make('Password@123456'),
            'role' => UserRole::AUDITOR,
            'branch_id' => $branch->id,
        ]);
        $auditor->syncRoles([UserRole::AUDITOR->value]);

        $cash = Account::withTrashed()->firstOrNew([
            'branch_id' => $branch->id,
            'code' => '1-1000',
        ]);
        $cash->fill([
            'name' => 'Cash on Hand',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
        ]);
        $cash->deleted_at = null;
        $cash->save();

        $sales = Account::withTrashed()->firstOrNew([
            'branch_id' => $branch->id,
            'code' => '4-1000',
        ]);
        $sales->fill([
            'name' => 'Sales Revenue',
            'type' => 'revenue',
            'normal_balance' => 'credit',
            'is_active' => true,
        ]);
        $sales->deleted_at = null;
        $sales->save();

        $purchases = Account::withTrashed()->firstOrNew([
            'branch_id' => $branch->id,
            'code' => '5-1000',
        ]);
        $purchases->fill([
            'name' => 'Purchases Expense',
            'type' => 'expense',
            'normal_balance' => 'debit',
            'is_active' => true,
        ]);
        $purchases->deleted_at = null;
        $purchases->save();

        $accountsPayable = Account::withTrashed()->firstOrNew([
            'branch_id' => $branch->id,
            'code' => '2-1000',
        ]);
        $accountsPayable->fill([
            'name' => 'Accounts Payable',
            'type' => 'liability',
            'normal_balance' => 'credit',
            'is_active' => true,
        ]);
        $accountsPayable->deleted_at = null;
        $accountsPayable->save();

        $customer = Customer::query()->firstOrCreate(['code' => 'CUST-001'], [
            'branch_id' => $branch->id,
            'name' => 'ABC Trading',
            'tin' => '222-222-222-000',
            'address' => 'Pasig City',
        ]);

        $supplier = Supplier::query()->firstOrCreate(['code' => 'SUP-001'], [
            'branch_id' => $branch->id,
            'name' => 'XYZ Supplies',
            'tin' => '333-333-333-000',
            'address' => 'Taguig City',
        ]);

        if (JournalEntry::query()->exists()) {
            return;
        }

        $journalEntryService = app(JournalEntryService::class);
        $entry = $journalEntryService->create(new StoreJournalEntryData(
            branchId: $branch->id,
            journalType: JournalType::SALES,
            entryDate: CarbonImmutable::now(),
            description: 'Sample cash sale',
            referenceNo: 'SI-1001',
            lines: [
                new JournalEntryLineData(accountId: $cash->id, debit: 1000, credit: 0, customerId: $customer->id),
                new JournalEntryLineData(accountId: $sales->id, debit: 0, credit: 1000, customerId: $customer->id),
            ],
        ), $admin->id);

        $journalEntryService->post($entry, $admin->id);

        $purchaseEntry = $journalEntryService->create(new StoreJournalEntryData(
            branchId: $branch->id,
            journalType: JournalType::PURCHASE,
            entryDate: CarbonImmutable::now(),
            description: 'Sample purchase on account',
            referenceNo: 'PI-1001',
            lines: [
                new JournalEntryLineData(accountId: $purchases->id, debit: 700, credit: 0, supplierId: $supplier->id),
                new JournalEntryLineData(accountId: $accountsPayable->id, debit: 0, credit: 700, supplierId: $supplier->id),
            ],
        ), $admin->id);

        $journalEntryService->post($purchaseEntry, $admin->id);
    }
}

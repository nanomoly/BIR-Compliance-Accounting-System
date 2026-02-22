<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\BankTransaction;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\EInvoiceTransmission;
use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Ledger;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ReportRun;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SalesReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CasFullModuleSampleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CasPermissionSeeder::class,
            CasSampleSeeder::class,
        ]);

        $branch = Branch::query()->first();
        if (! $branch) {
            return;
        }

        $admin = User::query()->firstOrCreate([
            'email' => 'admin@cas.local',
        ], [
            'name' => 'CAS Admin',
            'password' => Hash::make('Password@123456'),
            'role' => UserRole::ADMIN,
            'branch_id' => $branch->id,
        ]);
        $admin->syncRoles([UserRole::ADMIN->value]);

        $accountant = User::query()->firstOrCreate([
            'email' => 'accountant@cas.local',
        ], [
            'name' => 'CAS Accountant',
            'password' => Hash::make('Password@123456'),
            'role' => UserRole::ACCOUNTANT,
            'branch_id' => $branch->id,
        ]);
        $accountant->syncRoles([UserRole::ACCOUNTANT->value]);

        $customer = Customer::query()->firstOrCreate([
            'code' => 'CUST-002',
        ], [
            'branch_id' => $branch->id,
            'name' => 'Delta Retail Corp',
            'tin' => '444-444-444-000',
            'address' => 'Quezon City',
            'email' => 'ap@delta-retail.test',
            'phone' => '+63-917-000-0002',
        ]);

        $supplier = Supplier::query()->firstOrCreate([
            'code' => 'SUP-002',
        ], [
            'branch_id' => $branch->id,
            'name' => 'Prime Wholesale Inc',
            'tin' => '555-555-555-000',
            'address' => 'Mandaluyong City',
            'email' => 'billing@prime-wholesale.test',
            'phone' => '+63-917-000-0003',
        ]);

        Account::query()->firstOrCreate([
            'branch_id' => $branch->id,
            'code' => '1-1200',
        ], [
            'name' => 'Accounts Receivable',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_control_account' => true,
        ]);

        Account::query()->firstOrCreate([
            'branch_id' => $branch->id,
            'code' => '1-1300',
        ], [
            'name' => 'Inventory',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_control_account' => false,
        ]);

        Account::query()->firstOrCreate([
            'branch_id' => $branch->id,
            'code' => '5-2000',
        ], [
            'name' => 'Cost of Goods Sold',
            'type' => 'expense',
            'normal_balance' => 'debit',
            'is_active' => true,
            'is_control_account' => false,
        ]);

        $item = InventoryItem::query()->firstOrCreate([
            'sku' => 'ITEM-0001',
        ], [
            'branch_id' => $branch->id,
            'name' => 'Sample Paper Ream',
            'unit' => 'ream',
            'quantity_on_hand' => 100,
            'reorder_level' => 20,
            'is_active' => true,
        ]);

        InventoryMovement::query()->firstOrCreate([
            'inventory_item_id' => $item->id,
            'reference_type' => 'seed',
            'reference_id' => 1,
            'movement_type' => 'in',
        ], [
            'movement_date' => now()->toDateString(),
            'quantity' => 100,
            'unit_cost' => 85,
            'remarks' => 'Initial stock for testing',
            'created_by' => $admin->id,
        ]);

        $employee = Employee::query()->firstOrCreate([
            'employee_no' => 'EMP-0001',
        ], [
            'branch_id' => $branch->id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'position' => 'Accounting Staff',
            'department' => 'Finance',
            'hire_date' => now()->subYear()->toDateString(),
            'monthly_rate' => 25000,
            'is_active' => true,
        ]);

        $period = PayrollPeriod::query()->firstOrCreate([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ], [
            'name' => now()->format('F Y').' Payroll',
            'pay_date' => now()->endOfMonth()->toDateString(),
            'status' => 'open',
        ]);

        $run = PayrollRun::query()->firstOrCreate([
            'run_number' => 'PR-TEST-0001',
        ], [
            'payroll_period_id' => $period->id,
            'status' => 'posted',
            'gross_total' => 25000,
            'deduction_total' => 3250,
            'net_total' => 21750,
            'created_by' => $accountant->id,
            'approved_by' => $admin->id,
            'approved_at' => now()->subDay(),
            'posted_at' => now(),
        ]);

        PayrollRunLine::query()->firstOrCreate([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
        ], [
            'gross_amount' => 25000,
            'deduction_amount' => 3250,
            'net_amount' => 21750,
            'breakdown' => [
                'basic' => 25000,
                'sss' => 1125,
                'philhealth' => 750,
                'pagibig' => 200,
                'withholding_tax' => 1175,
            ],
        ]);

        $salesInvoice = Invoice::query()->firstOrCreate([
            'invoice_number' => 'SI-TEST-0001',
        ], [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by' => $accountant->id,
            'control_number' => 'CN-SI-TEST-0001',
            'invoice_type' => 'sales',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'subtotal' => 10000,
            'vat_amount' => 1200,
            'total_amount' => 11200,
            'status' => 'issued',
            'issued_at' => now(),
            'remarks' => 'Sample sales invoice',
        ]);

        InvoiceLine::query()->firstOrCreate([
            'invoice_id' => $salesInvoice->id,
            'description' => 'Office supply package',
        ], [
            'quantity' => 10,
            'unit_price' => 1000,
            'line_total' => 10000,
        ]);

        EInvoiceTransmission::query()->firstOrCreate([
            'invoice_id' => $salesInvoice->id,
            'reference_number' => 'EIS-TEST-0001',
        ], [
            'provider' => 'bir-eis',
            'status' => 'transmitted',
            'request_payload' => ['invoice' => $salesInvoice->invoice_number],
            'response_payload' => ['result' => 'accepted'],
            'transmitted_at' => now(),
        ]);

        $purchaseInvoice = Invoice::query()->firstOrCreate([
            'invoice_number' => 'PI-TEST-0001',
        ], [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'created_by' => $accountant->id,
            'control_number' => 'CN-PI-TEST-0001',
            'invoice_type' => 'purchase',
            'invoice_date' => now()->subDay()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 8000,
            'vat_amount' => 960,
            'total_amount' => 8960,
            'status' => 'issued',
            'issued_at' => now()->subDay(),
            'remarks' => 'Sample purchase invoice',
        ]);

        InvoiceLine::query()->firstOrCreate([
            'invoice_id' => $purchaseInvoice->id,
            'description' => 'Office supplies bulk purchase',
        ], [
            'quantity' => 20,
            'unit_price' => 400,
            'line_total' => 8000,
        ]);

        $dashboardInvoices = [
            [
                'invoice_number' => 'SI-DB-0001',
                'control_number' => 'CN-SI-DB-0001',
                'invoice_type' => 'sales',
                'invoice_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(10)->toDateString(),
                'subtotal' => 15000,
                'vat_amount' => 1800,
                'total_amount' => 16800,
                'customer_id' => $customer->id,
                'supplier_id' => null,
                'line_description' => 'Current month sales - open receivable',
                'receipt' => [
                    'receipt_number' => 'OR-DB-0001',
                    'amount' => 6000,
                    'receipt_date' => now()->subDays(2)->toDateString(),
                    'reference_no' => 'RCPT-DB-0001',
                ],
            ],
            [
                'invoice_number' => 'SI-DB-0002',
                'control_number' => 'CN-SI-DB-0002',
                'invoice_type' => 'sales',
                'invoice_date' => now()->subMonths(2)->startOfMonth()->addDays(4)->toDateString(),
                'due_date' => now()->subDays(20)->toDateString(),
                'subtotal' => 12000,
                'vat_amount' => 1440,
                'total_amount' => 13440,
                'customer_id' => $customer->id,
                'supplier_id' => null,
                'line_description' => 'Prior month sales - overdue receivable',
                'receipt' => [
                    'receipt_number' => 'OR-DB-0002',
                    'amount' => 2000,
                    'receipt_date' => now()->subMonth()->toDateString(),
                    'reference_no' => 'RCPT-DB-0002',
                ],
            ],
            [
                'invoice_number' => 'SV-DB-0003',
                'control_number' => 'CN-SV-DB-0003',
                'invoice_type' => 'service',
                'invoice_date' => now()->subMonth()->startOfMonth()->addDays(8)->toDateString(),
                'due_date' => now()->subDays(5)->toDateString(),
                'subtotal' => 7000,
                'vat_amount' => 840,
                'total_amount' => 7840,
                'customer_id' => $customer->id,
                'supplier_id' => null,
                'line_description' => 'Service revenue - overdue receivable',
                'receipt' => null,
            ],
            [
                'invoice_number' => 'PI-DB-0001',
                'control_number' => 'CN-PI-DB-0001',
                'invoice_type' => 'purchase',
                'invoice_date' => now()->subDays(6)->toDateString(),
                'due_date' => now()->addDays(12)->toDateString(),
                'subtotal' => 9000,
                'vat_amount' => 1080,
                'total_amount' => 10080,
                'customer_id' => null,
                'supplier_id' => $supplier->id,
                'line_description' => 'Current month payable - open',
                'receipt' => null,
            ],
            [
                'invoice_number' => 'PI-DB-0002',
                'control_number' => 'CN-PI-DB-0002',
                'invoice_type' => 'purchase',
                'invoice_date' => now()->subMonths(3)->startOfMonth()->addDays(12)->toDateString(),
                'due_date' => now()->subDays(25)->toDateString(),
                'subtotal' => 11000,
                'vat_amount' => 1320,
                'total_amount' => 12320,
                'customer_id' => null,
                'supplier_id' => $supplier->id,
                'line_description' => 'Prior month payable - overdue',
                'receipt' => null,
            ],
        ];

        foreach ($dashboardInvoices as $dashboardInvoiceData) {
            $invoice = Invoice::query()->firstOrCreate([
                'invoice_number' => $dashboardInvoiceData['invoice_number'],
            ], [
                'branch_id' => $branch->id,
                'customer_id' => $dashboardInvoiceData['customer_id'],
                'supplier_id' => $dashboardInvoiceData['supplier_id'],
                'created_by' => $accountant->id,
                'control_number' => $dashboardInvoiceData['control_number'],
                'invoice_type' => $dashboardInvoiceData['invoice_type'],
                'invoice_date' => $dashboardInvoiceData['invoice_date'],
                'due_date' => $dashboardInvoiceData['due_date'],
                'subtotal' => $dashboardInvoiceData['subtotal'],
                'vat_amount' => $dashboardInvoiceData['vat_amount'],
                'total_amount' => $dashboardInvoiceData['total_amount'],
                'status' => 'issued',
                'issued_at' => now(),
                'remarks' => 'Dashboard-focused sample invoice',
            ]);

            InvoiceLine::query()->firstOrCreate([
                'invoice_id' => $invoice->id,
                'description' => $dashboardInvoiceData['line_description'],
            ], [
                'quantity' => 1,
                'unit_price' => $dashboardInvoiceData['subtotal'],
                'line_total' => $dashboardInvoiceData['subtotal'],
            ]);

            if (is_array($dashboardInvoiceData['receipt'])) {
                SalesReceipt::query()->firstOrCreate([
                    'receipt_number' => $dashboardInvoiceData['receipt']['receipt_number'],
                ], [
                    'invoice_id' => $invoice->id,
                    'branch_id' => $branch->id,
                    'customer_id' => $customer->id,
                    'receipt_date' => $dashboardInvoiceData['receipt']['receipt_date'],
                    'amount' => $dashboardInvoiceData['receipt']['amount'],
                    'payment_method' => 'bank',
                    'reference_no' => $dashboardInvoiceData['receipt']['reference_no'],
                    'remarks' => 'Partial receipt for dashboard metrics',
                    'created_by' => $accountant->id,
                ]);
            }
        }

        $salesOrder = SalesOrder::query()->firstOrCreate([
            'order_number' => 'SO-TEST-0001',
        ], [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'invoice_id' => $salesInvoice->id,
            'created_by' => $accountant->id,
            'order_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'confirmed',
            'subtotal' => 10000,
            'vat_amount' => 1200,
            'total_amount' => 11200,
            'remarks' => 'Sample sales order',
            'confirmed_at' => now(),
        ]);

        SalesOrderLine::query()->firstOrCreate([
            'sales_order_id' => $salesOrder->id,
            'description' => 'Office supply package',
        ], [
            'quantity' => 10,
            'unit_price' => 1000,
            'line_total' => 10000,
        ]);

        $purchaseOrder = PurchaseOrder::query()->firstOrCreate([
            'order_number' => 'PO-TEST-0001',
        ], [
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'invoice_id' => $purchaseInvoice->id,
            'created_by' => $accountant->id,
            'order_date' => now()->subDays(2)->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'received',
            'subtotal' => 8000,
            'vat_amount' => 960,
            'total_amount' => 8960,
            'remarks' => 'Sample purchase order',
            'received_at' => now()->subDay(),
            'billed_at' => now(),
        ]);

        PurchaseOrderLine::query()->firstOrCreate([
            'purchase_order_id' => $purchaseOrder->id,
            'description' => 'Office supplies bulk purchase',
        ], [
            'quantity' => 20,
            'received_quantity' => 20,
            'unit_price' => 400,
            'line_total' => 8000,
        ]);

        SalesReceipt::query()->firstOrCreate([
            'receipt_number' => 'OR-TEST-0001',
        ], [
            'invoice_id' => $salesInvoice->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'receipt_date' => now()->toDateString(),
            'amount' => 5000,
            'payment_method' => 'bank_transfer',
            'reference_no' => 'BT-TEST-0001',
            'remarks' => 'Partial payment for sample invoice',
            'created_by' => $accountant->id,
        ]);

        $bankAccount = BankAccount::query()->firstOrCreate([
            'account_number' => '0000-1111-2222',
        ], [
            'branch_id' => $branch->id,
            'bank_name' => 'Sample Bank PH',
            'account_name' => 'CAS Operating Account',
            'account_type' => 'checking',
            'current_balance' => 175000,
            'is_active' => true,
        ]);

        $bankTransaction = BankTransaction::query()->firstOrCreate([
            'bank_account_id' => $bankAccount->id,
            'reference_no' => 'BTX-TEST-0001',
        ], [
            'transaction_date' => now()->toDateString(),
            'transaction_type' => 'credit',
            'amount' => 5000,
            'description' => 'Collection from Delta Retail Corp',
            'created_by' => $accountant->id,
        ]);

        for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
            $monthDate = now()->subMonths($monthOffset);

            BankTransaction::query()->firstOrCreate([
                'bank_account_id' => $bankAccount->id,
                'reference_no' => 'DB-CREDIT-'.str_pad((string) $monthOffset, 2, '0', STR_PAD_LEFT),
            ], [
                'transaction_date' => $monthDate->copy()->startOfMonth()->addDays(10)->toDateString(),
                'transaction_type' => 'credit',
                'amount' => 12000 + (($monthOffset + 1) * 750),
                'description' => 'Dashboard seeded incoming cashflow',
                'created_by' => $accountant->id,
            ]);

            BankTransaction::query()->firstOrCreate([
                'bank_account_id' => $bankAccount->id,
                'reference_no' => 'DB-DEBIT-'.str_pad((string) $monthOffset, 2, '0', STR_PAD_LEFT),
            ], [
                'transaction_date' => $monthDate->copy()->startOfMonth()->addDays(20)->toDateString(),
                'transaction_type' => 'debit',
                'amount' => 7000 + (($monthOffset + 1) * 500),
                'description' => 'Dashboard seeded outgoing cashflow',
                'created_by' => $accountant->id,
            ]);
        }

        $statement = BankStatement::query()->firstOrCreate([
            'bank_account_id' => $bankAccount->id,
            'statement_date' => now()->endOfMonth()->toDateString(),
        ], [
            'opening_balance' => 170000,
            'closing_balance' => 175000,
            'created_by' => $admin->id,
        ]);

        $statementLine = BankStatementLine::query()->firstOrCreate([
            'bank_statement_id' => $statement->id,
            'reference_no' => 'BSA-TEST-0001',
        ], [
            'transaction_date' => now()->toDateString(),
            'description' => 'Collection credit',
            'transaction_type' => 'credit',
            'amount' => 5000,
            'balance' => 175000,
            'is_matched' => true,
        ]);

        $reconciliation = BankReconciliation::query()->firstOrCreate([
            'bank_account_id' => $bankAccount->id,
            'bank_statement_id' => $statement->id,
        ], [
            'status' => 'closed',
            'statement_opening_balance' => 170000,
            'statement_closing_balance' => 175000,
            'cleared_balance' => 175000,
            'difference' => 0,
            'closed_by' => $admin->id,
            'closed_at' => now(),
        ]);

        BankReconciliationMatch::query()->firstOrCreate([
            'bank_statement_line_id' => $statementLine->id,
        ], [
            'bank_reconciliation_id' => $reconciliation->id,
            'bank_transaction_id' => $bankTransaction->id,
            'matched_amount' => 5000,
            'created_by' => $admin->id,
        ]);

        $postedEntry = JournalEntry::query()->where('status', 'posted')->latest('id')->first();
        $postedLine = $postedEntry
            ? JournalEntryLine::query()->where('journal_entry_id', $postedEntry->id)->latest('id')->first()
            : null;

        if ($postedEntry && $postedLine) {
            Ledger::query()->firstOrCreate([
                'journal_entry_line_id' => $postedLine->id,
            ], [
                'branch_id' => $postedEntry->branch_id,
                'account_id' => $postedLine->account_id,
                'journal_entry_id' => $postedEntry->id,
                'posting_date' => $postedEntry->entry_date,
                'debit' => $postedLine->debit,
                'credit' => $postedLine->credit,
                'running_balance' => (float) $postedLine->debit - (float) $postedLine->credit,
                'control_number' => $postedEntry->control_number,
            ]);
        }

        ReportRun::query()->firstOrCreate([
            'reference_number' => 'RPT-TEST-0001',
        ], [
            'generated_by' => $admin->id,
            'branch_id' => $branch->id,
            'report_type' => 'trial_balance',
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->endOfMonth()->toDateString(),
            'page_count' => 2,
            'generated_at' => now(),
        ]);

        Backup::query()->firstOrCreate([
            'file_path' => 'backups/sample-cas-backup.sql',
        ], [
            'requested_by' => $admin->id,
            'status' => 'completed',
            'backup_at' => now()->subDays(2),
            'restore_at' => null,
        ]);

        UserLog::query()->firstOrCreate([
            'user_id' => $accountant->id,
            'activity' => 'seed.sample.data',
            'route' => '/seeders/cas-full-module-sample',
        ], [
            'method' => 'CLI',
            'ip_address' => '127.0.0.1',
            'occurred_at' => now(),
        ]);

        AuditLog::query()->firstOrCreate([
            'event' => 'seeded',
            'auditable_type' => Invoice::class,
            'auditable_id' => $salesInvoice->id,
        ], [
            'user_id' => $admin->id,
            'old_values' => null,
            'new_values' => ['invoice_number' => $salesInvoice->invoice_number],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'artisan-db-seed',
            'occurred_at' => now(),
        ]);
    }
}

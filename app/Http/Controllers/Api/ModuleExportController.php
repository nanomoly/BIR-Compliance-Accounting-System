<?php

namespace App\Http\Controllers\Api;

use App\Exports\ArrayReportExport;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BankTransaction;
use App\Models\Backup;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\SalesReceipt;
use App\Models\PayrollRun;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ModuleExportController extends Controller
{
    public function __invoke(Request $request, string $module): BinaryFileResponse
    {
        $definitions = $this->definitions();
        abort_unless(isset($definitions[$module]), 404);

        $definition = $definitions[$module];
        abort_unless($request->user()?->can($definition['permission']), 403);

        $fromDate = $request->date('from_date')?->startOfDay();
        $toDate = $request->date('to_date')?->endOfDay();

        $rows = $definition['rows']($fromDate, $toDate);
        $headings = $definition['headings'];

        return Excel::download(
            new ArrayReportExport($headings, $rows),
            $module.'-'.now()->format('YmdHis').'.xlsx',
        );
    }

    /**
     * @return array<string, array{permission: string, headings: array<int, string>, rows: \Closure(?\Illuminate\Support\Carbon, ?\Illuminate\Support\Carbon): array<int, array<int, mixed>>}>
     */
    private function definitions(): array
    {
        return [
            'accounts' => [
                'permission' => 'accounts.view',
                'headings' => ['Code', 'Name', 'Type', 'Normal Balance', 'Parent', 'Active'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    Account::query(),
                    'created_at',
                    $fromDate,
                    $toDate,
                )
                    ->with('parent:id,code,name')
                    ->orderBy('code')
                    ->get()
                    ->map(fn (Account $account): array => [
                        $account->code,
                        $account->name,
                        $account->type,
                        $account->normal_balance,
                        $account->parent?->code,
                        $account->is_active ? 'Yes' : 'No',
                    ])
                    ->values()
                    ->all(),
            ],
            'customers' => [
                'permission' => 'customers.view',
                'headings' => ['Code', 'Name', 'TIN', 'Email', 'Phone', 'Address', 'Branch'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    Customer::query(),
                    'created_at',
                    $fromDate,
                    $toDate,
                )
                    ->with('branch:id,name')
                    ->orderBy('code')
                    ->get()
                    ->map(fn (Customer $customer): array => [
                        $customer->code,
                        $customer->name,
                        $customer->tin,
                        $customer->email,
                        $customer->phone,
                        $customer->address,
                        $customer->branch?->name,
                    ])
                    ->values()
                    ->all(),
            ],
            'branches' => [
                'permission' => 'branches.view',
                'headings' => ['Code', 'Name', 'TIN', 'Address', 'Main Branch'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    Branch::query(),
                    'created_at',
                    $fromDate,
                    $toDate,
                )
                    ->orderBy('code')
                    ->get()
                    ->map(fn (Branch $branch): array => [
                        $branch->code,
                        $branch->name,
                        $branch->tin,
                        $branch->address,
                        $branch->is_main ? 'Yes' : 'No',
                    ])
                    ->values()
                    ->all(),
            ],
            'inventory' => [
                'permission' => 'inventory.view',
                'headings' => ['SKU', 'Name', 'Unit', 'On Hand', 'Reorder Level', 'Status'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    InventoryItem::query(),
                    'created_at',
                    $fromDate,
                    $toDate,
                )
                    ->orderBy('sku')
                    ->get()
                    ->map(fn (InventoryItem $item): array => [
                        $item->sku,
                        $item->name,
                        $item->unit,
                        $item->quantity_on_hand,
                        $item->reorder_level,
                        $item->is_active ? 'Active' : 'Inactive',
                    ])
                    ->values()
                    ->all(),
            ],
            'sales-orders' => [
                'permission' => 'sales.view',
                'headings' => ['Order Number', 'Order Date', 'Customer', 'Branch', 'Status', 'Subtotal', 'VAT', 'Total', 'Invoice Number'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    SalesOrder::query(),
                    'order_date',
                    $fromDate,
                    $toDate,
                )
                    ->with(['customer:id,name', 'branch:id,name', 'invoice:id,invoice_number'])
                    ->latest('id')
                    ->get()
                    ->map(fn (SalesOrder $order): array => [
                        $order->order_number,
                        $order->order_date,
                        $order->customer?->name,
                        $order->branch?->name,
                        $order->status,
                        $order->subtotal,
                        $order->vat_amount,
                        $order->total_amount,
                        $order->invoice?->invoice_number,
                    ])
                    ->values()
                    ->all(),
            ],
            'collections-receipts' => [
                'permission' => 'collections.view',
                'headings' => ['Receipt Number', 'Receipt Date', 'Invoice Number', 'Customer', 'Branch', 'Payment Method', 'Amount', 'Reference', 'Journal Entry', 'Journal Status', 'Posted At'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    SalesReceipt::query(),
                    'receipt_date',
                    $fromDate,
                    $toDate,
                )
                    ->with(['invoice:id,invoice_number', 'customer:id,name', 'branch:id,name', 'journalEntry:id,entry_number,status,posted_at'])
                    ->latest('id')
                    ->get()
                    ->map(fn (SalesReceipt $receipt): array => [
                        $receipt->receipt_number,
                        $receipt->receipt_date,
                        $receipt->invoice?->invoice_number,
                        $receipt->customer?->name,
                        $receipt->branch?->name,
                        $receipt->payment_method,
                        $receipt->amount,
                        $receipt->reference_no,
                        $receipt->journalEntry?->entry_number,
                        $receipt->journalEntry?->status?->value,
                        $this->formatDateTimePh($receipt->journalEntry?->posted_at),
                    ])
                    ->values()
                    ->all(),
            ],
            'purchase-orders' => [
                'permission' => 'purchases.view',
                'headings' => ['Order Number', 'Order Date', 'Supplier', 'Branch', 'Status', 'Subtotal', 'VAT', 'Total', 'Bill Number'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    PurchaseOrder::query(),
                    'order_date',
                    $fromDate,
                    $toDate,
                )
                    ->with(['supplier:id,name', 'branch:id,name', 'invoice:id,invoice_number'])
                    ->latest('id')
                    ->get()
                    ->map(fn (PurchaseOrder $order): array => [
                        $order->order_number,
                        $order->order_date,
                        $order->supplier?->name,
                        $order->branch?->name,
                        $order->status,
                        $order->subtotal,
                        $order->vat_amount,
                        $order->total_amount,
                        $order->invoice?->invoice_number,
                    ])
                    ->values()
                    ->all(),
            ],
            'payroll-runs' => [
                'permission' => 'payroll.view',
                'headings' => ['Run Number', 'Period', 'Status', 'Gross Total', 'Deduction Total', 'Net Total', 'Approved At', 'Posted At'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    PayrollRun::query(),
                    'created_at',
                    $fromDate,
                    $toDate,
                )
                    ->with('period:id,name')
                    ->latest('id')
                    ->get()
                    ->map(fn (PayrollRun $run): array => [
                        $run->run_number,
                        $run->period?->name,
                        $run->status,
                        $run->gross_total,
                        $run->deduction_total,
                        $run->net_total,
                        $this->formatDateTimePh($run->approved_at),
                        $this->formatDateTimePh($run->posted_at),
                    ])
                    ->values()
                    ->all(),
            ],
            'hr' => [
                'permission' => 'hr.view',
                'headings' => ['Employee No', 'First Name', 'Last Name', 'Position', 'Department', 'Hire Date', 'Monthly Rate', 'Status'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    Employee::query(),
                    'created_at',
                    $fromDate,
                    $toDate,
                )
                    ->orderBy('employee_no')
                    ->get()
                    ->map(fn (Employee $employee): array => [
                        $employee->employee_no,
                        $employee->first_name,
                        $employee->last_name,
                        $employee->position,
                        $employee->department,
                        $employee->hire_date,
                        $employee->monthly_rate,
                        $employee->is_active ? 'Active' : 'Inactive',
                    ])
                    ->values()
                    ->all(),
            ],
            'banking' => [
                'permission' => 'banking.view',
                'headings' => ['Date', 'Bank', 'Account Number', 'Type', 'Amount', 'Reference', 'Description'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    BankTransaction::query(),
                    'transaction_date',
                    $fromDate,
                    $toDate,
                )
                    ->with('bankAccount:id,bank_name,account_number')
                    ->latest('transaction_date')
                    ->get()
                    ->map(fn (BankTransaction $transaction): array => [
                        $transaction->transaction_date,
                        $transaction->bankAccount?->bank_name,
                        $transaction->bankAccount?->account_number,
                        $transaction->transaction_type,
                        $transaction->amount,
                        $transaction->reference_no,
                        $transaction->description,
                    ])
                    ->values()
                    ->all(),
            ],
            'suppliers' => [
                'permission' => 'suppliers.view',
                'headings' => ['Code', 'Name', 'TIN', 'Email', 'Phone', 'Address', 'Branch'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    Supplier::query(),
                    'created_at',
                    $fromDate,
                    $toDate,
                )
                    ->with('branch:id,name')
                    ->orderBy('code')
                    ->get()
                    ->map(fn (Supplier $supplier): array => [
                        $supplier->code,
                        $supplier->name,
                        $supplier->tin,
                        $supplier->email,
                        $supplier->phone,
                        $supplier->address,
                        $supplier->branch?->name,
                    ])
                    ->values()
                    ->all(),
            ],
            'journals' => [
                'permission' => 'journals.view',
                'headings' => ['Entry Number', 'Entry Date', 'Journal Type', 'Status', 'Total Debit', 'Total Credit', 'Description', 'Reference'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    JournalEntry::query(),
                    'entry_date',
                    $fromDate,
                    $toDate,
                )
                    ->latest('id')
                    ->get()
                    ->map(fn (JournalEntry $entry): array => [
                        $entry->entry_number,
                        $entry->entry_date,
                        $entry->journal_type,
                        $entry->status,
                        $entry->total_debit,
                        $entry->total_credit,
                        $entry->description,
                        $entry->reference_no,
                    ])
                    ->values()
                    ->all(),
            ],
            'e-invoices' => [
                'permission' => 'e_invoices.view',
                'headings' => ['Invoice Number', 'Control Number', 'Type', 'Date', 'Status', 'Subtotal', 'VAT', 'Total'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    Invoice::query(),
                    'invoice_date',
                    $fromDate,
                    $toDate,
                )
                    ->latest('id')
                    ->get()
                    ->map(fn (Invoice $invoice): array => [
                        $invoice->invoice_number,
                        $invoice->control_number,
                        $invoice->invoice_type,
                        $invoice->invoice_date,
                        $invoice->status,
                        $invoice->subtotal,
                        $invoice->vat_amount,
                        $invoice->total_amount,
                    ])
                    ->values()
                    ->all(),
            ],
            'system-users' => [
                'permission' => 'users.view',
                'headings' => ['Name', 'Email', 'Role', 'Branch'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    User::query(),
                    'created_at',
                    $fromDate,
                    $toDate,
                )
                    ->with(['roles:id,name', 'branch:id,name'])
                    ->latest('id')
                    ->get()
                    ->map(fn (User $user): array => [
                        $user->name,
                        $user->email,
                        $user->role?->value ?? $user->roles->pluck('name')->first(),
                        $user->branch?->name,
                    ])
                    ->values()
                    ->all(),
            ],
            'backups' => [
                'permission' => 'backups.view',
                'headings' => ['File Path', 'Status', 'Backup At', 'Restore At'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    Backup::query(),
                    'backup_at',
                    $fromDate,
                    $toDate,
                )
                    ->latest('backup_at')
                    ->get()
                    ->map(fn (Backup $backup): array => [
                        $backup->file_path,
                        $backup->status,
                        $this->formatDateTimePh($backup->backup_at),
                        $this->formatDateTimePh($backup->restore_at),
                    ])
                    ->values()
                    ->all(),
            ],
            'audit-logs' => [
                'permission' => 'audit_trail.view',
                'headings' => ['Date/Time', 'User', 'Event', 'Type', 'ID'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    AuditLog::query(),
                    'occurred_at',
                    $fromDate,
                    $toDate,
                )
                    ->with('user:id,name')
                    ->latest('occurred_at')
                    ->get()
                    ->map(fn (AuditLog $log): array => [
                        $this->formatDateTimePh($log->occurred_at),
                        $log->user?->name ?? $log->user_id,
                        $log->event,
                        $log->auditable_type,
                        $log->auditable_id,
                    ])
                    ->values()
                    ->all(),
            ],
            'user-activity-logs' => [
                'permission' => 'audit_trail.view',
                'headings' => ['Date/Time', 'User', 'Activity', 'Route', 'Method', 'IP'],
                'rows' => fn ($fromDate, $toDate): array => $this->applyDateRange(
                    UserLog::query(),
                    'occurred_at',
                    $fromDate,
                    $toDate,
                )
                    ->with('user:id,name')
                    ->latest('occurred_at')
                    ->get()
                    ->map(fn (UserLog $log): array => [
                        $this->formatDateTimePh($log->occurred_at),
                        $log->user?->name ?? $log->user_id,
                        $log->activity,
                        $log->route,
                        $log->method,
                        $log->ip_address,
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    private function applyDateRange(Builder $query, string $column, $fromDate, $toDate): Builder
    {
        if ($fromDate !== null) {
            $query->where($column, '>=', $fromDate);
        }

        if ($toDate !== null) {
            $query->where($column, '<=', $toDate);
        }

        return $query;
    }

    private function formatDateTimePh($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->setTimezone('Asia/Manila')->format('Y-m-d H:i');
    }
}

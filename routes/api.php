<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuditTrailController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\BankReconciliationController;
use App\Http\Controllers\Api\BankTransactionController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EInvoiceController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\InventoryMovementController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\ModuleExportController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalesReceiptController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SystemInfoController;
use App\Http\Controllers\Api\SystemUserController;
use App\Http\Controllers\Api\UserAccessController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::apiResource('accounts', AccountController::class);

    Route::get('customers', [CustomerController::class, 'index']);
    Route::get('customers/catalog', [CustomerController::class, 'catalog']);
    Route::post('customers', [CustomerController::class, 'store']);
    Route::put('customers/{customer}', [CustomerController::class, 'update']);
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);

    Route::get('suppliers', [SupplierController::class, 'index']);
    Route::get('suppliers/catalog', [SupplierController::class, 'catalog']);
    Route::post('suppliers', [SupplierController::class, 'store']);
    Route::put('suppliers/{supplier}', [SupplierController::class, 'update']);
    Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);

    Route::get('branches', [BranchController::class, 'index']);
    Route::post('branches', [BranchController::class, 'store']);
    Route::put('branches/{branch}', [BranchController::class, 'update']);
    Route::delete('branches/{branch}', [BranchController::class, 'destroy']);

    Route::get('inventory-items', [InventoryItemController::class, 'index']);
    Route::post('inventory-items', [InventoryItemController::class, 'store']);
    Route::put('inventory-items/{inventoryItem}', [InventoryItemController::class, 'update']);
    Route::delete('inventory-items/{inventoryItem}', [InventoryItemController::class, 'destroy']);
    Route::get('inventory-movements', [InventoryMovementController::class, 'index']);
    Route::post('inventory-movements', [InventoryMovementController::class, 'store']);

    Route::get('sales-orders', [SalesOrderController::class, 'index']);
    Route::post('sales-orders', [SalesOrderController::class, 'store']);
    Route::put('sales-orders/{salesOrder}', [SalesOrderController::class, 'update']);
    Route::post('sales-orders/{salesOrder}/confirm', [SalesOrderController::class, 'confirm']);
    Route::post('sales-orders/{salesOrder}/convert-to-invoice', [SalesOrderController::class, 'convertToInvoice']);
    Route::delete('sales-orders/{salesOrder}', [SalesOrderController::class, 'destroy']);

    Route::get('collections/receipts', [SalesReceiptController::class, 'index']);
    Route::get('collections/catalog', [SalesReceiptController::class, 'catalog']);
    Route::post('collections/receipts', [SalesReceiptController::class, 'store']);

    Route::get('purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::put('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
    Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
    Route::post('purchase-orders/{purchaseOrder}/convert-to-bill', [PurchaseOrderController::class, 'convertToBill']);
    Route::delete('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy']);

    Route::get('employees', [EmployeeController::class, 'index']);
    Route::post('employees', [EmployeeController::class, 'store']);
    Route::put('employees/{employee}', [EmployeeController::class, 'update']);
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy']);

    Route::get('payroll-periods', [PayrollController::class, 'periods']);
    Route::post('payroll-periods', [PayrollController::class, 'storePeriod']);
    Route::get('payroll-runs', [PayrollController::class, 'runs']);
    Route::get('payroll-runs/{payrollRun}', [PayrollController::class, 'showRun']);
    Route::post('payroll-runs/generate', [PayrollController::class, 'generateRun']);
    Route::post('payroll-runs/{payrollRun}/approve', [PayrollController::class, 'approveRun']);
    Route::post('payroll-runs/{payrollRun}/post', [PayrollController::class, 'postRun']);

    Route::get('bank-accounts', [BankAccountController::class, 'index']);
    Route::post('bank-accounts', [BankAccountController::class, 'store']);
    Route::put('bank-accounts/{bankAccount}', [BankAccountController::class, 'update']);
    Route::delete('bank-accounts/{bankAccount}', [BankAccountController::class, 'destroy']);

    Route::get('bank-transactions', [BankTransactionController::class, 'index']);
    Route::post('bank-transactions', [BankTransactionController::class, 'store']);
    Route::delete('bank-transactions/{bankTransaction}', [BankTransactionController::class, 'destroy']);

    Route::post('banking/statements/import', [BankReconciliationController::class, 'importStatement']);
    Route::get('banking/reconciliations', [BankReconciliationController::class, 'index']);
    Route::get('banking/reconciliations/{bankReconciliation}', [BankReconciliationController::class, 'show']);
    Route::post('banking/reconciliations/{bankReconciliation}/match', [BankReconciliationController::class, 'match']);
    Route::post('banking/reconciliations/{bankReconciliation}/tag-unmatched', [BankReconciliationController::class, 'tagUnmatchedReason']);
    Route::delete('banking/reconciliations/{bankReconciliation}/matches/{bankReconciliationMatch}', [BankReconciliationController::class, 'unmatch']);
    Route::post('banking/reconciliations/{bankReconciliation}/close', [BankReconciliationController::class, 'close']);
    Route::post('banking/reconciliations/{bankReconciliation}/reopen', [BankReconciliationController::class, 'reopen']);

    Route::get('journal-entries', [JournalEntryController::class, 'index']);
    Route::post('journal-entries', [JournalEntryController::class, 'store']);
    Route::get('journal-entries/{journalEntry}', [JournalEntryController::class, 'show']);
    Route::post('journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post']);
    Route::post('journal-entries/{journalEntry}/reverse', [JournalEntryController::class, 'reverse']);

    Route::get('e-invoices', [EInvoiceController::class, 'index']);
    Route::post('e-invoices', [EInvoiceController::class, 'store']);
    Route::get('e-invoices/{invoice}', [EInvoiceController::class, 'show']);
    Route::get('e-invoices/{invoice}/print', [EInvoiceController::class, 'print']);
    Route::post('e-invoices/{invoice}/issue', [EInvoiceController::class, 'issue']);
    Route::post('e-invoices/{invoice}/cancel', [EInvoiceController::class, 'cancel']);
    Route::post('e-invoices/{invoice}/transmit', [EInvoiceController::class, 'transmit']);

    Route::get('reports/trial-balance', [ReportController::class, 'trialBalance']);
    Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet']);
    Route::get('reports/income-statement', [ReportController::class, 'incomeStatement']);
    Route::get('reports/journal-book', [ReportController::class, 'journalBook']);
    Route::get('reports/general-ledger-book', [ReportController::class, 'generalLedgerBook']);
    Route::get('reports/accounts-receivable-ledger', [ReportController::class, 'accountsReceivableLedger']);
    Route::get('reports/accounts-payable-ledger', [ReportController::class, 'accountsPayableLedger']);
    Route::get('reports/customer-ledger', [ReportController::class, 'customerLedger']);
    Route::get('reports/supplier-ledger', [ReportController::class, 'supplierLedger']);

    Route::get('backups', [BackupController::class, 'index']);
    Route::post('backups', [BackupController::class, 'store']);
    Route::post('backups/{backupId}/restore', [BackupController::class, 'restore']);

    Route::get('system-info', [SystemInfoController::class, 'show']);
    Route::put('system-info/company-profile', [SystemInfoController::class, 'updateCompanyProfile']);

    Route::get('system-users', [SystemUserController::class, 'index']);
    Route::post('system-users', [SystemUserController::class, 'store']);
    Route::get('system-users/catalog', [SystemUserController::class, 'catalog']);

    Route::get('audit-logs', [AuditTrailController::class, 'index']);
    Route::get('user-activity-logs', [AuditTrailController::class, 'activities']);

    Route::get('access/modules', [UserAccessController::class, 'modules']);
    Route::get('access/catalog', [UserAccessController::class, 'catalog']);
    Route::get('access/users/{user}', [UserAccessController::class, 'show']);
    Route::post('access/users/{user}/assign', [UserAccessController::class, 'assign']);

    Route::get('exports/{module}', ModuleExportController::class);
});

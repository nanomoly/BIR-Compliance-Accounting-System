<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuditTrailController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\EInvoiceController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\ReportController;
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

    Route::get('suppliers', [SupplierController::class, 'index']);
    Route::get('suppliers/catalog', [SupplierController::class, 'catalog']);
    Route::post('suppliers', [SupplierController::class, 'store']);
    Route::put('suppliers/{supplier}', [SupplierController::class, 'update']);

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
});

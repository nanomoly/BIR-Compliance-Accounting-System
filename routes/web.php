<?php

use App\Http\Controllers\Cas\CasPageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('cas.dashboard');
    }

    return redirect()->route('login');
})->name('home');

Route::get('/privacy', fn () => Inertia::render('Privacy'))->name('privacy');
Route::get('/terms', fn () => Inertia::render('Terms'))->name('terms');

Route::get('dashboard', function () {
    return redirect()->route('cas.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->prefix('cas')->group(function (): void {
    Route::get('/', [CasPageController::class, 'dashboard'])->name('cas.dashboard');
    Route::get('/accounts', [CasPageController::class, 'accounts'])->middleware('can:accounts.view')->name('cas.accounts');
    Route::get('/customers', [CasPageController::class, 'customers'])->middleware('can:customers.view')->name('cas.customers');
    Route::get('/suppliers', [CasPageController::class, 'suppliers'])->middleware('can:suppliers.view')->name('cas.suppliers');
    Route::get('/journals', [CasPageController::class, 'journals'])->middleware('can:journals.view')->name('cas.journals');
    Route::get('/e-invoicing', [CasPageController::class, 'eInvoicing'])->middleware('can:e_invoices.view')->name('cas.e-invoicing');
    Route::get('/reports', [CasPageController::class, 'reports'])->middleware('can:reports.view')->name('cas.reports');
    Route::get('/ledgers', [CasPageController::class, 'ledgers'])->middleware('can:ledgers.view')->name('cas.ledgers');
    Route::get('/backups', [CasPageController::class, 'backups'])->middleware('can:backups.view')->name('cas.backups');
    Route::get('/system-info', [CasPageController::class, 'systemInfo'])->middleware('can:system_info.view')->name('cas.system-info');
    Route::get('/users', [CasPageController::class, 'users'])->middleware('can:users.view')->name('cas.users');
    Route::get('/user-access', [CasPageController::class, 'userAccess'])->middleware('can:user_access.view')->name('cas.user-access');
    Route::get('/audit-trail', [CasPageController::class, 'auditTrail'])->middleware('can:audit_trail.view')->name('cas.audit-trail');
});

require __DIR__.'/settings.php';

<?php

namespace App\Http\Controllers\Cas;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CasPageController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('cas/Dashboard');
    }

    public function accounts(): Response
    {
        return Inertia::render('cas/Accounts');
    }

    public function customers(): Response
    {
        return Inertia::render('cas/Customers');
    }

    public function suppliers(): Response
    {
        return Inertia::render('cas/Suppliers');
    }

    public function branches(): Response
    {
        return Inertia::render('cas/Branches');
    }

    public function inventory(): Response
    {
        return Inertia::render('cas/Inventory');
    }

    public function sales(): Response
    {
        return Inertia::render('cas/Sales');
    }

    public function collections(): Response
    {
        return Inertia::render('cas/Collections');
    }

    public function purchases(): Response
    {
        return Inertia::render('cas/Purchases');
    }

    public function hr(): Response
    {
        return Inertia::render('cas/HR');
    }

    public function payroll(): Response
    {
        return Inertia::render('cas/Payroll');
    }

    public function banking(): Response
    {
        return Inertia::render('cas/Banking');
    }

    public function journals(): Response
    {
        return Inertia::render('cas/Journals');
    }

    public function eInvoicing(): Response
    {
        return Inertia::render('cas/EInvoicing');
    }

    public function reports(): Response
    {
        return Inertia::render('cas/Reports');
    }

    public function ledgers(): Response
    {
        return Inertia::render('cas/Ledgers');
    }

    public function backups(): Response
    {
        return Inertia::render('cas/Backups');
    }

    public function systemInfo(): Response
    {
        return Inertia::render('cas/SystemInfo');
    }

    public function userAccess(): Response
    {
        return Inertia::render('cas/UserAccess');
    }

    public function users(): Response
    {
        return Inertia::render('cas/Users');
    }

    public function auditTrail(): Response
    {
        return Inertia::render('cas/AuditTrail');
    }
}

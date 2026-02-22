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

<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Permission;

function grantReportPermissions(User $user): void
{
    $view = Permission::findOrCreate('reports.view', 'web');
    $export = Permission::findOrCreate('reports.export', 'web');

    $user->givePermissionTo([$view, $export]);
}

it('has all report endpoints available for authorized users', function () {
    $user = User::factory()->create();
    grantReportPermissions($user);

    $this->actingAs($user);

    $fromDate = CarbonImmutable::today()->startOfMonth()->toDateString();
    $toDate = CarbonImmutable::today()->toDateString();

    $endpoints = [
        'trial-balance',
        'balance-sheet',
        'income-statement',
        'journal-book',
        'general-ledger-book',
        'accounts-receivable-ledger',
        'accounts-payable-ledger',
        'customer-ledger',
        'supplier-ledger',
    ];

    foreach ($endpoints as $endpoint) {
        $this->getJson("/api/reports/{$endpoint}?from_date={$fromDate}&to_date={$toDate}&format=json")
            ->assertOk()
            ->assertJsonStructure([
                'reference_number',
                'page_count',
                'rows',
            ]);
    }
});

<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Policies\AccountPolicy;
use App\Policies\JournalEntryPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Account::class => AccountPolicy::class,
        JournalEntry::class => JournalEntryPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}

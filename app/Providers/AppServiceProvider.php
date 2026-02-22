<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Observers\AuditObserver;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Contracts\JournalEntryRepositoryInterface;
use App\Repositories\Contracts\LedgerRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\AccountRepository;
use App\Repositories\Eloquent\InvoiceRepository;
use App\Repositories\Eloquent\JournalEntryRepository;
use App\Repositories\Eloquent\LedgerRepository;
use App\Repositories\Eloquent\UserRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AccountRepositoryInterface::class, AccountRepository::class);
        $this->app->bind(JournalEntryRepositoryInterface::class, JournalEntryRepository::class);
        $this->app->bind(LedgerRepositoryInterface::class, LedgerRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Account::observe(AuditObserver::class);
        Customer::observe(AuditObserver::class);
        Supplier::observe(AuditObserver::class);
        JournalEntry::observe(AuditObserver::class);
        Invoice::observe(AuditObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}

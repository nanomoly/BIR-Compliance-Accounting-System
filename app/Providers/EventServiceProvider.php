<?php

namespace App\Providers;

use App\Events\JournalEntryPosted;
use App\Listeners\PostJournalEntryToLedger;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        JournalEntryPosted::class => [
            PostJournalEntryToLedger::class,
        ],
    ];
}

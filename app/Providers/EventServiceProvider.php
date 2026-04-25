<?php
// app/Providers/EventServiceProvider.php

namespace App\Providers;

use App\Events\PasswordResetRequested;
use App\Listeners\SendPasswordResetNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        PasswordResetRequested::class => [
            SendPasswordResetNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }
}
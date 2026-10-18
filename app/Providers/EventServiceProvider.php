<?php

namespace App\Providers;

use App\Listeners\SendMembershipChange;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamMemberRemoved;
use Laravel\Jetstream\Events\TeamMemberUpdated;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        // Platforms that copy a company's membership are told at once (see MembershipWebhook).
        TeamMemberRemoved::class => [[SendMembershipChange::class, 'handleRemoved']],
        TeamMemberUpdated::class => [[SendMembershipChange::class, 'handleUpdated']],
        TeamDeleted::class => [[SendMembershipChange::class, 'handleDeleted']],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}

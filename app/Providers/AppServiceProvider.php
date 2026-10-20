<?php

namespace App\Providers;

use App\Events\Knowledge\AnswerAcceptanceRevoked;
use App\Events\Knowledge\AnswerAccepted;
use App\Events\Knowledge\AnswerPublished;
use App\Events\Knowledge\QuestionSubmitted;
use App\Listeners\ForwardKnowledgeEventToPoints;
use App\Models\File;
use App\Models\Folder;
use App\Models\Questions;
use App\Models\Solutions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Community knowledge events -> durable outbox -> the Dot Points ledger (signed, retried).
        foreach ([
            QuestionSubmitted::class,
            AnswerPublished::class,
            AnswerAccepted::class,
            AnswerAcceptanceRevoked::class,
        ] as $knowledgeEvent) {
            Event::listen($knowledgeEvent, ForwardKnowledgeEventToPoints::class);
        }

        foreach ([
            'action-message',
            'action-section',
            'application-logo',
            'authentication-card',
            'authentication-card-logo',
            'banner',
            'button',
            'checkbox',
            'confirmation-modal',
            'confirms-password',
            'danger-button',
            'dialog-modal',
            'dropdown',
            'dropdown-link',
            'form-section',
            'input',
            'input-error',
            'label',
            'modal',
            'nav-link',
            'responsive-nav-link',
            'secondary-button',
            'section-border',
            'section-title',
            'switchable-team',
            'validation-errors',
        ] as $component) {
            Blade::component("vendor.jetstream.components.{$component}", "jet-{$component}");
        }

        RateLimiter::for('ecosystem-redeem', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        Relation::morphMap([
            'file' => File::class,
            'folder' => Folder::class,
            'solutions' => Solutions::class,
            'questions' => Questions::class,
        ]);
    }
}

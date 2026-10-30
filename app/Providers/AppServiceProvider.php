<?php

namespace App\Providers;

use App\Events\Knowledge\AnswerAcceptanceRevoked;
use App\Events\Knowledge\AnswerAccepted;
use App\Events\Knowledge\AnswerPublished;
use App\Events\Knowledge\ContentModerationChanged;
use App\Events\Knowledge\ContentReported;
use App\Events\Knowledge\ContributionApproved;
use App\Events\Knowledge\KnowledgeAdopted;
use App\Events\Knowledge\LibraryEntryWithdrawn;
use App\Events\Knowledge\QuestionSubmitted;
use App\Events\Knowledge\SolutionUnverified;
use App\Events\Knowledge\SolutionVerified;
use App\Listeners\ForwardKnowledgeEventToMemory;
use App\Listeners\ForwardKnowledgeEventToPoints;
use App\Listeners\RecordInAppNotice;
use App\Listeners\RefreshAchievements;
use App\Livewire\Teams\TeamMemberManager;
use App\Models\File;
use App\Models\Folder;
use App\Models\Questions;
use App\Models\Solutions;
use App\Support\CanonicalUrl;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Cashier registers POST /stripe/webhook, and without a webhook secret it accepts unsigned events from anyone. Leave the route out
        // until STRIPE_WEBHOOK_SECRET is set.
        if (! config('cashier.webhook.secret')) {
            Cashier::ignoreRoutes();
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->isProduction()) {
            CanonicalUrl::apply();
        }

        // Jetstream's team member manager with a server-side check on cancelling invitations (app/Livewire/Teams/TeamMemberManager.php).
        Livewire::component('teams.team-member-manager', TeamMemberManager::class);

        // Community knowledge events -> durable outbox -> the Dot Points ledger (signed, retried).
        foreach ([
            QuestionSubmitted::class,
            AnswerPublished::class,
            AnswerAccepted::class,
            AnswerAcceptanceRevoked::class,
        ] as $knowledgeEvent) {
            Event::listen($knowledgeEvent, ForwardKnowledgeEventToPoints::class);
            // Milestones follow the same facts; separate from the ledger and never able to fail the request.
            Event::listen($knowledgeEvent, RefreshAchievements::class);
        }

        // Moderator decisions the ledger needs to know about: a solution verified or unverified, a report, an approval, an adoption into the library or its withdrawal.
        // (No milestones follow these: ContentModerationChanged already refreshes them.)
        foreach ([SolutionVerified::class, SolutionUnverified::class, ContentReported::class, ContributionApproved::class, KnowledgeAdopted::class, LibraryEntryWithdrawn::class] as $moderationEvent) {
            Event::listen($moderationEvent, ForwardKnowledgeEventToPoints::class);
        }

        // In-app notices to the person an event is about: an answer to their question, an accepted answer, a verified solution, work added to the library.
        foreach ([AnswerPublished::class, AnswerAccepted::class, SolutionVerified::class, KnowledgeAdopted::class] as $noticeEvent) {
            Event::listen($noticeEvent, RecordInAppNotice::class);
        }

        // Opt-in only: an accepted answer or a verified solution of someone who let Dot.Memory remember their topics.
        foreach ([AnswerAccepted::class, SolutionVerified::class] as $participationEvent) {
            Event::listen($participationEvent, ForwardKnowledgeEventToMemory::class);
        }

        // Hidden, restored, verified or unverified: these change what counts as contribution (they are not ledger events).
        Event::listen(ContentModerationChanged::class, RefreshAchievements::class);

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

        RateLimiter::for('knowledge-candidates', fn (Request $request) => Limit::perMinute((int) config('knowledge.candidates.per_minute', 60))->by($request->ip()));
        RateLimiter::for('ecosystem-redeem', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        // Their own buckets, so a burst of payment or room callbacks cannot lock out sign-in redeems, or the reverse. Verification of the
        // signature happens after the limiter, so the allowance is generous: a 200-person room start sends 200 joins in a minute.
        RateLimiter::for('billing-callbacks', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
        RateLimiter::for('livekit-webhook', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));

        Relation::morphMap([
            'file' => File::class,
            'folder' => Folder::class,
            'solutions' => Solutions::class,
            'questions' => Questions::class,
        ]);
    }
}

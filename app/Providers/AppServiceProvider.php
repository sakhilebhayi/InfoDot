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
use App\Listeners\SkipMailToReservedDomains;
use App\Livewire\Profile\UpdateProfileInformationForm;
use App\Livewire\Teams\TeamMemberManager;
use App\Livewire\Teams\TransferOwnershipForm;
use App\Models\BusinessAppeal;
use App\Models\BusinessReport;
use App\Models\BusinessVerification;
use App\Models\EnquiryMessage;
use App\Models\File;
use App\Models\Folder;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Team;
use App\Services\Demo\DemoRegistry;
use App\Services\Platforms\PlatformNotices;
use App\Support\CanonicalUrl;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
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

        // One register of demonstration content per request (R-49): it is read once and asked many times.
        $this->app->scoped(DemoRegistry::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->isProduction()) {
            CanonicalUrl::apply($this->app->runningInConsole() ? null : $this->app['request']);
        }

        // R-50: the owner and administrators of a company are told when its entitlement to a platform changes.
        PlatformNotices::register();

        // Jetstream's team member manager with a server-side check on cancelling invitations (app/Livewire/Teams/TeamMemberManager.php).
        Livewire::component('teams.team-member-manager', TeamMemberManager::class);
        // R-49: the owner hands a company to one of its members (app/Livewire/Teams/TransferOwnershipForm.php).
        Livewire::component('teams.transfer-ownership-form', TransferOwnershipForm::class);
        // Jetstream's profile form, returning to the account page after a picture is saved (app/Livewire/Profile/UpdateProfileInformationForm.php).
        Livewire::component('profile.update-profile-information-form', UpdateProfileInformationForm::class);

        // R-49: the demonstration register hears of every record created, and keeps the ones `demo:install` or a demonstration account made.
        Event::listen('eloquent.created: *', function (string $event, array $models): void {
            $this->app->make(DemoRegistry::class)->created($models[0]);
        });
        // R-49: nothing is ever mailed to an address on a reserved domain (the demonstration accounts' addresses are).
        Event::listen(MessageSending::class, SkipMailToReservedDomains::class);

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
        RateLimiter::for('finance-callbacks', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
        RateLimiter::for('livekit-webhook', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));

        Relation::morphMap([
            'file' => File::class,
            'folder' => Folder::class,
            'solutions' => Solutions::class,
            'questions' => Questions::class,
            'team' => Team::class,
            'business_verification' => BusinessVerification::class,
            'enquiry_message' => EnquiryMessage::class,
            'business_report' => BusinessReport::class,
            'business_appeal' => BusinessAppeal::class,
        ]);
    }
}

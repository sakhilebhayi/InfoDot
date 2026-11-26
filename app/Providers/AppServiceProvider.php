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
use App\Models\User;
use App\Services\Accounts\SignInDevices;
use App\Services\Demo\DemoRegistry;
use App\Services\Growth\OnboardingEvents;
use App\Services\Notifications\AccountMail;
use App\Services\Platforms\PlatformNotices;
use App\Services\Sso\PlatformContext;
use App\Services\Web\SafeFetcher;
use App\Support\CanonicalUrl;
use App\Support\Input;
use App\Support\PlatformSecret;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Laravel\Jetstream\Events\TeamMemberRemoved;
use Laravel\Jetstream\Events\TeamMemberUpdated;
use Laravel\Jetstream\Jetstream;
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
        // One fetcher (and one time budget) per request, shared by robots.txt checks and the page reader.
        $this->app->scoped(SafeFetcher::class);

        // Cashier registers POST /stripe/webhook. InfoDot takes no payments through Stripe (payments are Dot.Finance's), so the route is
        // never registered, whatever is in the environment (R-72, F-03).
        Cashier::ignoreRoutes();

        // R-72 (S-03): the breached-password check asks Pwned Passwords for the first five characters of the password's SHA-1 hash only.
        // Laravel's default waits up to 30 seconds; three is plenty, and if the service does not answer the password is not refused.
        $this->app->singleton(UncompromisedVerifier::class, fn ($app) => new NotPwnedVerifier($app[HttpFactory::class], 3));

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
        // R-72 (S-03): every new or changed password is at least 12 characters and not one found in a public breach.
        Password::defaults(fn () => Password::min(12)->uncompromised());

        // R-72 (E-13): the two framework emails speak in InfoDot's own words, without the default "Regards" sign-off.
        ResetPassword::toMailUsing(fn ($notifiable, string $token) => (new MailMessage)
            ->subject('Choose a new InfoDot password')
            ->greeting('Hello'.($notifiable->name ? ' '.$notifiable->name : '').',')
            ->line('Someone asked to choose a new password for the InfoDot account that uses this email address.')
            ->action('Choose a new password', url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false)))
            ->line('The link works for '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutes.')
            ->line('If you did not ask for this, you can ignore this email. Your password stays as it is.')
            ->salutation('InfoDot'));

        VerifyEmail::toMailUsing(fn ($notifiable, string $url) => (new MailMessage)
            ->subject('Confirm your email address for InfoDot')
            ->greeting('Hello'.($notifiable->name ? ' '.$notifiable->name : '').',')
            ->line('Confirm this email address to finish setting up your InfoDot account.')
            ->action('Confirm my email address', $url)
            ->line('If you did not create an InfoDot account, you can ignore this email.')
            ->salutation('InfoDot'));

        if ($this->app->isProduction()) {
            CanonicalUrl::apply($this->app->runningInConsole() ? null : $this->app['request']);
        }

        // R-54: the way-in pages know which platform started the sign-in, and only the allowlisted branding of it (null on a
        // direct visit). Resolved lazily from the server-side pending request; nothing in the address is read.
        View::composer(['auth.*', 'onboarding.*', 'layouts.guest'], function ($view) {
            if (! array_key_exists('platformBranding', $view->getData())) {
                $view->with('platformBranding', request()->hasSession() ? app(PlatformContext::class)->branding() : null);
            }

            // The flow id the way-in page was opened with: its form and its links to the other way-in pages carry it on.
            if (! array_key_exists('platformFlow', $view->getData())) {
                $view->with('platformFlow', request()->hasSession() ? app(PlatformContext::class)->enteredFlowToken() : null);
            }
        });

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

        // R-59 Phase 1B: the moment an address is confirmed is a step of its own in the funnel.
        Event::listen(Verified::class, function (Verified $e): void {
            if ($e->user instanceof User) {
                app(OnboardingEvents::class)->record($e->user, 'email_verified');
            }
        });

        // R-62 phase 6: a completed sign-in is a step, and the day it happened is kept so that "came back" can be counted. Recording never stops a sign-in.
        Event::listen(Login::class, function (Login $e): void {
            if (! $e->user instanceof User) {
                return;
            }
            try {
                DB::table('user_login_days')->insertOrIgnore(['user_id' => $e->user->getAuthIdentifier(), 'day' => now()->toDateString()]);
                app(OnboardingEvents::class)->record($e->user, 'login_completed');
            } catch (\Throwable $x) {
                report($x);
            }

            // R-72 (S-06): a sign-in from a browser this account has not used before is told to the person. Never stops a sign-in.
            try {
                if (($device = app(SignInDevices::class)->record($e->user, request())) !== null) {
                    app(AccountMail::class)->newSignIn($e->user, $device);
                }
            } catch (\Throwable $x) {
                report($x);
            }
        });

        // R-62 phase 6: someone who joined a company by accepting an invitation or using a link.
        Event::listen(TeamMemberAdded::class, function (TeamMemberAdded $e): void {
            if ($e->user instanceof User && $e->team instanceof Team) {
                app(OnboardingEvents::class)->record($e->user, 'company_joined', $e->team);
            }
        });

        // R-64 phase 7: account protection and company membership, told to the person they are about.
        Event::listen(TwoFactorAuthenticationEnabled::class, fn ($e) => $e->user instanceof User && app(AccountMail::class)->twoFactor($e->user, true));
        Event::listen(TwoFactorAuthenticationDisabled::class, fn ($e) => $e->user instanceof User && app(AccountMail::class)->twoFactor($e->user, false));
        Event::listen(TeamMemberAdded::class, function (TeamMemberAdded $e): void {
            if ($e->user instanceof User && $e->team instanceof Team) {
                app(AccountMail::class)->membership($e->user, $e->team, true, auth()->user());
            }
        });
        Event::listen(TeamMemberRemoved::class, function ($e): void {
            if ($e->user instanceof User && $e->team instanceof Team) {
                app(AccountMail::class)->membership($e->user, $e->team, false, auth()->user());
            }
        });
        // R-72 (E-04): a changed role, and a welcome once the address is confirmed.
        Event::listen(TeamMemberUpdated::class, function (TeamMemberUpdated $e): void {
            if ($e->user instanceof User && $e->team instanceof Team) {
                $role = DB::table('team_user')->where('team_id', $e->team->getKey())->where('user_id', $e->user->getKey())->value('role');
                $name = is_string($role) ? (Jetstream::findRole($role)->name ?? $role) : null;
                if ($name !== null) {
                    app(AccountMail::class)->roleChanged($e->user, $e->team, $name, auth()->user());
                }
            }
        });
        Event::listen(Verified::class, fn (Verified $e) => $e->user instanceof User && app(AccountMail::class)->welcome($e->user));

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
        // R-50: the platforms read has its own bucket, so sign-in redemption and entitlement checks can never starve each other. A platform
        // that is refused (429) carries on and treats the answer as unknown, so sharing the redeem bucket could silently pause enforcement.
        // The bucket belongs to the calling platform once its secret has been proved here (the same check the endpoint makes), never to
        // the address alone: platforms often share a host, and one platform's burst must not slow another's. Anything that does not prove
        // itself falls back to a small bucket per address, so guessing is throttled and cannot spend a real platform's allowance.
        RateLimiter::for('platform-companies', function (Request $request) {
            $caller = Input::text($request->query('platform')) ?: Input::text($request->header('X-Dot-Platform'));

            return $caller !== '' && strlen($caller) <= 40 && PlatformSecret::proves($request, $caller)
                ? Limit::perMinute((int) config('ecosystem.companies_read_per_minute', 600))->by('platform:'.$caller)
                : Limit::perMinute((int) config('ecosystem.companies_read_unproven_per_minute', 30))->by('unproven:'.$request->ip());
        });
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

<?php

namespace App\Services\Notifications;

use App\Mail\MembershipChanged;
use App\Mail\SecurityNotice;
use App\Models\Team;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The emails about a person's own account and company membership (R-64 phase 7). Each one is sent to the person it is about, once, from a real event; a
 * failure to send is logged and never fails the thing the person was doing. Security messages go to the account's address whether or not it is
 * confirmed; membership messages only to a confirmed one.
 */
class AccountMail
{
    public function passwordChanged(User $user): void
    {
        $this->deliver($user, new SecurityNotice(SecurityNotice::PASSWORD_CHANGED, $user, $this->now()), 'sec:pw:'.$user->getKey(), 300);
    }

    public function twoFactor(User $user, bool $enabled): void
    {
        $this->deliver($user, new SecurityNotice($enabled ? SecurityNotice::TWO_FACTOR_ON : SecurityNotice::TWO_FACTOR_OFF, $user, $this->now()), 'sec:2fa:'.($enabled ? 'on' : 'off').':'.$user->getKey(), 300);
    }

    /** `$actor` is whoever made the change; someone who joined or left by their own hand is not mailed about it. */
    public function membership(User $user, Team $team, bool $added, ?User $actor): void
    {
        if ($user->email_verified_at === null || ($actor !== null && $actor->is($user)) || $team->personal_team) {
            return;
        }

        $this->deliver($user, new MembershipChanged($added ? MembershipChanged::ADDED : MembershipChanged::REMOVED, $user, MembershipChanged::plainName($team)), 'member:'.($added ? 'add' : 'rm').':'.$team->getKey().':'.$user->getKey(), 86400);
    }

    /**
     * One message of a kind per person (and company) in a window, so repeating an action over and over cannot be used to fill someone's inbox: a
     * password changed five times in five minutes is one message, a person added, removed and added again is one message that day.
     */
    private function deliver(User $user, Mailable $mail, string $throttleKey, int $seconds): void
    {
        if (! Cache::add('account-mail:'.$throttleKey, 1, $seconds)) {
            return;
        }

        try {
            Mail::to($user->email)->send($mail);
        } catch (\Throwable $e) {
            Log::warning('account.mail_failed', ['mail' => $mail::class, 'exception' => $e::class]);
        }
    }

    private function now(): string
    {
        return now(config('app.timezone'))->format('j F Y, H:i').' ('.config('app.timezone').')';
    }
}

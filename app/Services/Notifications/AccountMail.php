<?php

namespace App\Services\Notifications;

use App\Mail\AccountClosed;
use App\Mail\MembershipChanged;
use App\Mail\SecurityNotice;
use App\Mail\WelcomeMail;
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

    /** R-72 (S-06): a sign-in from a browser this account has not used before. One per device label per day. */
    public function newSignIn(User $user, string $device): void
    {
        $this->deliver($user, new SecurityNotice(SecurityNotice::NEW_SIGN_IN, $user, $this->now(), $device), 'sec:signin:'.$user->getKey().':'.sha1($device), 86400);
    }

    /** R-72 (E-04): InfoDot suspended the account, or opened it again. Always sent: the person must know why they cannot sign in. */
    public function suspension(User $user, bool $suspended, ?string $reason): void
    {
        $this->deliver($user, new SecurityNotice($suspended ? SecurityNotice::SUSPENDED : SecurityNotice::RESTORED, $user, $this->now(), (string) $reason), 'sec:susp:'.($suspended ? 'on' : 'off').':'.$user->getKey(), 300);
    }

    /** R-72 (E-04): someone else changed the person's role in a company. */
    public function roleChanged(User $user, Team $team, string $roleName, ?User $actor): void
    {
        if ($user->email_verified_at === null || ($actor !== null && $actor->is($user)) || $team->personal_team) {
            return;
        }

        $this->deliver($user, new MembershipChanged(MembershipChanged::ROLE_CHANGED, $user, MembershipChanged::plainName($team), $roleName), 'member:role:'.$team->getKey().':'.$user->getKey().':'.sha1($roleName), 3600);
    }

    /**
     * R-72 (E-04): a company was deleted; its other members hear it from us, not from a missing page.
     *
     * @param  iterable<User>  $members
     */
    public function companyDeleted(iterable $members, string $companyName, ?User $actor): void
    {
        foreach ($members as $member) {
            if ($member->email_verified_at === null || ($actor !== null && $actor->is($member))) {
                continue;
            }
            $this->deliver($member, new MembershipChanged(MembershipChanged::COMPANY_DELETED, $member, $companyName), 'member:gone:'.sha1($companyName).':'.$member->getKey(), 86400);
        }
    }

    /** R-72 (E-04): the account was deleted. Plain strings only, because the account is gone by the time this is sent. */
    public function accountClosed(string $email, string $name): void
    {
        if ($email === '') {
            return;
        }

        try {
            Mail::to($email)->send(new AccountClosed($name, $this->now()));
        } catch (\Throwable $e) {
            Log::error('account.mail_failed', ['category' => 'mail', 'mail' => AccountClosed::class, 'exception' => $e::class]);
        }
    }

    /** R-72 (E-04): once, when the person confirms their address. */
    public function welcome(User $user): void
    {
        $this->deliver($user, new WelcomeMail($user), 'welcome:'.$user->getKey(), 400 * 86400);
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
            // R-72 (E-09): at error level, so the ops alert counts it; the class only, never the message (it can hold the address).
            Log::error('account.mail_failed', ['category' => 'mail', 'mail' => $mail::class, 'exception' => $e::class]);
        }
    }

    private function now(): string
    {
        return now(config('app.display_timezone'))->format('j F Y, H:i').' (South African time)';
    }
}

<?php

namespace App\Services\Accounts;

use App\Models\AccountSuspensionEvent;
use App\Models\SsoPlatformSession;
use App\Models\User;
use App\Services\Demo\DemoRegistry;
use App\Services\Sso\BackchannelLogoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Token;

/**
 * Switches one account off, or back on, for the whole ecosystem. InfoDot is where every Dot platform signs people in,
 * so suspending here has to end everything the account already holds in one place: the mark on the user, the line in
 * the trail, the open browser sessions, the API tokens, the provider tokens and the platforms' own sessions (through
 * the same back-channel logout a central sign-out uses). What stops the person coming back in is checked at each
 * door by User::isSuspended().
 */
class AccountSuspension
{
    public const MESSAGE = 'This account is suspended. Contact InfoDot support.';

    public function __construct(private BackchannelLogoutService $backchannel) {}

    /**
     * Safe to run again on an account that is already suspended: the clean-up is repeated, the first date and reason
     * are kept, and no second line is written to the trail.
     *
     * @return array{sessions: int, api_tokens: int, provider_tokens: int, platforms: int} what was ended
     */
    public function suspend(User $user, ?string $reason = null, ?User $actor = null): array
    {
        $reason = $this->clean($reason);

        DB::transaction(function () use ($user, $reason, $actor) {
            $already = $user->isSuspended();

            $user->forceFill([
                'suspended_at' => $user->suspended_at ?? now(),
                'suspended_reason' => $already ? $user->suspended_reason : $reason,
                // A "remember me" cookie must not sign the browser back in.
                'remember_token' => Str::random(60),
            ])->save();

            if (! $already) {
                $this->record($user, AccountSuspensionEvent::SUSPENDED, $reason, $actor);
            }
        });

        $summary = [
            'sessions' => $this->endBrowserSessions($user),
            'api_tokens' => $user->tokens()->delete(),
            'provider_tokens' => Token::where('user_id', $user->id)->where('revoked', false)->count(),
            'platforms' => SsoPlatformSession::where('user_id', $user->id)->count(),
        ];

        // Tells every platform the person is signed in to, revokes the provider tokens and clears the session registry.
        $this->backchannel->propagate($user);

        return $summary;
    }

    /**
     * Why this moderator may not suspend or restore this account from the accounts desk, or null when they may. The
     * desk shows the sentence and every action checks it again, so the rule holds whatever the browser sends.
     *  - Nobody suspends themselves: the account that made a mistake could not undo it.
     *  - A moderator does not suspend a moderator: that is the owner's decision, made from the command line, so a
     *    disagreement between two moderators cannot end with one locking the other out.
     *  - A demonstration moderator (R-49) acts on demonstration accounts only.
     */
    public function whyNot(User $moderator, User $account): ?string
    {
        if ($moderator->is($account)) {
            return 'This is your own account. Nobody can suspend or restore themselves.';
        }

        if ($account->isModerator()) {
            return $account->name.' is a moderator. One moderator cannot suspend or restore another: that decision is the owner\'s, made with the account:suspend command.';
        }

        $demo = app(DemoRegistry::class);
        if ($demo->isDemoUser($moderator) && ! $demo->isDemoUser($account)) {
            return 'You are signed in as the demonstration moderator, which can act on demonstration accounts only.';
        }

        return null;
    }

    /** Lets the person sign in again. Nothing that was ended is brought back: they start with a fresh sign-in. */
    public function restore(User $user, ?string $reason = null, ?User $actor = null): void
    {
        if (! $user->isSuspended()) {
            return;
        }

        DB::transaction(function () use ($user, $reason, $actor) {
            $user->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();

            $this->record($user, AccountSuspensionEvent::RESTORED, $this->clean($reason), $actor);
        });
    }

    /** Production keeps sessions in the database; with any other driver there is no table and nothing to delete here. */
    private function endBrowserSessions(User $user): int
    {
        $connection = config('session.connection');
        $table = (string) config('session.table', 'sessions');

        if (! Schema::connection($connection)->hasTable($table)) {
            return 0;
        }

        return DB::connection($connection)->table($table)->where('user_id', $user->id)->delete();
    }

    private function record(User $user, string $action, ?string $reason, ?User $actor): void
    {
        AccountSuspensionEvent::create([
            'user_id' => $user->id,
            'action' => $action,
            'reason' => $reason,
            'actor_id' => $actor?->id,
        ]);
    }

    private function clean(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : Str::limit($reason, 255, '');
    }
}

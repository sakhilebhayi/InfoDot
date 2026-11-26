<?php

namespace App\Jobs;

use App\Mail\NoticeMail;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * R-72 (E-03): emails a security or business notice that has no message of its own, as it happens. Security notices always go; business
 * notices go when the person's settings say "as it happens" (the default). Everything is checked again when the job runs: the notice
 * must still exist, the address must be confirmed, and the words come from describe(), which shows nothing the person may not read.
 * Reviewer queues ("a business is waiting for review") are held to one email per person per kind in half an hour.
 */
class SendNoticeEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Kinds that tell a reviewer their queue grew: useful once, noise when many arrive together. */
    public const QUEUE_KINDS = [UserNotice::VERIFICATION_QUEUE, UserNotice::BUSINESS_TRUST_QUEUE, UserNotice::IDENTITY_CONFLICT];

    public int $tries = 3;

    public function __construct(public readonly int $noticeId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    /** Whether a notice of this kind is emailed as it happens by this job (kinds with their own mail are not). */
    public static function covers(string $kind): bool
    {
        $category = UserNotice::categoryOf($kind);

        return $category === 'security'
            || ($category === 'business' && ! in_array($kind, [UserNotice::ENQUIRY_RECEIVED, UserNotice::ENQUIRY_REPLY, UserNotice::ENQUIRY_ASSIGNED, UserNotice::ENQUIRY_FOLLOW_UP], true));
    }

    public function handle(InAppNotices $notices, NotificationPreferences $preferences): void
    {
        $notice = UserNotice::with('user')->find($this->noticeId);
        $user = $notice?->user;

        if ($notice === null || $user === null || $user->email_verified_at === null) {
            return;
        }

        $security = UserNotice::categoryOf($notice->kind) === 'security';

        if (! $security && ! $preferences->instantEmail($user, $notice->kind)) {
            return;
        }

        $throttle = in_array($notice->kind, self::QUEUE_KINDS, true) ? 'notice-mail:'.$user->id.':'.$notice->kind : null;
        if ($throttle !== null && ! Cache::add($throttle, 1, now()->addMinutes(30))) {
            return;
        }

        $described = $notices->describe($notice);
        if (($described['gone'] ?? false) || trim((string) ($described['text'] ?? '')) === '') {
            return;
        }

        try {
            Mail::to($user)->send(new NoticeMail($user, (string) $described['text'], $described['url'] ?? null, $security));
        } catch (\Throwable $e) {
            // R-72 (R72-9, N-2): a send that failed did not use up the half hour, or the retry would find it taken and send nothing.
            if ($throttle !== null) {
                Cache::forget($throttle);
            }

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        // R-72 (E-09): at error level, so the ops alert counts it; the class only, never the message (it can hold the address).
        Log::error('notice.mail_failed', ['category' => 'mail', 'notice_id' => $this->noticeId, 'exception' => $e::class]);
    }
}

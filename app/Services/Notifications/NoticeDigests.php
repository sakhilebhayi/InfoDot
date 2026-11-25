<?php

namespace App\Services\Notifications;

use App\Mail\NoticeDigest;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the daily summary (R-64 phase 8). One message per person per run at most; nothing when there is nothing unread and new in the categories they
 * chose to get as a summary; only to a confirmed address; and only notices from after the last summary (and never older than a few days), so switching the
 * feature on never sends a backlog. The person's own choices are read fresh for each send.
 */
class NoticeDigests
{
    public function __construct(private readonly NotificationPreferences $preferences, private readonly InAppNotices $notices) {}

    /** @return int how many summaries were sent */
    public function run(): int
    {
        if (! config('notifications.digest.enabled')) {
            return 0;
        }

        $sent = 0;

        User::query()->whereNotNull('email_verified_at')->whereIn('id', UserNotice::query()->whereNull('read_at')->whereNull('archived_at')->select('user_id')->distinct())
            ->chunkById(200, function ($users) use (&$sent) {
                foreach ($users as $user) {
                    $sent += $this->sendTo($user) ? 1 : 0;
                }
            });

        return $sent;
    }

    public function sendTo(User $user): bool
    {
        $categories = $this->preferences->digestCategories($user);

        if ($categories === [] || $user->email_verified_at === null) {
            return false;
        }

        $since = max(
            $user->notices_digest_sent_at ?? now()->subDay(),
            now()->subDays((int) config('notifications.digest.max_age_days')),
        );

        // A reminder is only useful at the moment: in a summary hours later it would be stale, so reminders are left to the bell and the instant mail.
        $reminders = [UserNotice::BOOKING_REMINDER, UserNotice::SEMINAR_REMINDER];
        $kinds = array_values(array_filter(UserNotice::KINDS, fn (string $k) => in_array(UserNotice::categoryOf($k), $categories, true) && ! in_array($k, $reminders, true)));

        $rows = UserNotice::where('user_id', $user->getKey())->whereIn('kind', $kinds)->whereNull('read_at')->whereNull('archived_at')
            ->where('created_at', '>', $since)->orderByDesc('id')->limit(200)->get();

        if ($rows->isEmpty()) {
            return false;
        }

        // The person is not signed in here: each notice is described for them, from what they may see.
        $rows->each(fn (UserNotice $n) => $n->setRelation('user', $user));
        $this->notices->preload($rows);

        // Anything the person can no longer see (a hidden question, a company they left) is left out, and not counted.
        $described = $rows->map(fn (UserNotice $n) => ['notice' => $n] + $this->notices->describe($n))->reject(fn (array $d) => ($d['gone'] ?? false) === true)->values();

        if ($described->isEmpty()) {
            return false;
        }

        $limit = (int) config('notifications.digest.items_per_category');
        $sections = [];

        foreach ($described->groupBy(fn (array $d) => UserNotice::categoryOf($d['notice']->kind)) as $category => $group) {
            $sections[] = [
                'name' => NotificationPreferences::categories()[$category]['name'],
                'total' => $group->count(),
                'items' => $group->take($limit)->map(fn (array $d) => $d['text'])->values()->all(),
            ];
        }

        // Claim the send before it leaves, so two overlapping runs cannot both send one.
        if (! Cache::add('notice-digest:'.$user->getKey().':'.now()->format('Y-m-d'), 1, now()->addDay())) {
            return false;
        }

        try {
            Mail::to($user->email)->send(new NoticeDigest($user, $sections, $described->count()));
        } catch (\Throwable $e) {
            Log::warning('notices.digest_failed', ['exception' => $e::class]);

            return false;
        }

        // The next summary starts after the newest notice this one covered, so nothing created while it was being prepared is skipped.
        User::whereKey($user->getKey())->update(['notices_digest_sent_at' => $rows->max('created_at')]);

        return true;
    }
}

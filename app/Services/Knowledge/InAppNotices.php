<?php

namespace App\Services\Knowledge;

use App\Models\Questions;
use App\Models\Seminar;
use App\Models\Solutions;
use App\Models\User;
use App\Models\UserNotice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * In-app notices for contributors. `record()` is the only writer; it is called from a listener that can never fail the
 * request that raised the event. Rules, so nobody is nagged and nothing private leaks:
 *  - only the person the event is about is told, never the person who did it (nobody is notified of their own act);
 *  - nothing is recorded for a person who switched notices off;
 *  - the same fact is one notice however often the event is raised (unique per person, kind and reference);
 *  - a notice holds no words; the page writes them from what the viewer may see, so a question that was hidden or made
 *    private afterwards shows as "no longer available", not as its title.
 */
class InAppNotices
{
    /** A recipient who no longer exists (their account was deleted) is simply not told. */
    public function record(string $kind, ?User $recipient, string $subjectType, int $subjectId, string $ref, ?User $actor = null): bool
    {
        if ($recipient === null || ! in_array($kind, UserNotice::KINDS, true) || (! $recipient->wantsInAppNotices() && ! in_array($kind, UserNotice::SERVICE_KINDS, true))) {
            return false;
        }

        if ($actor !== null && $actor->is($recipient)) {
            return false;
        }

        try {
            UserNotice::create([
                'user_id' => $recipient->id, 'kind' => $kind, 'subject_type' => $subjectType,
                'subject_id' => $subjectId, 'ref' => mb_substr($ref, 0, 96), 'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    public function unreadCount(User $user): int
    {
        return UserNotice::where('user_id', $user->id)->whereNull('read_at')->count();
    }

    /** @return LengthAwarePaginator<UserNotice> */
    public function page(User $user, int $perPage = 30)
    {
        return UserNotice::where('user_id', $user->id)->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);
    }

    public function markRead(User $user, int $noticeId): ?UserNotice
    {
        $notice = UserNotice::where('user_id', $user->id)->whereKey($noticeId)->first();

        if ($notice !== null && $notice->read_at === null) {
            $notice->forceFill(['read_at' => now()])->save();
        }

        return $notice;
    }

    public function markAllRead(User $user): int
    {
        return UserNotice::where('user_id', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    /** Read notices older than $readDays and any notice older than $anyDays go. @return int rows removed */
    public function prune(int $readDays = 90, int $anyDays = 365): int
    {
        return UserNotice::where(fn ($q) => $q->whereNotNull('read_at')->where('created_at', '<', now()->subDays($readDays)))
            ->orWhere('created_at', '<', now()->subDays($anyDays))->delete();
    }

    /**
     * What to show for one notice, from what the signed-in person may see now. `url` is null when the item cannot be opened.
     *
     * @return array{text: string, url: ?string}
     */
    public function describe(UserNotice $notice): array
    {
        $gone = ['text' => $this->gone($notice->kind), 'url' => null];

        if ($notice->subject_type === 'question') {
            $q = Questions::find($notice->subject_id);

            return $q === null || $q->isHidden() ? $gone : ['text' => $this->words($notice->kind, (string) $q->question), 'url' => route('questions.view', ['qid' => $q->id])];
        }

        if ($notice->subject_type === 'solution') {
            $s = Solutions::find($notice->subject_id);

            return $s === null || $s->isHidden() ? $gone : ['text' => $this->words($notice->kind, (string) $s->solution_title), 'url' => route('solutions.view', ['id' => $s->id])];
        }

        if ($notice->subject_type === 'seminar') {
            $sem = Seminar::find($notice->subject_id);

            if ($sem === null || $sem->status === Seminar::DRAFT) {
                return $gone;
            }

            return ['text' => $this->words($notice->kind, (string) $sem->title, $sem).($notice->kind === UserNotice::SEMINAR_REMINDER ? ' Starts '.$sem->starts_at->timezone(config('app.timezone'))->format('j M, H:i').'.' : ''), 'url' => route('seminars.show', ['seminar' => $sem->id])];
        }

        return $gone;
    }

    /** The short plate shown beside a notice: what kind of thing happened. */
    public static function label(string $kind): string
    {
        return match ($kind) {
            UserNotice::ANSWER_RECEIVED => 'Answer',
            UserNotice::COMMENT_RECEIVED => 'Comment',
            UserNotice::ANSWER_ACCEPTED => 'Accepted',
            UserNotice::SOLUTION_VERIFIED => 'Verified',
            UserNotice::KNOWLEDGE_REUSED => 'Library',
            UserNotice::SEMINAR_REMINDER, UserNotice::SEMINAR_CHANGED, UserNotice::SEMINAR_CANCELLED, UserNotice::SEMINAR_SPOT_OPEN, UserNotice::SEMINAR_REFUNDED, UserNotice::SEMINAR_DISPUTE, UserNotice::SEMINAR_LINK_BROKEN, UserNotice::SEMINAR_DEBT_OPENED, UserNotice::SEMINAR_DEBT_CLEARED, UserNotice::SEMINAR_DEBT_REOPENED, UserNotice::SEMINAR_DEBT_WRITTEN_OFF, UserNotice::SEMINAR_REMOVED, UserNotice::SEMINAR_REINSTATED, UserNotice::SEMINAR_APPEAL_DECIDED, UserNotice::SEMINAR_REPORT_DECIDED => 'Seminar',
            default => 'Update',
        };
    }

    private function words(string $kind, string $title, ?Seminar $seminar = null): string
    {
        $title = '“'.Str::limit(trim($title), 90, '…', true).'”';

        return match ($kind) {
            UserNotice::ANSWER_RECEIVED => "Someone answered your question $title.",
            UserNotice::ANSWER_ACCEPTED => "Your answer to $title was accepted as the solution.",
            UserNotice::SOLUTION_VERIFIED => "Your solution $title was verified by a moderator.",
            UserNotice::KNOWLEDGE_REUSED => "Your work on $title was added to the knowledge library.",
            UserNotice::COMMENT_RECEIVED => "Someone commented on $title. They may be asking for more detail.",
            UserNotice::SEMINAR_REMINDER => "Reminder: you registered for the seminar $title.",
            UserNotice::SEMINAR_CANCELLED => $seminar?->removed_at
                ? "The seminar $title was removed by a moderator. If you paid for a ticket and you are due a refund, we will tell you when it is made."
                : "The seminar $title was cancelled by its host. If you paid for a ticket, it is being refunded in full.",
            UserNotice::SEMINAR_CHANGED => "The host changed the time of the seminar $title.",
            UserNotice::SEMINAR_SPOT_OPEN => "A place opened and you are now registered for the seminar $title.",
            UserNotice::SEMINAR_REFUNDED => "Your ticket for the seminar $title was refunded. It can take a few days to show on your statement.",
            UserNotice::SEMINAR_DISPUTE => "A ticket for your seminar $title was disputed with the card issuer. We will tell you the outcome.",
            UserNotice::SEMINAR_DEBT_OPENED => "A chargeback on a ticket for your seminar $title was lost, so you now owe InfoDot an amount. It is taken only from your share of future tickets, never more than half of your share of any ticket. See what you owe on the page where you set up payouts.",
            UserNotice::SEMINAR_DEBT_CLEARED => "What you owed InfoDot after a lost chargeback on your seminar $title has been paid off. Nothing more is taken.",
            UserNotice::SEMINAR_DEBT_REOPENED => "A refund on a ticket meant part of what was taken for a lost chargeback (seminar $title) was given back, so some of it is owed again.",
            UserNotice::SEMINAR_DEBT_WRITTEN_OFF => "InfoDot wrote off what was still owed after a lost chargeback on your seminar $title. Nothing more is taken for it.",
            UserNotice::SEMINAR_REMOVED => "A moderator removed your seminar $title. ".match ($seminar?->removed_refunds) {
                'complainants' => 'Tickets are being refunded to the people who reported it.',
                'none' => 'The seminar had already taken place, so tickets were not refunded.',
                default => 'Anyone who paid is being refunded in full.',
            }.' If you think this was a mistake, you can appeal once on the seminar page.',
            UserNotice::SEMINAR_REINSTATED => "The seminar $title was removed but is back on. You are still registered if you were before. Tickets that were already refunded stay refunded.",
            UserNotice::SEMINAR_REPORT_DECIDED => "A moderator looked at your report about the seminar $title. ".($seminar?->removed_at ? 'The seminar was removed.' : 'No action was taken.'),
            UserNotice::SEMINAR_APPEAL_DECIDED => "A different moderator looked at your appeal about the seminar $title. The answer and its reason are on the seminar page.",
            UserNotice::SEMINAR_LINK_BROKEN => "Someone could not open the meeting link for your seminar $title. Please check it and fix it before the start.",
            default => 'Something happened to your work.',
        };
    }

    private function gone(string $kind): string
    {
        return match ($kind) {
            UserNotice::ANSWER_RECEIVED => 'Someone answered a question of yours that is no longer available.',
            UserNotice::ANSWER_ACCEPTED => 'One of your answers was accepted, on a question that is no longer available.',
            UserNotice::SOLUTION_VERIFIED => 'A solution of yours was verified; it is no longer available.',
            UserNotice::KNOWLEDGE_REUSED => 'Some of your work was added to the knowledge library; it is no longer available.',
            UserNotice::COMMENT_RECEIVED => 'Someone commented on something of yours that is no longer available.',
            UserNotice::SEMINAR_REMINDER, UserNotice::SEMINAR_CHANGED, UserNotice::SEMINAR_CANCELLED, UserNotice::SEMINAR_SPOT_OPEN, UserNotice::SEMINAR_REFUNDED, UserNotice::SEMINAR_DISPUTE, UserNotice::SEMINAR_LINK_BROKEN, UserNotice::SEMINAR_DEBT_OPENED, UserNotice::SEMINAR_DEBT_CLEARED, UserNotice::SEMINAR_DEBT_REOPENED, UserNotice::SEMINAR_DEBT_WRITTEN_OFF, UserNotice::SEMINAR_REMOVED, UserNotice::SEMINAR_REINSTATED, UserNotice::SEMINAR_APPEAL_DECIDED, UserNotice::SEMINAR_REPORT_DECIDED => 'A seminar notice that is no longer available.',
            default => 'Something happened to your work that is no longer available.',
        };
    }
}

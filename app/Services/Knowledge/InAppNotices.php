<?php

namespace App\Services\Knowledge;

use App\Models\Enquiry;
use App\Models\Questions;
use App\Models\Seminar;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
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
    /** @var array<string, array<int, Model|null>> subject type => id => the item (null: not visible or gone), filled by preload() */
    private array $preloaded = [];

    /**
     * One query per kind of subject for a whole page of notices, instead of one per notice. The same scoped queries as the single lookups, so
     * what a person may not see is still reported as gone.
     *
     * @param  iterable<UserNotice>  $notices
     */
    public function preload(iterable $notices): void
    {
        $ids = [];

        foreach ($notices as $n) {
            $ids[$n->subject_type][] = $n->subject_id;
        }

        $models = ['question' => Questions::class, 'solution' => Solutions::class, 'seminar' => Seminar::class];

        foreach ($ids as $type => $list) {
            if (! isset($models[$type])) {
                continue;
            }

            $found = $models[$type]::whereIn('id', array_unique($list))->get()->keyBy('id');

            foreach (array_unique($list) as $id) {
                $this->preloaded[$type][$id] = $found->get($id);
            }
        }
    }

    private function subject(string $type, int $id): ?Model
    {
        if (isset($this->preloaded[$type]) && array_key_exists($id, $this->preloaded[$type])) {
            return $this->preloaded[$type][$id];
        }

        return match ($type) {
            'question' => Questions::find($id),
            'solution' => Solutions::find($id),
            'seminar' => Seminar::find($id),
            default => null,
        };
    }

    public function describe(UserNotice $notice): array
    {
        $gone = ['text' => $this->gone($notice->kind), 'url' => null];

        if ($notice->subject_type === 'question') {
            $q = $this->subject('question', $notice->subject_id);

            return $q === null || $q->isHidden() ? $gone : ['text' => $this->words($notice->kind, (string) $q->question), 'url' => route('questions.view', ['qid' => $q->id])];
        }

        if ($notice->subject_type === 'solution') {
            $s = $this->subject('solution', $notice->subject_id);

            return $s === null || $s->isHidden() ? $gone : ['text' => $this->words($notice->kind, (string) $s->solution_title), 'url' => route('solutions.view', ['id' => $s->id])];
        }

        if ($notice->subject_type === 'seminar') {
            $sem = $this->subject('seminar', $notice->subject_id);

            if ($sem === null || $sem->status === Seminar::DRAFT) {
                return $gone;
            }

            return ['text' => $this->words($notice->kind, (string) $sem->title, $sem).($notice->kind === UserNotice::SEMINAR_REMINDER ? ' Starts '.$sem->starts_at->timezone(config('app.timezone'))->format('j M, H:i').'.' : ''), 'url' => route('seminars.show', ['seminar' => $sem->id])];
        }

        if ($notice->subject_type === 'business') {
            $team = Team::find($notice->subject_id);

            return $team === null ? $gone : $this->business($notice, $team);
        }

        if ($notice->subject_type === 'enquiry') {
            return $this->enquiry($notice) ?? $gone;
        }

        return $gone;
    }

    /**
     * Verification notices (R-29). The status at the time is carried in the reference
     * ("bv:{id}:{status}:…"), so an old notice keeps saying what happened then.
     *
     * @return array{text: string, url: ?string}
     */
    private function business(UserNotice $notice, Team $team): array
    {
        [, $id, $status] = array_pad(explode(':', $notice->ref), 3, null);
        $name = '“'.Str::limit($team->name, 60, '…', true).'”';

        // R-33 Phase 4a. Reviewers see what is waiting; the reporter learns only whether action was
        // taken; the business is pointed at its standing page, where the explanation is.
        if ($notice->kind === UserNotice::BUSINESS_TRUST_QUEUE) {
            [, $what, $itemId] = array_pad(explode(':', $notice->ref), 3, null);

            return match ($what) {
                'appeal' => ['text' => "$name appealed a decision about it. It needs a reviewer who did not make that decision.", 'url' => route('moderation.trust.appeal', ['appeal' => (int) $itemId])],
                'feature' => ['text' => "$name asked to be featured in the directory.", 'url' => route('moderation.trust').'#featured'],
                default => ['text' => "Someone reported $name. It needs a reviewer.", 'url' => route('moderation.trust.report', ['report' => (int) $itemId])],
            };
        }

        if ($notice->kind === UserNotice::BUSINESS_REPORT_DECIDED) {
            [, , $outcome] = array_pad(explode(':', $notice->ref), 3, null);

            return ['text' => "Your report about $name was reviewed. ".($outcome === 'actioned' ? 'InfoDot acted on it.' : 'InfoDot found no problem it could act on.').' Thank you for raising it.', 'url' => null];
        }

        if ($notice->kind === UserNotice::BUSINESS_STANDING) {
            $parts = explode(':', $notice->ref);
            if (($parts[1] ?? '') === 'feature') {
                $text = match ($parts[3] ?? '') {
                    'active' => "$name is featured in the directory. The dates are on its profile page.",
                    'declined' => "The request to feature $name was declined. The reason is on its profile page.",
                    default => "{$name}’s featured placement has ended.",
                };

                return ['text' => $text, 'url' => route('business.edit', $team).'#featured'];
            }

            $text = match (true) {
                ($parts[1] ?? '') === 'appeal' => 'A reviewer decided the appeal for '.$name.': '.(($parts[3] ?? '') === 'upheld' ? 'upheld.' : 'not upheld.'),
                ($parts[3] ?? '') === 'restricted' => "InfoDot is reviewing $name and has taken it out of the directory meanwhile. The reason, and how to respond, are on its standing page.",
                ($parts[3] ?? '') === 'concluded' => "InfoDot finished reviewing $name. The outcome is on its standing page.",
                ($parts[3] ?? '') === 'lifted' => "$name is back in the directory.",
                default => "InfoDot wrote to $name about a review. Read it on its standing page.",
            };

            return ['text' => $text, 'url' => route('business.standing', $team)];
        }

        // R-29 Part C. The other account is never named in the verified business's notice.
        if ($notice->kind === UserNotice::IDENTITY_CONFLICT) {
            return [
                'text' => "A business account shares identity details with verified $name ($status match). It needs a reviewer.",
                'url' => route('moderation.identity.show', ['conflict' => (int) $id]),
            ];
        }

        if ($notice->kind === UserNotice::IDENTITY_ALERT) {
            return [
                'text' => "Another account on InfoDot shares identity details with $name. InfoDot is looking into it; you can add what you know on the verification page.",
                'url' => route('business.verification', $team).'#identity',
            ];
        }

        if ($notice->kind === UserNotice::RELATIONSHIP_REQUEST) {
            return ['text' => "A business asked $name to confirm it is related (branch, franchise, representative…). Confirm or decline it on the verification page.", 'url' => route('business.verification', $team).'#linked'];
        }

        if ($notice->kind === UserNotice::RELATIONSHIP_DECIDED) {
            return ['text' => "{$name}’s request to be linked to a verified business was ".($status === 'confirmed' ? 'confirmed.' : 'declined.'), 'url' => route('business.verification', $team).'#linked'];
        }

        if ($notice->kind === UserNotice::VERIFICATION_QUEUE) {
            return [
                'text' => $status === 'under_review'
                    ? "$name changed identity details it was verified with. Its badge is off until a reviewer confirms the new details."
                    : "$name is waiting for a business verification review.",
                'url' => route('moderation.verifications.show', ['verification' => (int) $id]),
            ];
        }

        $text = $notice->kind === UserNotice::BUSINESS_VERIFICATION_RENEWAL
            ? "InfoDot verification of $name lapses soon. Renew it from the verification page so the badge stays."
            : match ($status) {
                'verified' => "InfoDot verified $name. The badge is on the business page.",
                'info_required' => "InfoDot needs more information to verify $name. What is needed is on the verification page.",
                'rejected' => "The verification application for $name was not approved. The reason is on the verification page.",
                'under_review' => "$name changed details it was verified with, so its badge is off until InfoDot confirms them.",
                'suspended' => "InfoDot suspended the verification of $name. The reason is on the verification page.",
                'revoked' => "InfoDot withdrew the verification of $name. The reason is on the verification page.",
                'expired' => "InfoDot verification of $name has lapsed. Apply again to renew it.",
                default => "There is news about the verification of $name.",
            };

        return ['text' => $text, 'url' => route('business.verification', $team)];
    }

    /**
     * Inbox notices (R-33). Written for whichever side the reader is on, and only while they may
     * still open the conversation; the message's words are never part of a notice.
     *
     * @return array{text: string, url: string}|null
     */
    private function enquiry(UserNotice $notice): ?array
    {
        $enquiry = Enquiry::with(['team', 'customer'])->find($notice->subject_id);
        $reader = $notice->user;

        if ($enquiry === null || $enquiry->team === null || $reader === null) {
            return null;
        }

        $subject = '“'.Str::limit($enquiry->subject, 70, '…', true).'”';
        $business = Str::limit($enquiry->team->name, 60, '…', true);

        if ($enquiry->isHandler($reader)) {
            $who = $enquiry->customer->name ?? 'A former InfoDot user';
            $text = match ($notice->kind) {
                UserNotice::ENQUIRY_RECEIVED => "New enquiry for $business from $who: $subject.",
                UserNotice::ENQUIRY_REPLY => "$who replied to $subject.",
                UserNotice::ENQUIRY_ASSIGNED => "The enquiry $subject for $business is now yours.",
                UserNotice::ENQUIRY_FOLLOW_UP => "Today is the follow-up date for $subject.",
                default => "There is news on $subject.",
            };

            return ['text' => $text, 'url' => route('business.inbox.show', [$enquiry->team, $enquiry])];
        }

        if ($enquiry->isCustomer($reader)) {
            return ['text' => "$business replied to your enquiry $subject.", 'url' => route('inbox.show', $enquiry)];
        }

        return null;
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
            UserNotice::SOLUTION_CONFIRMED => 'Tried it',
            UserNotice::MENTIONED => 'Mention',
            UserNotice::SOLUTION_RECOMMENDED => 'Recommended',
            UserNotice::IMPROVEMENT_PROPOSED, UserNotice::IMPROVEMENT_APPROVED, UserNotice::IMPROVEMENT_REJECTED, UserNotice::IMPROVEMENT_CLARIFY => 'Improvement',
            UserNotice::SEMINAR_REMINDER, UserNotice::SEMINAR_CHANGED, UserNotice::SEMINAR_CANCELLED, UserNotice::SEMINAR_SPOT_OPEN, UserNotice::SEMINAR_REFUNDED, UserNotice::SEMINAR_DISPUTE, UserNotice::SEMINAR_LINK_BROKEN, UserNotice::SEMINAR_DEBT_OPENED, UserNotice::SEMINAR_DEBT_CLEARED, UserNotice::SEMINAR_DEBT_REOPENED, UserNotice::SEMINAR_DEBT_WRITTEN_OFF, UserNotice::SEMINAR_REMOVED, UserNotice::SEMINAR_REINSTATED, UserNotice::SEMINAR_APPEAL_DECIDED, UserNotice::SEMINAR_REPORT_DECIDED => 'Seminar',
            UserNotice::BUSINESS_VERIFICATION, UserNotice::BUSINESS_VERIFICATION_RENEWAL, UserNotice::VERIFICATION_QUEUE => 'Verification',
            UserNotice::IDENTITY_CONFLICT, UserNotice::IDENTITY_ALERT => 'Identity',
            UserNotice::RELATIONSHIP_REQUEST, UserNotice::RELATIONSHIP_DECIDED => 'Linked business',
            UserNotice::ENQUIRY_RECEIVED, UserNotice::ENQUIRY_REPLY, UserNotice::ENQUIRY_ASSIGNED, UserNotice::ENQUIRY_FOLLOW_UP => 'Inbox',
            UserNotice::BUSINESS_TRUST_QUEUE, UserNotice::BUSINESS_REPORT_DECIDED, UserNotice::BUSINESS_STANDING => 'Business review',
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
            UserNotice::SOLUTION_CONFIRMED => "Someone tried your solution $title in practice and confirmed it worked for them.",
            UserNotice::MENTIONED => "You were mentioned on $title — someone thinks you have the experience to help.",
            UserNotice::SOLUTION_RECOMMENDED => "Your solution $title was recommended as an answer to a question.",
            UserNotice::IMPROVEMENT_PROPOSED => "Someone proposed an improvement to your solution $title. Review it on the solution page — nothing changes until you approve it.",
            UserNotice::IMPROVEMENT_APPROVED => "Your proposed improvement to $title was approved and is now part of the solution, credited to you.",
            UserNotice::IMPROVEMENT_REJECTED => "Your proposed improvement to $title was not taken up. The author's note, if any, is on the solution page.",
            UserNotice::IMPROVEMENT_CLARIFY => "The author of $title asked you to clarify your proposed improvement. Their question is on the solution page.",
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

<?php

namespace App\Services\Seminars;

use App\Models\ModerationAction;
use App\Models\Seminar;
use App\Models\SeminarFeedback;
use App\Models\SeminarHostAgreement;
use App\Models\SeminarPayoutAccount;
use App\Models\SeminarRegistration;
use App\Models\SeminarRemovalAppeal;
use App\Models\SeminarReport;
use App\Models\SeminarTicketEvent;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Free seminars: hosting, registering, joining, attending, rating. Rules in one place:
 *  - any verified person may host; no tier, reputation or seat is needed;
 *  - a host can have only a few upcoming published seminars (config), so the list cannot be flooded;
 *  - the join link is the host's own and is handed out only to registered people inside the window; opening it records attendance;
 *  - the last seat is taken under a row lock, so two people cannot both get it; the rest wait in order and are moved up when a place opens;
 *  - only people who attended can rate, once; the comment is for the host, and a host's average is shown publicly only from a few ratings.
 */
class SeminarService
{
    public function __construct(private readonly InAppNotices $notices, private readonly BillingMarketplace $billing) {}

    /** @return array<string, mixed> validation rules for the host's form */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:140'],
            'summary' => ['required', 'string', 'max:280'],
            'description' => ['nullable', 'string', 'max:5000'],
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:'.config('seminars.min_duration_minutes', 15), 'max:'.config('seminars.max_duration_minutes', 480)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:'.config('seminars.max_capacity', 5000)],
            'delivery' => ['nullable', 'in:link,room,p2p'],
            // Only looked at for a small room: a link or room seminar can carry empty or zero values for these without being refused.
            'p2p_quality' => ['exclude_unless:delivery,p2p', 'nullable', 'in:audio,low,medium'],
            'p2p_uplink_kbps' => ['exclude_unless:delivery,p2p', 'nullable', 'integer', 'min:50', 'max:1000000'],
            'join_url' => ['nullable', 'url:https', 'max:500'],
            'price' => ['nullable', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
        ];
    }

    /** @param  array<string, mixed>  $data  validated by rules() */
    public function save(User $host, ?Seminar $seminar, array $data, bool $publish): Seminar
    {
        $starts = Carbon::parse($data['starts_at'], config('app.timezone'))->utc();
        $this->assertLead($starts);

        return DB::transaction(function () use ($host, $seminar, $data, $publish, $starts) {
            User::whereKey($host->getKey())->lockForUpdate()->first();

            if ($seminar !== null && $seminar->status === Seminar::CANCELLED) {
                throw ValidationException::withMessages(['title' => 'A cancelled seminar cannot be edited.']);
            }

            $willBePublished = $publish || ($seminar?->isPublished() ?? false);

            if ($willBePublished && ! ($seminar?->isPublished() ?? false)) {
                $this->assertRoomToPublish($host);
            }

            $delivery = in_array($data['delivery'] ?? 'link', ['room', 'p2p'], true) ? $data['delivery'] : 'link';
            $p2pPlan = null;
            if ($delivery === 'p2p') {
                if (! config('seminars.p2p.enabled')) {
                    throw ValidationException::withMessages(['delivery' => 'Small rooms are not available yet. Use your own meeting link.']);
                }
                if (blank($data['p2p_quality'] ?? null) || blank($data['p2p_uplink_kbps'] ?? null)) {
                    throw ValidationException::withMessages(['p2p_uplink_kbps' => 'Test your connection first, so we can tell you how many people the room can hold.']);
                }
                $p2pPlan = P2pCapacity::plan((int) $data['p2p_uplink_kbps'], (string) $data['p2p_quality']);
                if (! $p2pPlan['usable']) {
                    throw ValidationException::withMessages(['p2p_quality' => 'Your connection is too slow for '.$p2pPlan['label'].'. Choose a lighter quality, such as audio only.']);
                }
            }
            if ($delivery === 'room' && ! config('seminars.video.enabled')) {
                throw ValidationException::withMessages(['delivery' => 'InfoDot rooms are not available yet. Use your own meeting link.']);
            }
            if ($delivery === 'link' && blank($data['join_url'] ?? null)) {
                throw ValidationException::withMessages(['join_url' => 'Add your meeting link, or choose an InfoDot room.']);
            }

            // A typed link is checked before it is accepted: a made-up or mistyped one is the commonest reason nobody can join.
            $linkCheck = null;
            if ($delivery === 'link' && ($seminar === null || $seminar->join_url !== ($data['join_url'] ?? null) || $seminar->join_url_status === null)) {
                $linkCheck = app(MeetingLinkCheck::class)->check((string) $data['join_url']);

                if ($linkCheck['status'] === 'bad') {
                    throw ValidationException::withMessages(['join_url' => $linkCheck['message']]);
                }
            }

            $priceCents = $this->priceFrom($host, $data['price'] ?? null);
            $this->assertPriceChangeAllowed($seminar, $priceCents);

            if ($seminar !== null && $seminar->join_url !== ($delivery === 'link' ? $data['join_url'] : null)) {
                DB::table('seminar_link_reports')->where('seminar_id', $seminar->id)->delete();   // the host fixed the link: start counting again
            }

            $before = $seminar?->starts_at;
            $attrs = [
                'price_cents' => $priceCents, 'currency' => $priceCents ? config('seminars.paid.currency', 'ZAR') : null,
                'title' => $data['title'], 'summary' => $data['summary'], 'description' => $data['description'] ?? null,
                'topic_id' => $data['topic_id'] ?? null, 'starts_at' => $starts, 'duration_minutes' => (int) $data['duration_minutes'],
                'capacity' => $p2pPlan ? min($p2pPlan['capacity'], (int) ($data['capacity'] ?? $p2pPlan['capacity'])) : (isset($data['capacity']) ? (int) $data['capacity'] : null),
                'join_url' => $delivery === 'link' ? $data['join_url'] : null, 'delivery' => $delivery,
                ...($linkCheck ? ['join_url_status' => $linkCheck['status'], 'join_url_checked_at' => now()] : ($delivery === 'link' ? [] : ['join_url_status' => null, 'join_url_checked_at' => null])),
                'p2p_quality' => $p2pPlan ? $data['p2p_quality'] : null, 'p2p_uplink_kbps' => $p2pPlan ? (int) $data['p2p_uplink_kbps'] : null,
                'status' => $willBePublished ? Seminar::PUBLISHED : Seminar::DRAFT,
            ];

            $seminar = $seminar === null ? Seminar::create($attrs + ['host_id' => $host->id]) : tap($seminar)->update($attrs);

            if ($before !== null && ! $before->equalTo($starts) && $seminar->isPublished()) {
                $this->tell(UserNotice::SEMINAR_CHANGED, $seminar, 'changed:'.$starts->timestamp);
            }

            return $seminar;
        });
    }

    /**
     * @param  string  $refunds  all | complainants | none: who is refunded among people who paid. Only a moderator's removal of a seminar that
     *                           has already started uses anything but "all"; people part-way through paying are always released.
     * @param  list<int>  $refundUserIds  with "complainants", the people whose tickets are refunded
     */
    public function cancel(Seminar $seminar, string $refunds = 'all', array $refundUserIds = []): void
    {
        if ($seminar->status === Seminar::CANCELLED) {
            return;
        }

        $wasPublished = $seminar->isPublished();
        $seminar->forceFill(['status' => Seminar::CANCELLED, 'cancelled_at' => now()])->save();

        if ($wasPublished) {
            $this->tell(UserNotice::SEMINAR_CANCELLED, $seminar, 'cancelled');
        }

        // If Dot Points were already queued for hosting this seminar, take them back (nothing happens when none were).
        app(SeminarHostPoints::class)->reverseIfAwarded($seminar);

        // Everyone who paid is refunded in full; anyone part-way through paying is released.
        foreach (SeminarRegistration::where('seminar_id', $seminar->id)->whereIn('payment_status', ['paid', 'pending'])->get() as $reg) {
            if ($reg->payment_status === 'paid') {
                if ($refunds === 'none' || ($refunds === 'complainants' && ! in_array($reg->user_id, $refundUserIds, true))) {
                    continue;
                }

                $reg->forceFill(['refund_requested_at' => $reg->refund_requested_at ?? now()])->save();
                $this->requestRefund($reg);
            } else {
                $reg->forceFill(['status' => SeminarRegistration::CANCELLED, 'payment_status' => 'free', 'hold_expires_at' => null, 'checkout_url' => null])->save();

                try {
                    $this->billing->cancel((string) $reg->ticket_ref);
                } catch (MarketplaceUnavailable) {
                }
            }
        }
    }

    /** @return string registered|waitlist */
    public function register(Seminar $seminar, User $user): string
    {
        if ($seminar->host_id === $user->id) {
            throw ValidationException::withMessages(['seminar' => 'You are hosting this seminar.']);
        }

        if ($seminar->isPaid()) {
            throw ValidationException::withMessages(['seminar' => 'This seminar sells tickets.']);
        }

        return DB::transaction(function () use ($seminar, $user) {
            $locked = Seminar::whereKey($seminar->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPublished() || $locked->hasEnded()) {
                throw ValidationException::withMessages(['seminar' => 'This seminar is not open for registration.']);
            }

            $existing = SeminarRegistration::where('seminar_id', $locked->id)->where('user_id', $user->id)->first();

            if ($existing && $existing->status !== SeminarRegistration::CANCELLED) {
                return $existing->status;
            }

            $taken = SeminarRegistration::where('seminar_id', $locked->id)->where('status', SeminarRegistration::REGISTERED)->count();
            $status = $locked->capacity !== null && $taken >= $locked->capacity ? SeminarRegistration::WAITLIST : SeminarRegistration::REGISTERED;

            SeminarRegistration::updateOrCreate(['seminar_id' => $locked->id, 'user_id' => $user->id], ['status' => $status, 'attended_at' => null]);

            return $status;
        });
    }

    public function unregister(Seminar $seminar, User $user): void
    {
        $refund = null;

        DB::transaction(function () use ($seminar, $user, &$refund) {
            $locked = Seminar::whereKey($seminar->id)->lockForUpdate()->firstOrFail();
            $reg = SeminarRegistration::where('seminar_id', $locked->id)->where('user_id', $user->id)->first();

            if (! $reg || $reg->status === SeminarRegistration::CANCELLED) {
                return;
            }

            $held = $reg->status === SeminarRegistration::REGISTERED;
            $reg->forceFill(['status' => SeminarRegistration::CANCELLED])->save();

            // A paid ticket cancelled early enough is refunded in full; inside the window the place is freed and no money moves.
            if ($held && in_array($reg->payment_status, ['paid', 'partially_refunded'], true) && $reg->dispute_status === null
                && $reg->refundableCents() > 0 && $this->refundWindowOpen($locked)) {
                $reg->forceFill(['refund_requested_at' => now(), 'refund_pending_cents' => $reg->refundableCents()])->save();
                $refund = $reg;
            }

            if ($held && ! $locked->isPaid() && $locked->isPublished() && ! $locked->hasEnded()) {
                $next = SeminarRegistration::where('seminar_id', $locked->id)->where('status', SeminarRegistration::WAITLIST)->orderBy('id')->first();

                if ($next) {
                    $next->forceFill(['status' => SeminarRegistration::REGISTERED])->save();
                    $this->notices->record(UserNotice::SEMINAR_SPOT_OPEN, User::find($next->user_id), 'seminar', $locked->id, 'seminar:'.$locked->id.':spot');
                }
            }
        });

        if ($refund) {
            $this->requestRefund($refund);
        }
    }

    /** Whether cancelling now still earns a refund: at least the configured number of hours before the start. */
    public function refundWindowOpen(Seminar $seminar): bool
    {
        return $seminar->starts_at->gte(now()->addHours((int) config('seminars.paid.refund_hours_before', 24)));
    }

    /**
     * The host refunds part of one ticket. Sent to Billing with a key so a retry is the same refund; the ticket changes only when
     * Billing confirms. Not possible while the ticket is disputed or once it is fully refunded.
     */
    public function hostRefund(Seminar $seminar, SeminarRegistration $reg, int $cents): void
    {
        $key = null;

        DB::transaction(function () use ($seminar, $reg, $cents, &$key) {
            $locked = SeminarRegistration::whereKey($reg->id)->lockForUpdate()->firstOrFail();

            if ($locked->seminar_id !== $seminar->id || ! in_array($locked->payment_status, ['paid', 'partially_refunded'], true) || $locked->dispute_status === 'open') {
                throw ValidationException::withMessages(['refund' => 'This ticket cannot be refunded now.']);
            }

            // One refund at a time: a second submit (a double click) would otherwise be a second, differently keyed request to Billing.
            if ($locked->refund_pending_cents !== null) {
                throw ValidationException::withMessages(['refund' => 'A refund for this ticket is already on its way. Wait until it is confirmed.']);
            }

            if ($cents < 1 || $cents > $locked->refundableCents()) {
                throw ValidationException::withMessages(['refund' => 'Enter an amount between R0.01 and '.Seminar::money($locked->refundableCents()).'.']);
            }

            $locked->forceFill(['refund_seq' => $locked->refund_seq + 1, 'refund_pending_cents' => $cents])->save();
            $key = 'rf-'.$locked->id.'-'.$locked->refund_seq;
        });

        try {
            $this->billing->refund((string) $reg->ticket_ref, $cents, $key);
        } catch (MarketplaceUnavailable $e) {
            SeminarRegistration::whereKey($reg->id)->update(['refund_pending_cents' => null]);

            throw ValidationException::withMessages(['refund' => $e->getMessage()]);
        }
    }

    /** The link to open, or null when this person may not join now. Opening it is what records attendance. */
    public function joinUrl(Seminar $seminar, User $user): ?string
    {
        $reg = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $user->id)->where('status', SeminarRegistration::REGISTERED)->first();

        if ($seminar->host_id === $user->id) {
            return $seminar->isPublished() ? $seminar->join_url : null;
        }

        if (! $reg || ! $seminar->joinIsOpen()) {
            return null;
        }

        if ($reg->attended_at === null) {
            $reg->forceFill(['attended_at' => now()])->save();
        }

        return $seminar->join_url;
    }

    /** Why this person cannot enter the InfoDot room right now, or null when they can. */
    public function roomRefusal(Seminar $seminar, User $user): ?string
    {
        if (! config('seminars.video.enabled') || ! $seminar->isRoom() || ! $seminar->isPublished()) {
            return 'This seminar has no InfoDot room.';
        }

        if ($seminar->host_id === $user->id) {
            return $seminar->hasEnded() ? 'This seminar has finished.' : null;
        }

        $registered = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $user->id)->where('status', SeminarRegistration::REGISTERED)->exists();

        return match (true) {
            DB::table('seminar_room_bans')->where('seminar_id', $seminar->id)->where('user_id', $user->id)->exists() => 'The host removed you from this room.',
            ! $registered => 'The room is for people who registered.',
            $seminar->hasEnded() => 'This seminar has finished.',
            ! $seminar->joinIsOpen() => 'The room opens '.config('seminars.join_opens_minutes_before', 15).' minutes before the start.',
            default => null,
        };
    }

    /** LiveKit says this person really connected. Recorded once; the host and strangers are ignored. */
    public function markJoinedFromRoom(Seminar $seminar, int $userId): bool
    {
        if ($userId === $seminar->host_id) {
            return false;
        }

        return SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $userId)->where('status', SeminarRegistration::REGISTERED)->whereNull('attended_at')
            ->update(['attended_at' => now()]) > 0;
    }

    /** A person who registered says the host's meeting link did not open. The host is told (once for a while); it clears when the link changes. */
    public function reportLink(Seminar $seminar, User $user): void
    {
        $registered = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $user->id)->where('status', SeminarRegistration::REGISTERED)->exists();

        if (! $registered || ! $seminar->isPublished() || $seminar->isRoom() || $seminar->isP2p() || ! $seminar->joinIsOpen()) {
            throw ValidationException::withMessages(['link' => 'You can report a link once the seminar has opened and you are registered.']);
        }

        DB::table('seminar_link_reports')->insertOrIgnore(['seminar_id' => $seminar->id, 'user_id' => $user->id, 'created_at' => now()]);
        $this->notices->record(UserNotice::SEMINAR_LINK_BROKEN, User::find($seminar->host_id), 'seminar', $seminar->id, 'seminar:'.$seminar->id.':link:'.(int) floor(now()->timestamp / 1800));
    }

    /** A signed-in person reports a seminar. One report each; the host cannot report their own. */
    public function report(Seminar $seminar, User $reporter, string $reason, ?string $detail, ?string $aboutName = null): SeminarReport
    {
        $aboutName = $aboutName !== null && trim($aboutName) !== '' ? mb_substr(trim($aboutName), 0, 60) : null;
        $isHost = $seminar->host_id === $reporter->id;

        if ($aboutName === null) {
            // Reporting the seminar itself: published, and not by its own host.
            $allowed = ! $isHost && $seminar->isPublished();
        } else {
            // Reporting a person in the room: the host, or someone registered for it, while the seminar is not a draft.
            $allowed = $seminar->status !== Seminar::DRAFT && ($isHost || SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $reporter->id)->where('status', '!=', SeminarRegistration::CANCELLED)->exists());
        }

        if (! $allowed || ! array_key_exists($reason, SeminarReport::REASONS)) {
            throw ValidationException::withMessages(['report' => $aboutName === null ? 'This seminar cannot be reported by you.' : 'You cannot report someone in this seminar.']);
        }

        return SeminarReport::firstOrCreate(['seminar_id' => $seminar->id, 'reporter_id' => $reporter->id, 'about_name' => $aboutName], [
            'reason' => $reason, 'detail' => $detail !== null && trim($detail) !== '' ? mb_substr(trim($detail), 0, 1000) : null,
        ]);
    }

    /**
     * A moderator takes a seminar down. It is cancelled (everyone who registered is told and everyone who paid is refunded in full), the host is told
     * why, the reports about it are closed, and the decision goes in the moderation record.
     */
    public function removeByModerator(Seminar $seminar, User $moderator, string $reason, string $refunds = 'all'): void
    {
        if ($seminar->removed_at !== null) {
            return;
        }

        // A draft is the host's private work: it is not published, so there is nothing to remove.
        if ($seminar->status === Seminar::DRAFT) {
            throw ValidationException::withMessages(['reason' => 'A draft cannot be removed; it is not published.']);
        }

        // Before the start everyone who paid is refunded. Once it has started the moderator chooses: everyone, only the people who reported it, or no one.
        $refunds = $seminar->starts_at->isFuture() || ! in_array($refunds, ['all', 'complainants', 'none'], true) ? 'all' : $refunds;
        $complainants = $refunds === 'complainants'
            ? SeminarReport::where('seminar_id', $seminar->id)->whereNull('about_name')->pluck('reporter_id')->map(fn ($id) => (int) $id)->all()
            : [];

        $seminar->forceFill(['removed_at' => now(), 'removed_reason' => mb_substr($reason, 0, 500), 'removed_refunds' => $refunds])->save();
        $this->cancel($seminar->fresh(), $refunds, $complainants);
        $this->notices->record(UserNotice::SEMINAR_REMOVED, User::find($seminar->host_id), 'seminar', $seminar->id, 'seminar:'.$seminar->id.':removed');
        ModerationAction::record($seminar, $moderator, ModerationAction::SEMINAR_REMOVED, $reason.' [refunds: '.$refunds.']');
        SeminarReport::where('seminar_id', $seminar->id)->where('status', 'open')->update(['status' => 'actioned', 'resolved_by' => $moderator->id, 'resolution_note' => 'Seminar removed.', 'resolved_at' => now()]);
    }

    /** The host's one appeal against a removal, within the configured number of days. */
    public function appealRemoval(Seminar $seminar, User $host, string $message): SeminarRemovalAppeal
    {
        $days = (int) config('seminars.removal_appeal_days', 30);

        if ($seminar->host_id !== $host->id || $seminar->removed_at === null) {
            throw ValidationException::withMessages(['appeal' => 'There is nothing to appeal.']);
        }

        if ($seminar->removed_at->lt(now()->subDays($days))) {
            throw ValidationException::withMessages(['appeal' => "An appeal must be made within $days days of the removal."]);
        }

        if (SeminarRemovalAppeal::where('seminar_id', $seminar->id)->exists()) {
            throw ValidationException::withMessages(['appeal' => 'You have already appealed this removal.']);
        }

        return SeminarRemovalAppeal::create(['seminar_id' => $seminar->id, 'host_id' => $host->id, 'message' => mb_substr(trim($message), 0, 1000)]);
    }

    /**
     * A different moderator from the one who removed it decides (unless there is only one moderator). Reinstating works only before the start;
     * refunds already made stay made, and people who were still registered are told it is back on.
     */
    public function decideRemovalAppeal(SeminarRemovalAppeal $appeal, User $moderator, bool $reinstate, string $note): void
    {
        $seminar = Seminar::findOrFail($appeal->seminar_id);
        $appeal = $appeal->fresh();

        // A moderator does not decide an appeal about their own seminar (content moderation has the same rule).
        if ($seminar->host_id === $moderator->id) {
            throw ValidationException::withMessages(['appeal' => 'You cannot decide an appeal about your own seminar.']);
        }

        if ($appeal->status !== SeminarRemovalAppeal::OPEN) {
            throw ValidationException::withMessages(['appeal' => 'This appeal has already been decided.']);
        }

        $remover = ModerationAction::where('subject_type', $seminar->getMorphClass())->where('subject_id', $seminar->id)->where('action', ModerationAction::SEMINAR_REMOVED)->latest('id')->value('actor_id');

        if ($remover !== null && (int) $remover === $moderator->id && User::where('is_moderator', true)->count() > 1) {
            throw ValidationException::withMessages(['appeal' => 'A different moderator must decide an appeal against your own removal.']);
        }

        if ($reinstate && ! $seminar->starts_at->isFuture()) {
            throw ValidationException::withMessages(['appeal' => 'The seminar has already started, so it cannot be put back on.']);
        }

        DB::transaction(function () use ($appeal, $seminar, $moderator, $reinstate, $note) {
            $appeal->forceFill(['status' => $reinstate ? SeminarRemovalAppeal::REINSTATED : SeminarRemovalAppeal::DENIED, 'resolved_by' => $moderator->id, 'resolution_note' => mb_substr($note, 0, 500), 'resolved_at' => now()])->save();

            if ($reinstate) {
                $seminar->forceFill(['status' => Seminar::PUBLISHED, 'cancelled_at' => null, 'removed_at' => null, 'removed_reason' => null, 'removed_refunds' => null])->save();
            }
        });

        if ($reinstate) {
            $this->tell(UserNotice::SEMINAR_REINSTATED, $seminar->fresh(), 'reinstated:'.$appeal->id);
        }

        $this->notices->record(UserNotice::SEMINAR_APPEAL_DECIDED, User::find($seminar->host_id), 'seminar', $seminar->id, 'seminar:'.$seminar->id.':appeal:'.$appeal->id);
        ModerationAction::record($seminar, $moderator, $reinstate ? ModerationAction::SEMINAR_REINSTATED : ModerationAction::SEMINAR_APPEAL_DENIED, $note);
    }

    /** A moderator bars a registered person from the seminar's room because of a report about them. They stay registered; they cannot get back in. */
    public function barFromRoom(Seminar $seminar, User $target, User $moderator, string $note): void
    {
        DB::table('seminar_room_bans')->insertOrIgnore(['seminar_id' => $seminar->id, 'user_id' => $target->id, 'created_at' => now()]);
        app(P2pRoom::class)->remove($seminar, $target);

        if ($seminar->isRoom() && app(LiveKit::class)->configured()) {
            try {
                app(LiveKit::class)->remove($seminar, $target);
            } catch (\Throwable) {
                // The ban already stops a new entry pass; disconnecting someone who is in the room right now is best effort.
            }
        }

        ModerationAction::record($seminar, $moderator, ModerationAction::SEMINAR_PERSON_BARRED, $note);
    }

    public function dismissReport(SeminarReport $report, User $moderator, string $note): void
    {
        if (Seminar::whereKey($report->seminar_id)->value('host_id') === $moderator->id) {
            throw ValidationException::withMessages(['reason' => 'You cannot decide a report about your own seminar.']);
        }

        $report->forceFill(['status' => 'dismissed', 'resolved_by' => $moderator->id, 'resolution_note' => mb_substr($note, 0, 500), 'resolved_at' => now()])->save();
    }

    public function linkReports(Seminar $seminar): int
    {
        return (int) DB::table('seminar_link_reports')->where('seminar_id', $seminar->id)->count();
    }

    public function setAttended(Seminar $seminar, User $person, bool $attended): void
    {
        SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $person->id)->where('status', SeminarRegistration::REGISTERED)
            ->update(['attended_at' => $attended ? now() : null]);
    }

    public function rate(Seminar $seminar, User $user, int $rating, ?string $comment): void
    {
        $attended = SeminarRegistration::where('seminar_id', $seminar->id)->where('user_id', $user->id)->whereNotNull('attended_at')->exists();

        if (! $seminar->isPublished() || ! $seminar->hasEnded() || ! $attended || $seminar->host_id === $user->id) {
            throw ValidationException::withMessages(['rating' => 'Only people who attended can rate a seminar, once it has finished.']);
        }

        SeminarFeedback::updateOrCreate(['seminar_id' => $seminar->id, 'user_id' => $user->id], ['rating' => max(1, min(5, $rating)), 'comment' => $comment !== null && trim($comment) !== '' ? mb_substr(trim($comment), 0, 1000) : null]);
    }

    /** @return array{hosted: int, attendees: int, ratings: int, average: ?float} average is null until enough people have rated */
    public function hostStats(User $host): array
    {
        $ended = Seminar::where('host_id', $host->id)->where('status', Seminar::PUBLISHED)->get()->filter->hasEnded();
        $ids = $ended->pluck('id');
        $ratings = SeminarFeedback::whereIn('seminar_id', $ids)->pluck('rating');

        return [
            'hosted' => $ended->count(),
            'attendees' => SeminarRegistration::whereIn('seminar_id', $ids)->whereNotNull('attended_at')->count(),
            'ratings' => $ratings->count(),
            'average' => $ratings->count() >= (int) config('seminars.min_ratings_shown', 3) ? round($ratings->avg(), 1) : null,
        ];
    }

    /** Parses the host's price. Null (free) unless paid seminars are on, the host has a payout account and the amount is within bounds. */
    private function priceFrom(User $host, mixed $price): ?int
    {
        if ($price === null || trim((string) $price) === '' || (float) $price == 0.0) {
            return null;
        }

        if (! config('seminars.paid.enabled')) {
            throw ValidationException::withMessages(['price' => 'Paid seminars are not available yet.']);
        }

        if (! SeminarPayoutAccount::where('user_id', $host->id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['price' => 'Set up where your earnings are paid before you charge for a seminar.']);
        }

        if (! config('seminars.paid.agreement_approved')) {
            throw ValidationException::withMessages(['price' => 'Paid seminars open once the host agreement has been approved. We will tell you.']);
        }

        if (! SeminarHostAgreement::where('user_id', $host->id)->where('version', config('seminars.paid.agreement_version', 'v1'))->exists()) {
            throw ValidationException::withMessages(['price' => 'Read and accept the host agreement (on the page where you set up payouts) before you charge for a seminar.']);
        }

        $cents = (int) round(((float) $price) * 100);
        [$min, $max] = [(int) config('seminars.paid.min_price_cents', 500), (int) config('seminars.paid.max_price_cents', 5000000)];

        if ($cents < $min || $cents > $max) {
            throw ValidationException::withMessages(['price' => 'The price must be between '.Seminar::money($min).' and '.Seminar::money($max).'.']);
        }

        return $cents;
    }

    /** Once anyone has paid or is paying, the price cannot change under them. */
    private function assertPriceChangeAllowed(?Seminar $seminar, ?int $newCents): void
    {
        if ($seminar === null || $seminar->price_cents === $newCents) {
            return;
        }

        if (SeminarRegistration::where('seminar_id', $seminar->id)->whereIn('payment_status', ['pending', 'paid'])->exists()) {
            throw ValidationException::withMessages(['price' => 'People have already bought or started buying tickets, so the price cannot change.']);
        }
    }

    /**
     * Holds a place and starts the payment for a paid seminar. Returns the Paystack page to send the attendee to. The place is held for
     * a short time; it becomes a registration only when Billing reports the payment verified, never because the browser came back.
     */
    public function startCheckout(Seminar $seminar, User $user): string
    {
        if ($seminar->host_id === $user->id) {
            throw ValidationException::withMessages(['seminar' => 'You are hosting this seminar.']);
        }

        $account = SeminarPayoutAccount::where('user_id', $seminar->host_id)->where('status', 'active')->first();

        [$reg, $reuse] = DB::transaction(function () use ($seminar, $user) {
            $locked = Seminar::whereKey($seminar->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPublished() || $locked->hasEnded() || ! $locked->isPaid()) {
                throw ValidationException::withMessages(['seminar' => 'This seminar is not open for tickets.']);
            }

            $existing = SeminarRegistration::where('seminar_id', $locked->id)->where('user_id', $user->id)->first();

            if ($existing && $existing->status === SeminarRegistration::REGISTERED) {
                throw ValidationException::withMessages(['seminar' => 'You already have a ticket.']);
            }

            if ($existing && $existing->status === SeminarRegistration::PENDING_PAYMENT && $existing->hold_expires_at?->isFuture()) {
                if ($existing->checkout_url) {
                    return [$existing, true];
                }

                // The first request is still asking Billing for the payment page. A second one now would make a second ticket reference and
                // overwrite the first, and a payment made on the first would match no registration. Wait for it (a minute at most).
                if ($existing->updated_at?->gt(now()->subMinute())) {
                    throw ValidationException::withMessages(['seminar' => 'Your ticket is being prepared. Wait a moment and try again.']);
                }
            }

            $taken = SeminarRegistration::where('seminar_id', $locked->id)->where(fn ($q) => $q->where('status', SeminarRegistration::REGISTERED)
                ->orWhere(fn ($q) => $q->where('status', SeminarRegistration::PENDING_PAYMENT)->where('hold_expires_at', '>', now())))
                ->where('user_id', '!=', $user->id)->count();

            if ($locked->capacity !== null && $taken >= $locked->capacity) {
                throw ValidationException::withMessages(['seminar' => 'This seminar is full.']);
            }

            $reg = SeminarRegistration::updateOrCreate(['seminar_id' => $locked->id, 'user_id' => $user->id], [
                'status' => SeminarRegistration::PENDING_PAYMENT, 'payment_status' => 'pending', 'attended_at' => null,
                'ticket_ref' => 'sem'.$locked->id.'-u'.$user->id.'-'.Str::lower(Str::random(10)),
                'amount_cents' => $locked->price_cents, 'currency' => $locked->currency, 'checkout_url' => null,
                'hold_expires_at' => now()->addMinutes((int) config('seminars.paid.hold_minutes', 30)),
                'paid_at' => null, 'refund_requested_at' => null, 'refunded_at' => null,
            ]);

            return [$reg, false];
        });

        if ($reuse) {
            return $reg->checkout_url;
        }

        try {
            $ticket = $this->billing->createTicket($reg->ticket_ref, (int) $account?->billing_account_id, $user->email, (int) $reg->amount_cents, (string) $reg->currency, $seminar->title, url(config('seminars.paid.return_path', '/seminars').'/'.$seminar->id));
        } catch (MarketplaceUnavailable $e) {
            $reg->forceFill(['status' => SeminarRegistration::CANCELLED, 'payment_status' => 'free', 'hold_expires_at' => null])->save();

            throw ValidationException::withMessages(['seminar' => $e->getMessage()]);
        }

        $reg->forceFill(['checkout_url' => $ticket['authorization_url']])->save();

        return $ticket['authorization_url'];
    }

    /**
     * Applies one signed callback from Billing. Each (event, ticket) is applied once. The amount and currency must equal what was asked
     * for; anything else is recorded and left for a person to look at. @return string applied|duplicate|unknown|mismatch|ignored
     */
    public function applyTicketEvent(string $event, string $ticketRef, int $amountCents, string $currency, array $extra = []): string
    {
        if (! in_array($event, ['ticket.paid', 'ticket.refunded', 'ticket.disputed', 'ticket.dispute_resolved'], true)) {
            return 'ignored';
        }

        $refKey = match ($event) {
            'ticket.refunded' => mb_substr((string) ($extra['refund_key'] ?? 'full-'.(int) ($extra['refunded_total_cents'] ?? $amountCents)), 0, 64),
            'ticket.dispute_resolved' => mb_substr((string) ($extra['outcome'] ?? ''), 0, 64),
            default => '',
        };

        return DB::transaction(function () use ($event, $ticketRef, $amountCents, $currency, $extra, $refKey) {
            if (SeminarTicketEvent::where('event', $event)->where('ticket_ref', $ticketRef)->where('ref_key', $refKey)->exists()) {
                return 'duplicate';
            }

            $reg = SeminarRegistration::where('ticket_ref', $ticketRef)->lockForUpdate()->first();
            $outcome = 'unknown';

            if ($reg) {
                $seminar = Seminar::whereKey($reg->seminar_id)->lockForUpdate()->first();

                if ($amountCents !== (int) $reg->amount_cents || strtoupper($currency) !== strtoupper((string) $reg->currency)) {
                    Log::warning('Seminar ticket callback did not match what was asked for.', ['ticket_ref' => $ticketRef, 'event' => $event]);
                    $outcome = 'mismatch';
                } else {
                    $outcome = match ($event) {
                        'ticket.paid' => $this->markPaid($reg, $seminar),
                        'ticket.refunded' => $this->applyRefund($reg, $seminar, $extra, $refKey),
                        'ticket.disputed' => $this->applyDispute($reg, $seminar),
                        default => $this->applyDisputeResolution($reg, $seminar, (string) ($extra['outcome'] ?? 'other')),
                    };
                }
            }

            SeminarTicketEvent::create(['event' => $event, 'ticket_ref' => $ticketRef, 'ref_key' => $refKey, 'outcome' => $outcome]);

            return $outcome;
        });
    }

    /** Billing confirmed money went back. A repeat key is a repeat; a total that does not add up is refused. */
    private function applyRefund(SeminarRegistration $reg, Seminar $seminar, array $extra, string $refKey): string
    {
        $total = (int) ($extra['refunded_total_cents'] ?? $reg->amount_cents);
        $now = $total - (int) $reg->refunded_cents; // the amount this confirmation adds
        $given = (int) ($extra['refund_amount_cents'] ?? $now);

        if ($given < 1 || $total > (int) $reg->amount_cents || $total <= (int) $reg->refunded_cents) {
            Log::warning('Seminar refund callback did not add up.', ['ticket_ref' => $reg->ticket_ref]);

            return 'mismatch';
        }

        DB::table('seminar_refund_lines')->insert(['registration_id' => $reg->id, 'seminar_id' => $seminar->id, 'host_id' => $seminar->host_id, 'amount_cents' => $now, 'reason' => 'refund', 'created_at' => now()]);
        $full = $total >= (int) $reg->amount_cents;
        $reg->forceFill(array_filter([
            'refunded_cents' => $total, 'refunded_at' => now(), 'refund_pending_cents' => null, 'hold_expires_at' => null,
            'payment_status' => $full ? 'refunded' : 'partially_refunded', 'status' => $full ? SeminarRegistration::CANCELLED : null,
        ], fn ($v) => $v !== null))->save();

        $this->notices->record(UserNotice::SEMINAR_REFUNDED, User::find($reg->user_id), 'seminar', $seminar->id, 'seminar:'.$seminar->id.':refund:'.$refKey);

        return 'applied';
    }

    private function applyDispute(SeminarRegistration $reg, Seminar $seminar): string
    {
        $reg->forceFill(['dispute_status' => 'open', 'disputed_at' => now()])->save();
        $this->notices->record(UserNotice::SEMINAR_DISPUTE, User::find($seminar->host_id), 'seminar', $seminar->id, 'seminar:'.$seminar->id.':dispute:'.$reg->id);

        return 'applied';
    }

    /** A lost dispute takes the ticket back (the place goes) and reverses the commission on what is left; a won one changes nothing else. */
    private function applyDisputeResolution(SeminarRegistration $reg, Seminar $seminar, string $outcome): string
    {
        $outcome = in_array($outcome, ['won', 'lost'], true) ? $outcome : 'other';
        $reg->dispute_status = $outcome;

        if ($outcome === 'lost') {
            if ($reg->refundableCents() > 0) {
                DB::table('seminar_refund_lines')->insert(['registration_id' => $reg->id, 'seminar_id' => $seminar->id, 'host_id' => $seminar->host_id, 'amount_cents' => $reg->refundableCents(), 'reason' => 'chargeback', 'created_at' => now()]);
            }

            $reg->payment_status = 'chargeback';
            $reg->status = SeminarRegistration::CANCELLED;
        }

        $reg->save();

        return 'applied';
    }

    private function markPaid(SeminarRegistration $reg, Seminar $seminar): string
    {
        if ($reg->payment_status === 'paid' || $reg->payment_status === 'refunded') {
            return 'applied';
        }

        $reg->forceFill(['payment_status' => 'paid', 'paid_at' => now()])->save();

        $taken = SeminarRegistration::where('seminar_id', $seminar->id)->where('status', SeminarRegistration::REGISTERED)->where('id', '!=', $reg->id)->count();
        $roomLeft = $seminar->capacity === null || $taken < $seminar->capacity;

        if ($seminar->isPublished() && ! $seminar->hasEnded() && $roomLeft) {
            $reg->forceFill(['status' => SeminarRegistration::REGISTERED, 'hold_expires_at' => null])->save();

            return 'applied';
        }

        // Paid after the seminar was cancelled or ended, or after the place went to someone else: the money goes back.
        $reg->forceFill(['status' => SeminarRegistration::CANCELLED, 'refund_requested_at' => now()])->save();
        DB::afterCommit(fn () => $this->requestRefund($reg));

        return 'applied';
    }

    private function requestRefund(SeminarRegistration $reg): void
    {
        try {
            $reg->refunded_cents > 0
                ? $this->billing->refund((string) $reg->ticket_ref, $reg->refundableCents(), 'rest-'.$reg->id)
                : $this->billing->refund((string) $reg->ticket_ref);
        } catch (MarketplaceUnavailable) {
            // Left marked as requested; seminars:sweep-payments asks again.
        }
    }

    /** Places held for payment that ran out of time are given back, and Billing is told not to take a late payment. */
    public function expireHolds(): int
    {
        $n = 0;

        foreach (SeminarRegistration::where('status', SeminarRegistration::PENDING_PAYMENT)->where('hold_expires_at', '<', now())->get() as $reg) {
            // Release only if the row is still an unpaid, expired hold at the moment of the update. A payment confirmed since the list was read
            // (the callback runs in another request) must not be overwritten.
            $released = SeminarRegistration::whereKey($reg->id)->where('status', SeminarRegistration::PENDING_PAYMENT)->where('payment_status', 'pending')
                ->where('hold_expires_at', '<', now())
                ->update(['status' => SeminarRegistration::CANCELLED, 'payment_status' => 'free', 'hold_expires_at' => null, 'checkout_url' => null]);

            if ($released === 0) {
                continue;
            }

            $n++;

            try {
                $this->billing->cancel((string) $reg->ticket_ref);
            } catch (MarketplaceUnavailable) {
                // A late payment would be refunded by markPaid().
            }
        }

        return $n;
    }

    /** Asks again for refunds that were requested but not yet confirmed. Billing treats a repeat as the same request. */
    public function retryRefunds(): int
    {
        $n = 0;

        foreach (SeminarRegistration::where('payment_status', 'paid')->whereNotNull('refund_requested_at')->where('refund_requested_at', '<', now()->subMinutes(10))->get() as $reg) {
            $this->requestRefund($reg);
            $n++;
        }

        return $n;
    }

    /** @return array{tickets: int, gross_cents: int, platform_cents: int, host_cents: int} what the host has taken, before Paystack's fee */
    public function hostRevenue(Seminar $seminar): array
    {
        $kept = SeminarRegistration::where('seminar_id', $seminar->id)->whereIn('payment_status', ['paid', 'partially_refunded'])->whereNull('refund_requested_at')
            ->get(['amount_cents', 'refunded_cents'])->map(fn ($r) => (int) $r->amount_cents - (int) $r->refunded_cents);
        $pct = (int) config('seminars.paid.platform_percent', 10);
        $platform = (int) $kept->sum(fn ($c) => (int) floor($c * $pct / 100 + 0.5));

        return ['tickets' => $kept->count(), 'gross_cents' => (int) $kept->sum(), 'platform_cents' => $platform, 'host_cents' => (int) $kept->sum() - $platform];
    }

    /** Day-before and hour-before reminders to people who registered. Safe to run often: one notice per person, seminar and kind of reminder. */
    public function sendReminders(): int
    {
        $sent = 0;

        foreach (Seminar::where('status', Seminar::PUBLISHED)->where('starts_at', '>', now())->where('starts_at', '<=', now()->addDay())->get() as $seminar) {
            $window = $seminar->starts_at->lte(now()->addHour()) ? 'hour' : 'day';

            foreach (SeminarRegistration::where('seminar_id', $seminar->id)->where('status', SeminarRegistration::REGISTERED)->get() as $reg) {
                $sent += (int) $this->notices->record(UserNotice::SEMINAR_REMINDER, User::find($reg->user_id), 'seminar', $seminar->id, 'seminar:'.$seminar->id.':'.$window);
            }
        }

        return $sent;
    }

    private function tell(string $kind, Seminar $seminar, string $suffix): void
    {
        $host = User::find($seminar->host_id);

        foreach (SeminarRegistration::where('seminar_id', $seminar->id)->whereIn('status', [SeminarRegistration::REGISTERED, SeminarRegistration::WAITLIST])->pluck('user_id') as $uid) {
            $this->notices->record($kind, User::find($uid), 'seminar', $seminar->id, 'seminar:'.$seminar->id.':'.$suffix, $host);
        }
    }

    private function assertLead(Carbon $starts): void
    {
        if ($starts->lt(now()->addMinutes((int) config('seminars.min_lead_minutes', 60)))) {
            throw ValidationException::withMessages(['starts_at' => 'A seminar must start at least '.config('seminars.min_lead_minutes', 60).' minutes from now.']);
        }
    }

    private function assertRoomToPublish(User $host): void
    {
        $upcoming = Seminar::where('host_id', $host->id)->where('status', Seminar::PUBLISHED)->get()->reject->hasEnded()->count();
        $max = (int) config('seminars.max_upcoming_per_host', 5);

        if ($upcoming >= $max) {
            throw ValidationException::withMessages(['title' => "You already have $max upcoming seminars. Wait for one to finish or cancel one first."]);
        }
    }
}

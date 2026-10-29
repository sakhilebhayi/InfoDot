<?php

namespace App\Services\Seminars;

use App\Models\Seminar;
use App\Models\SeminarFeedback;
use App\Models\SeminarPayoutAccount;
use App\Models\SeminarTicketEvent;
use App\Models\SeminarRegistration;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
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
            'join_url' => ['required', 'url:https', 'max:500'],
            'price' => ['nullable', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
        ];
    }

    /** @param  array<string, mixed>  $data  validated by rules() */
    public function save(User $host, ?Seminar $seminar, array $data, bool $publish): Seminar
    {
        $starts = \Illuminate\Support\Carbon::parse($data['starts_at'], config('app.timezone'))->utc();
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

            $priceCents = $this->priceFrom($host, $data['price'] ?? null);
            $this->assertPriceChangeAllowed($seminar, $priceCents);

            $before = $seminar?->starts_at;
            $attrs = [
                'price_cents' => $priceCents, 'currency' => $priceCents ? config('seminars.paid.currency', 'ZAR') : null,
                'title' => $data['title'], 'summary' => $data['summary'], 'description' => $data['description'] ?? null,
                'topic_id' => $data['topic_id'] ?? null, 'starts_at' => $starts, 'duration_minutes' => (int) $data['duration_minutes'],
                'capacity' => isset($data['capacity']) ? (int) $data['capacity'] : null, 'join_url' => $data['join_url'],
                'status' => $willBePublished ? Seminar::PUBLISHED : Seminar::DRAFT,
            ];

            $seminar = $seminar === null ? Seminar::create($attrs + ['host_id' => $host->id]) : tap($seminar)->update($attrs);

            if ($before !== null && ! $before->equalTo($starts) && $seminar->isPublished()) {
                $this->tell(UserNotice::SEMINAR_CHANGED, $seminar, 'changed:'.$starts->timestamp);
            }

            return $seminar;
        });
    }

    public function cancel(Seminar $seminar): void
    {
        if ($seminar->status === Seminar::CANCELLED) {
            return;
        }

        $wasPublished = $seminar->isPublished();
        $seminar->forceFill(['status' => Seminar::CANCELLED, 'cancelled_at' => now()])->save();

        if ($wasPublished) {
            $this->tell(UserNotice::SEMINAR_CANCELLED, $seminar, 'cancelled');
        }

        // Everyone who paid is refunded in full; anyone part-way through paying is released.
        foreach (SeminarRegistration::where('seminar_id', $seminar->id)->whereIn('payment_status', ['paid', 'pending'])->get() as $reg) {
            if ($reg->payment_status === 'paid') {
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
                    $this->notices->record(UserNotice::SEMINAR_SPOT_OPEN, User::findOrFail($next->user_id), 'seminar', $locked->id, 'seminar:'.$locked->id.':spot');
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

            if ($existing && $existing->status === SeminarRegistration::PENDING_PAYMENT && $existing->hold_expires_at?->isFuture() && $existing->checkout_url) {
                return [$existing, true];
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

        $this->notices->record(UserNotice::SEMINAR_REFUNDED, User::findOrFail($reg->user_id), 'seminar', $seminar->id, 'seminar:'.$seminar->id.':refund:'.$refKey);

        return 'applied';
    }

    private function applyDispute(SeminarRegistration $reg, Seminar $seminar): string
    {
        $reg->forceFill(['dispute_status' => 'open', 'disputed_at' => now()])->save();
        $this->notices->record(UserNotice::SEMINAR_DISPUTE, User::findOrFail($seminar->host_id), 'seminar', $seminar->id, 'seminar:'.$seminar->id.':dispute:'.$reg->id);

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
            $reg->forceFill(['status' => SeminarRegistration::CANCELLED, 'payment_status' => 'free', 'hold_expires_at' => null, 'checkout_url' => null])->save();
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
                $sent += (int) $this->notices->record(UserNotice::SEMINAR_REMINDER, User::findOrFail($reg->user_id), 'seminar', $seminar->id, 'seminar:'.$seminar->id.':'.$window);
            }
        }

        return $sent;
    }

    private function tell(string $kind, Seminar $seminar, string $suffix): void
    {
        $host = User::find($seminar->host_id);

        foreach (SeminarRegistration::where('seminar_id', $seminar->id)->whereIn('status', [SeminarRegistration::REGISTERED, SeminarRegistration::WAITLIST])->pluck('user_id') as $uid) {
            $this->notices->record($kind, User::findOrFail($uid), 'seminar', $seminar->id, 'seminar:'.$seminar->id.':'.$suffix, $host);
        }
    }

    private function assertLead(\Illuminate\Support\Carbon $starts): void
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

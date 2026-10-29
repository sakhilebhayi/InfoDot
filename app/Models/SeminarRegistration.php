<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeminarRegistration extends Model
{
    public const REGISTERED = 'registered';

    public const WAITLIST = 'waitlist';

    public const CANCELLED = 'cancelled';

    public const PENDING_PAYMENT = 'pending_payment';

    protected $fillable = [
        'seminar_id', 'user_id', 'status', 'attended_at', 'payment_status', 'ticket_ref', 'amount_cents', 'currency', 'checkout_url',
        'hold_expires_at', 'paid_at', 'refund_requested_at', 'refunded_at', 'refunded_cents', 'refund_seq', 'refund_pending_cents',
        'dispute_status', 'disputed_at',
    ];

    protected function casts(): array
    {
        return ['attended_at' => 'datetime', 'hold_expires_at' => 'datetime', 'paid_at' => 'datetime', 'refund_requested_at' => 'datetime', 'refunded_at' => 'datetime', 'disputed_at' => 'datetime', 'refunded_cents' => 'integer', 'amount_cents' => 'integer'];
    }

    public function seminar(): BelongsTo
    {
        return $this->belongsTo(Seminar::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault(['name' => 'A former member']);
    }

    /** What can still be refunded. */
    public function refundableCents(): int
    {
        return max(0, (int) $this->amount_cents - (int) $this->refunded_cents);
    }
}

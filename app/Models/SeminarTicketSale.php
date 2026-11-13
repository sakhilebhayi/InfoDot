<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One ticket sale: a checkout that was paid. Written when Dot.Finance confirms the payment and never reused, so a person
 * who buys again after a refund has two sales and one registration (their current place). Commission invoices, receipts,
 * the data export and the refund sweep read sales; the registration row says only where the person stands now.
 *
 * @property int $id
 * @property int $registration_id
 * @property int $seminar_id
 * @property int|null $user_id
 * @property string $ticket_ref
 * @property int $amount_cents
 * @property string $currency
 * @property string $state
 * @property int $refunded_cents
 * @property Carbon|null $paid_at
 * @property Carbon|null $refund_requested_at
 * @property Carbon|null $refunded_at
 */
class SeminarTicketSale extends Model
{
    public const PAID = 'paid';

    public const PARTIALLY_REFUNDED = 'partially_refunded';

    public const REFUNDED = 'refunded';

    public const CHARGEBACK = 'chargeback';

    /** States in which the buyer's money, or part of it, is still held. */
    public const HOLDING = [self::PAID, self::PARTIALLY_REFUNDED];

    protected $fillable = [
        'registration_id', 'seminar_id', 'user_id', 'ticket_ref', 'amount_cents', 'currency', 'state', 'refunded_cents',
        'paid_at', 'refund_requested_at', 'refunded_at',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'refunded_cents' => 'integer', 'paid_at' => 'datetime', 'refund_requested_at' => 'datetime', 'refunded_at' => 'datetime'];
    }

    public function seminar(): BelongsTo
    {
        return $this->belongsTo(Seminar::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(SeminarRegistration::class, 'registration_id');
    }

    /** What can still be refunded on this sale. */
    public function refundableCents(): int
    {
        return max(0, (int) $this->amount_cents - (int) $this->refunded_cents);
    }

    /** The receipt number shown to the buyer. */
    public function receiptNumber(): string
    {
        return 'R-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}

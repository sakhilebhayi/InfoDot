<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * One entry in an enquiry: the customer's words, the business's reply, an internal note, or a
 * recorded event (status or assignment change). Only `customer` and `business` reach the customer.
 *
 * @property int $id
 * @property int $enquiry_id
 * @property int|null $user_id
 * @property string $kind
 * @property string $body
 * @property string|null $body_hash
 * @property Carbon $created_at
 */
class EnquiryMessage extends Model
{
    public const CUSTOMER = 'customer';

    public const BUSINESS = 'business';

    public const NOTE = 'note';

    public const EVENT = 'event';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<Enquiry, $this> */
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('sort_order');
    }

    /** Whether the customer may see this entry. */
    public function isPublic(): bool
    {
        return in_array($this->kind, [self::CUSTOMER, self::BUSINESS], true);
    }
}

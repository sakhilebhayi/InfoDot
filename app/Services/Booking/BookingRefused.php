<?php

namespace App\Services\Booking;

use RuntimeException;

/** A booking or cancellation that is not allowed. The message is plain and safe to show the person; `reason` is a stable code for callers and tests. */
class BookingRefused extends RuntimeException
{
    public const CLOSED = 'closed';

    public const SLOT_TAKEN = 'slot_taken';

    public const LIMIT = 'limit';

    public const MEMBER = 'member';

    public const NOT_YOURS = 'not_yours';

    public const TOO_LATE = 'too_late';

    public const NOT_OPEN = 'not_open';

    /** The person may not act on this booking. */
    public const NOT_ALLOWED = 'not_allowed';

    /** The booking is not in a state this can be done from. */
    public const STATE = 'state';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}

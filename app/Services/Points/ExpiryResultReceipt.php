<?php

namespace App\Services\Points;

/** The points service's acknowledgement of an expiry-warning result: which warning, and the state it is now in. */
final class ExpiryResultReceipt
{
    public function __construct(
        public readonly string $warningId,
        public readonly string $state,
    ) {}
}

<?php

namespace App\Services\Points;

/** The points service's acknowledgement of a digest result: which digest, and the state it is now in. */
final class DigestResultReceipt
{
    public function __construct(
        public readonly string $digestId,
        public readonly string $state,
    ) {}
}

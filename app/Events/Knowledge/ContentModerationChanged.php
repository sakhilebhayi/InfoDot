<?php

namespace App\Events\Knowledge;

/**
 * Hidden, restored, verified or unverified (or an appeal upheld): a change to whether a piece of content counts as
 * contribution. Raised once, after the change is saved; carries the kind and id only. Not sent to the points ledger.
 */
final class ContentModerationChanged
{
    /** @param array<string, scalar|null> $facts */
    public function __construct(public readonly string $ref, public readonly array $facts = []) {}
}

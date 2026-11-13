<?php

namespace App\Support;

/**
 * Request parameters the application reads as text. A browser or a script can send any of them as a list
 * (`topic[]=x`); casting a list to a string raises an error, so every such read goes through here.
 */
final class Input
{
    /** The value as text, or an empty string when it is a list or is missing. */
    public static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

<?php

namespace App\Support;

/**
 * Short-lived news for the pop-up area (R-64 phase 10). Called from a controller after the work is done and confirmed, so the pop-up appears on the page
 * that follows the redirect and never before the server has said yes. A message people need to come back to stays on the page; this is for "Saved".
 */
final class Toast
{
    public static function flash(string $text, string $tone = 'ok', string $label = 'Done'): void
    {
        session()->flash('toast', ['text' => $text, 'tone' => in_array($tone, ['ok', 'info', 'warn'], true) ? $tone : 'ok', 'label' => $label]);
    }
}

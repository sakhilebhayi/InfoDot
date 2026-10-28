<?php

namespace App\Support;

use Illuminate\Support\Facades\Vite;
use Throwable;

/**
 * Script tags for assets a page can do without. The landing page's motion is an extra: if its file is missing from the build (a stale or
 * partial build), the page must still open, so a missing entry is reported and leaves nothing on the page instead of throwing.
 */
class OptionalVite
{
    public static function tags(string ...$entries): string
    {
        try {
            return (string) Vite::withEntryPoints($entries)->toHtml();
        } catch (Throwable $e) {
            report($e);

            return '';
        }
    }
}

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
    /**
     * The fonts a layout may preload (resources/views/partials/head-fonts.blade.php), by the path the build knows them under. Each is
     * named by a url() in resources/css/fonts.css, which is what puts it in the build and its manifest.
     */
    public const PRELOADABLE_FONTS = [
        'archivo' => 'node_modules/@fontsource-variable/archivo/files/archivo-latin-wght-normal.woff2',
        'stencil' => 'node_modules/@fontsource-variable/big-shoulders-stencil-display/files/big-shoulders-stencil-display-latin-wght-normal.woff2',
        'dm-sans' => 'node_modules/@fontsource-variable/dm-sans/files/dm-sans-latin-opsz-normal.woff2',
        'icons' => 'resources/fonts/material-symbols-rounded.woff2',
    ];

    /**
     * The address of one built file, or null when the build does not hold it. For the logo on error pages and in emails, which must
     * still be produced when the build is missing or stale: the caller shows the name instead.
     */
    public static function asset(string $path): ?string
    {
        try {
            $url = (string) Vite::asset($path);

            return $url !== '' ? $url : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

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

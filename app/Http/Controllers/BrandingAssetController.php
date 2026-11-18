<?php

namespace App\Http\Controllers;

use App\Support\Branding\PlatformBrandingView;
use Illuminate\Http\Response;

/**
 * R-54: the bundled, optimised imagery for a platform's sign-in pages (its own real screen, blurred so no demo data is
 * legible, in three widths; and its original dark-ground logo where it has one). Served from InfoDot's own origin out of
 * the repository's resources folder. The visitor chooses a platform key and one file name from a fixed pattern; nothing
 * else can be named, and the key must be a registry platform.
 */
class BrandingAssetController extends Controller
{
    public const FILE = '(hero|background)-(480|960|1600)\.webp|logo-on-dark\.png';

    public function __invoke(string $slug, string $file): Response
    {
        abort_unless(config('ecosystem.platforms.'.$slug) !== null && preg_match('/\A('.self::FILE.')\z/', $file) === 1, 404);

        $path = PlatformBrandingView::bundleDirectory($slug).'/'.$file;
        abort_unless(is_file($path), 404);

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => str_ends_with($file, '.png') ? 'image/png' : 'image/webp',
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

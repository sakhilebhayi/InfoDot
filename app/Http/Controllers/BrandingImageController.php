<?php

namespace App\Http\Controllers;

use App\Models\DotPlatform;
use App\Services\Sso\BrandingImages;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * R-54: serves a platform's branding image from InfoDot's own origin (the strict content security policy allows
 * nothing else). The visitor chooses only a platform slug and one of three slots; the file path comes from the
 * database and must match the exact shape the upload service writes, so no path can be steered. The version in the
 * address makes the long cache safe.
 */
class BrandingImageController extends Controller
{
    private const TYPES = ['png' => 'image/png', 'webp' => 'image/webp'];

    public function __invoke(string $slug, string $kind): Response
    {
        $column = ['logo' => 'logo_path', 'hero' => 'hero_path', 'background' => 'background_path'][$kind] ?? null;
        $platform = $column ? DotPlatform::query()->where('slug', $slug)->first() : null;
        $path = $platform?->branding?->{$column};

        abort_unless(is_string($path) && BrandingImages::isStoredPath($path, $slug, $kind), 404);

        $disk = Storage::disk(BrandingImages::DISK);
        abort_unless($disk->exists($path), 404);

        return response($disk->get($path), 200, [
            'Content-Type' => self::TYPES[pathinfo($path, PATHINFO_EXTENSION)],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}

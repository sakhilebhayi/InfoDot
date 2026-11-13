<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves profile pictures from the public disk. Their address is /storage/profile-photos/<random name>, which a
 * web-root link to storage/app/public would normally serve. This host's web root is not the app's public folder and
 * has no such link, so those addresses reached nothing and saved pictures never appeared. Answering here needs no
 * host set-up; where a real link exists the web server serves the file first and this is never reached.
 * Only this one folder is served, and only names in the shape the app writes.
 */
class ProfilePhotoController extends Controller
{
    private const TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    public function __invoke(string $file): Response
    {
        $disk = Storage::disk((string) config('jetstream.profile_photo_disk', 'public'));
        $path = 'profile-photos/'.$file;

        abort_unless($disk->exists($path), 404);

        return response((string) $disk->get($path), 200, [
            'Content-Type' => self::TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))],
            'Cache-Control' => 'public, max-age=2592000, immutable', // names are random and never reused
        ]);
    }
}

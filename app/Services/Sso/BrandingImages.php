<?php

namespace App\Services\Sso;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * R-54: the only way a branding image enters the system. Strict on purpose, because the file is served to every
 * visitor of a sign-in page: the type is read from the file's own bytes (the name and the sent MIME type are not
 * believed), SVG is refused outright (it can carry script), the size and dimensions are bounded, and the image is
 * decoded and written again, so nothing but pixels survives (no metadata, no appended payload, no polyglot).
 * Files are stored on the private disk under a fixed folder and served by BrandingImageController, never by a
 * path a visitor chooses.
 */
class BrandingImages
{
    public const DISK = 'local';

    public const FOLDER = 'platform-branding';

    public const KINDS = ['logo', 'hero', 'background'];

    /** kind => [max bytes, min width, min height, max width, max height] */
    private const LIMITS = [
        'logo' => [512 * 1024, 64, 64, 2400, 2400],
        'hero' => [2 * 1024 * 1024, 800, 500, 3840, 2560],
        'background' => [2 * 1024 * 1024, 800, 500, 3840, 2560],
    ];

    private const TYPES = ['image/png' => IMAGETYPE_PNG, 'image/jpeg' => IMAGETYPE_JPEG, 'image/webp' => IMAGETYPE_WEBP];

    /** Validate, re-encode and store. Returns the stored relative path. */
    public function store(UploadedFile $file, string $slug, string $kind, string $field): string
    {
        if (! in_array($kind, self::KINDS, true) || preg_match('/\A[a-z0-9-]{1,40}\z/', $slug) !== 1) {
            $this->refuse($field, 'That image slot does not exist.');
        }

        [$max, $minW, $minH, $maxW, $maxH] = self::LIMITS[$kind];

        if (! $file->isValid() || $file->getSize() === false || $file->getSize() > $max || $file->getSize() < 1) {
            $this->refuse($field, 'The image must be no larger than '.number_format($max / 1024).' KB.');
        }

        $bytes = (string) file_get_contents($file->getRealPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';

        if (! array_key_exists($mime, self::TYPES)) {
            $this->refuse($field, 'Use a PNG, JPEG or WebP image. SVG and other types are not accepted.');
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false || $info[2] !== self::TYPES[$mime]) {
            $this->refuse($field, 'That file is not a readable image.');
        }

        [$width, $height] = $info;

        if ($width < $minW || $height < $minH || $width > $maxW || $height > $maxH) {
            $this->refuse($field, "The image must be between {$minW}x{$minH} and {$maxW}x{$maxH} pixels. This one is {$width}x{$height}.");
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            $this->refuse($field, 'That file is not a readable image.');
        }

        imagesavealpha($image, true);
        ob_start();
        // A logo keeps its transparency as PNG; photographs and backgrounds become WebP.
        $ext = $kind === 'logo' ? 'png' : 'webp';
        $ok = $kind === 'logo' ? imagepng($image, null, 9) : imagewebp($image, null, 82);
        $out = (string) ob_get_clean();
        imagedestroy($image);

        if (! $ok || $out === '') {
            $this->refuse($field, 'The image could not be processed. Try another file.');
        }

        $path = self::FOLDER.'/'.$slug.'/'.$kind.'-'.substr(hash('sha256', $out), 0, 16).'.'.$ext;
        Storage::disk(self::DISK)->put($path, $out);

        return $path;
    }

    /** Whether a stored path is one this class could have written (a guard for the controller that serves it). */
    public static function isStoredPath(string $path, string $slug, string $kind): bool
    {
        return preg_match('#\A'.preg_quote(self::FOLDER.'/'.$slug.'/'.$kind, '#').'-[0-9a-f]{16}\.(png|webp)\z#', $path) === 1;
    }

    /** @return never */
    private function refuse(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

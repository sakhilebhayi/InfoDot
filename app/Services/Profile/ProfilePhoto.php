<?php

namespace App\Services\Profile;

use App\Models\User;
use App\Support\ImageBudget;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores a person's profile picture. Phones hand over 3 to 8 MB photos of 12 to 48 megapixels; the picture is shown
 * at a few dozen pixels. So the upload is re-encoded to a small JPEG (which also drops the camera's location data)
 * and that copy is what is kept. The previous picture's file is removed once the new one is saved.
 */
class ProfilePhoto
{
    public const MAX_KILOBYTES = 10240;

    public const MIMES = 'jpg,jpeg,png,webp';

    private const EDGE = 640;

    /** A file this small is kept as it is when it cannot be re-encoded here. */
    private const KEEP_AS_IS_BYTES = 1024 * 1024;

    public function store(User $user, UploadedFile $file): void
    {
        $disk = Storage::disk($this->disk());
        $previous = $user->profile_photo_path;

        $small = $this->small($file);

        if ($small !== null) {
            $path = 'profile-photos/'.Str::random(40).'.jpg';
            $saved = $disk->put($path, $small, 'public');
        } elseif ($file->getSize() <= self::KEEP_AS_IS_BYTES) {
            $path = $file->storePublicly('profile-photos', ['disk' => $this->disk()]);
            $saved = $path !== false;
        } else {
            throw ValidationException::withMessages(['photo' => 'That picture is too large to process. Choose a smaller one.']);
        }

        if (! $saved) {
            throw ValidationException::withMessages(['photo' => 'Your picture could not be saved. Try again.']);
        }

        $user->forceFill(['profile_photo_path' => $path])->save();

        if ($previous && $previous !== $path) {
            $disk->delete($previous);
        }
    }

    /** The picture as JPEG bytes no larger than EDGE on its long side, or null when it cannot be made here. */
    private function small(UploadedFile $file): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $info = @getimagesize($file->getRealPath());
        if ($info === false || ! ImageBudget::fits((int) $info[0], (int) $info[1])) {
            return null;
        }

        [$width, $height] = [(int) $info[0], (int) $info[1]];

        try {
            $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
            if ($source === false) {
                return null;
            }

            $source = $this->upright($source, $file, $width, $height);
            [$width, $height] = [imagesx($source), imagesy($source)];

            $scale = min(1, self::EDGE / max($width, $height));
            $w = max(1, (int) round($width * $scale));
            $h = max(1, (int) round($height * $scale));

            $small = imagecreatetruecolor($w, $h);
            imagefill($small, 0, 0, (int) imagecolorallocate($small, 255, 255, 255)); // flatten transparency
            imagecopyresampled($small, $source, 0, 0, 0, 0, $w, $h, $width, $height);

            ob_start();
            imagejpeg($small, null, 85);

            return (string) ob_get_clean() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Phone cameras store the sensor's orientation beside the pixels; without this, portraits come out sideways. */
    private function upright(\GdImage $image, UploadedFile $file, int $width, int $height): \GdImage
    {
        if (! function_exists('exif_read_data') || ! ImageBudget::fits($width, $height)) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
        $degrees = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        if ($degrees === 0) {
            return $image;
        }

        $turned = imagerotate($image, $degrees, 0);

        return $turned === false ? $image : $turned;
    }

    private function disk(): string
    {
        return (string) config('jetstream.profile_photo_disk', 'public');
    }
}

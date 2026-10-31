<?php

namespace App\Services\Attachments;

use App\Models\Attachment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Business media (R-29 Part A): a company's logo, cover photograph and gallery, stored as
 * attachments bound to the team. Every upload is decoded, cropped and re-encoded with GD — the
 * stored file is the processed derivative, never the raw upload — and replaced branding is kept
 * on the record (`superseded_at`), not deleted: that history is what later impersonation review
 * compares against. Gallery rows carry a caption; everything is served only through the
 * permission-checked attachment routes.
 */
class TeamMediaService
{
    public const ROLES = ['logo', 'cover', 'gallery'];

    /** Output geometry per role: logo square, cover wide, gallery bounded by longest edge. */
    private const LOGO_SIZE = 512;

    private const COVER_W = 1600;

    private const COVER_H = 500;

    private const GALLERY_MAX = 1600;

    /**
     * Store one piece of business media. $crop is an optional fractional rectangle
     * ['x' => 0..1, 'y' => 0..1, 'w' => 0..1, 'h' => 0..1] of the source image; without it the
     * image is centre-cropped to the role's shape.
     */
    public function put(User $user, Team $team, string $role, UploadedFile $file, ?array $crop = null, ?string $caption = null): Attachment
    {
        if (! in_array($role, self::ROLES, true)) {
            throw ValidationException::withMessages(['role' => 'Unknown image role.']);
        }

        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages(['file' => 'Image processing is not available on this server right now.']);
        }

        if (! $file->isValid() || $file->getSize() <= 0) {
            throw ValidationException::withMessages(['file' => 'The upload did not arrive intact. Try again.']);
        }

        $max = (int) config('attachments.kinds.image.max_bytes');
        if ($file->getSize() > $max) {
            throw ValidationException::withMessages(['file' => 'An image may be at most '.round($max / 1048576).' MB.']);
        }

        $mime = strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()));
        if (! array_key_exists($mime, (array) config('attachments.kinds.image.mimes'))) {
            throw ValidationException::withMessages(['file' => 'Only a JPEG, PNG, WebP or GIF image can be used here.']);
        }

        $info = @getimagesize($file->getRealPath());
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw ValidationException::withMessages(['file' => 'That image could not be read.']);
        }
        if ((int) config('attachments.max_image_pixels') < $info[0] * $info[1]) {
            throw ValidationException::withMessages(['file' => 'That image has too many pixels to handle safely. Export a smaller copy.']);
        }

        [$minW, $minH] = match ($role) {
            'logo' => [128, 128],
            'cover' => [800, 250],
            default => [320, 240],
        };
        if ($info[0] < $minW || $info[1] < $minH) {
            throw ValidationException::withMessages(['file' => 'This image is too small for a '.$role.' — at least '.$minW.'×'.$minH.' pixels.']);
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw ValidationException::withMessages(['file' => 'That image could not be decoded.']);
        }

        [$image, $outW, $outH] = $this->render($source, $info[0], $info[1], $role, $this->cleanCrop($crop));
        imagedestroy($source);

        // A logo keeps PNG transparency; photographs are flattened to JPEG.
        $asPng = $role === 'logo' && in_array($mime, ['image/png', 'image/webp', 'image/gif'], true);
        ob_start();
        $asPng ? imagepng($image, null, 8) : imagejpeg($image, null, 82);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $uuid = (string) Str::uuid();
        $path = 'attachments/branding/'.now()->format('Y/m').'/'.$uuid.'.'.($asPng ? 'png' : 'jpg');
        if (! Storage::disk(Attachment::DISK)->put($path, $bytes)) {
            throw ValidationException::withMessages(['file' => 'The image could not be stored. Try again.']);
        }

        $attachment = new Attachment;
        $attachment->uuid = $uuid;
        $attachment->user_id = $user->id;
        $attachment->team_id = $team->id;
        $attachment->kind = Attachment::IMAGE;
        $attachment->role = $role;
        $attachment->path = $path;
        $attachment->thumb_path = null; // branding derivatives are already display-sized
        $attachment->name = Str::limit($team->name, 120, '').' '.$role.($asPng ? '.png' : '.jpg');
        $attachment->mime = $asPng ? 'image/png' : 'image/jpeg';
        $attachment->size = strlen($bytes);
        $attachment->caption = $role === 'gallery' ? ($caption !== null ? Str::limit(trim($caption), 500, '') : null) : null;
        $attachment->meta = ['width' => $outW, 'height' => $outH];
        $attachment->attachable()->associate($team);

        // One current logo and one current cover; the gallery accumulates. History stays.
        if ($role !== 'gallery') {
            Attachment::query()
                ->where('attachable_type', 'team')->where('attachable_id', $team->id)
                ->where('role', $role)->whereNull('superseded_at')
                ->update(['superseded_at' => now()]);
        }

        $attachment->save();

        return $attachment;
    }

    /** Take one piece of media off the profile. The row and file stay, as history. */
    public function remove(Team $team, Attachment $attachment): void
    {
        abort_unless(
            $attachment->attachable_type === 'team'
            && (int) $attachment->attachable_id === (int) $team->id
            && $attachment->role !== null
            && $attachment->superseded_at === null,
            404
        );

        $attachment->forceFill(['superseded_at' => now()])->save();
    }

    /** @return array{0: \GdImage, 1: int, 2: int} */
    private function render(\GdImage $source, int $sw, int $sh, string $role, ?array $crop): array
    {
        // The source window to read from: the given crop, else a centred window of the right shape.
        [$targetW, $targetH] = match ($role) {
            'logo' => [self::LOGO_SIZE, self::LOGO_SIZE],
            'cover' => [self::COVER_W, self::COVER_H],
            default => [0, 0], // gallery: keep the image's own shape
        };

        if ($role === 'gallery') {
            $scale = min(1, self::GALLERY_MAX / max($sw, $sh));
            $w = max(1, (int) round($sw * $scale));
            $h = max(1, (int) round($sh * $scale));
            $out = imagecreatetruecolor($w, $h);
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            imagecopyresampled($out, $source, 0, 0, 0, 0, $w, $h, $sw, $sh);

            return [$out, $w, $h];
        }

        $aspect = $targetW / $targetH;

        if ($crop !== null) {
            $cx = (int) round($crop['x'] * $sw);
            $cy = (int) round($crop['y'] * $sh);
            $cw = max(1, (int) round($crop['w'] * $sw));
            $ch = max(1, (int) round($crop['h'] * $sh));
            $cw = min($cw, $sw - $cx);
            $ch = min($ch, $sh - $cy);
        } else {
            // Centre-crop to the target shape.
            if ($sw / $sh > $aspect) {
                $ch = $sh;
                $cw = max(1, (int) round($sh * $aspect));
                $cx = (int) floor(($sw - $cw) / 2);
                $cy = 0;
            } else {
                $cw = $sw;
                $ch = max(1, (int) round($sw / $aspect));
                $cx = 0;
                $cy = (int) floor(($sh - $ch) / 2);
            }
        }

        // Never upscale past the source: shrink the output to what the window can honestly fill.
        $outW = min($targetW, max(1, $cw));
        $outH = max(1, (int) round($outW / $aspect));

        $out = imagecreatetruecolor($outW, $outH);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
        imagealphablending($out, true);
        imagecopyresampled($out, $source, 0, 0, $cx, $cy, $outW, $outH, $cw, $ch);
        imagealphablending($out, false);

        return [$out, $outW, $outH];
    }

    /** @return array{x: float, y: float, w: float, h: float}|null */
    private function cleanCrop(?array $crop): ?array
    {
        if ($crop === null) {
            return null;
        }

        $x = (float) ($crop['x'] ?? -1);
        $y = (float) ($crop['y'] ?? -1);
        $w = (float) ($crop['w'] ?? 0);
        $h = (float) ($crop['h'] ?? 0);

        if ($x < 0 || $y < 0 || $w <= 0.01 || $h <= 0.01 || $x + $w > 1.0001 || $y + $h > 1.0001) {
            throw ValidationException::withMessages(['crop' => 'The crop selection did not make sense — try repositioning again.']);
        }

        return ['x' => $x, 'y' => $y, 'w' => min($w, 1 - $x), 'h' => min($h, 1 - $y)];
    }
}

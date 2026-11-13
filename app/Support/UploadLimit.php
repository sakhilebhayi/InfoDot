<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The words for an upload PHP itself refused or damaged, before any of the application's own rules ran. A request over
 * post_max_size reaches Laravel empty and a file over upload_max_filesize arrives as an error code; both used to end
 * as "failed to upload", which told a person to retry something that could never work. The limit quoted is the
 * smaller of the two settings, because that is the size a single file must stay under.
 */
class UploadLimit
{
    /** Whole megabytes PHP accepts for one file, or null when neither setting limits it. */
    public static function megabytes(): ?int
    {
        $bytes = UploadedFile::getMaxFilesize();

        return $bytes >= PHP_INT_MAX ? null : max(1, (int) floor($bytes / 1048576));
    }

    public static function tooLargeMessage(): string
    {
        $limit = self::megabytes();

        return 'That file is too large to upload.'.($limit === null ? '' : ' The limit is '.$limit.' MB.');
    }

    /** What to tell the person for one of PHP's UPLOAD_ERR_* codes. */
    public static function messageFor(int $error): string
    {
        return match (true) {
            in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) => self::tooLargeMessage(),
            self::isServerFault($error) => 'We could not save that file because of a problem on our side. Please try again in a few minutes.',
            default => 'The upload was cut off before it finished. Check your connection and try again.',
        };
    }

    /** No temporary folder, a failed write or an extension stopping the upload: nothing the person can fix, and worth an error in the log. */
    public static function isServerFault(int $error): bool
    {
        return in_array($error, [UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION], true);
    }
}

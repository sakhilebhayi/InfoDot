<?php

namespace App\Support;

/**
 * Can this process decode an image of the given size? GD holds a decoded picture at about 4 bytes a pixel, plus
 * working copies, so a 24-megapixel phone photo needs around 120 MB. Running past PHP's memory limit is a fatal
 * error that no catch block sees: the person gets a 500. Callers ask here first and take another path when the
 * answer is no.
 */
class ImageBudget
{
    private const BYTES_PER_PIXEL = 5;

    private const HEADROOM = 24 * 1024 * 1024;

    public static function fits(int $width, int $height): bool
    {
        if ($width < 1 || $height < 1) {
            return false;
        }

        $limit = self::limit();
        if ($limit === null) {
            return true;
        }

        return $width * $height * self::BYTES_PER_PIXEL + self::HEADROOM <= $limit - memory_get_usage(true);
    }

    /** PHP's memory limit in bytes, or null when there is none. */
    private static function limit(): ?int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return null;
        }

        $number = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}

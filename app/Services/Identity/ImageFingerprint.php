<?php

namespace App\Services\Identity;

/**
 * A 64-bit difference hash (dHash) made with GD alone (the host has no image library): the
 * picture shrunk to 9×8 greys, each bit saying whether a pixel is brighter than its neighbour.
 * Re-encoding, resizing and light recompression barely move it, so a copied logo lands within
 * a few bits of the original (R-29 §17). It is a similarity signal, never proof.
 */
final class ImageFingerprint
{
    public static function of(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }

        $small = imagecreatetruecolor(9, 8);
        imagecopyresampled($small, $src, 0, 0, 0, 0, 9, 8, imagesx($src), imagesy($src));
        imagedestroy($src);

        $bits = '';
        for ($y = 0; $y < 8; $y++) {
            $prev = null;
            for ($x = 0; $x < 9; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $grey = (int) round(0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF));
                if ($prev !== null) {
                    $bits .= $grey > $prev ? '1' : '0';
                }
                $prev = $grey;
            }
        }
        imagedestroy($small);

        return implode('', array_map(fn ($chunk) => str_pad(dechex((int) bindec($chunk)), 2, '0', STR_PAD_LEFT), str_split($bits, 8)));
    }

    /** Bits that differ between two fingerprints (0 = identical, 64 = opposite). */
    public static function distance(string $a, string $b): int
    {
        $d = 0;
        foreach (str_split($a, 2) as $i => $byte) {
            $d += substr_count(decbin(hexdec($byte) ^ hexdec(substr($b, $i * 2, 2))), '1');
        }

        return $d;
    }
}

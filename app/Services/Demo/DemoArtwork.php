<?php

namespace App\Services\Demo;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Draws the pictures demonstration content needs (R-49) with GD, so nothing is downloaded and nothing is a stock
 * photograph: a stencilled owner code on graphite for a logo, a container-wall cover carrying the same code and
 * the word DEMONSTRATION, and an initial on an area colour for a person. Letters are drawn from a small built-in
 * block alphabet with stencil bridges, because the host is not guaranteed a font file. Colours are the Yard's
 * tokens (resources/css/infodot.css). Each picture is handed over as an upload, so it goes through the same
 * media pipeline as a picture a person chose.
 */
class DemoArtwork
{
    private const GRAPHITE = [0x1B, 0x21, 0x24];

    private const GRAPHITE_RIB = [0x23, 0x2B, 0x2F];

    private const STENCIL = [0xE7, 0xA7, 0x17];

    private const INK = [0xF6, 0xF4, 0xEE];

    /** The Yard's area colours, used as a person's ground. */
    public const AREAS = [
        'knowledge' => [0x1D, 0x4F, 0x91],
        'files' => [0xA6, 0x3A, 0x2B],
        'documents' => [0x2F, 0x6A, 0x4A],
        'people' => [0x56, 0x63, 0x6B],
    ];

    /** 5x7 block letters; each row is five cells, "#" filled. */
    private const GLYPHS = [
        'A' => ['.###.', '#...#', '#...#', '#####', '#...#', '#...#', '#...#'],
        'B' => ['####.', '#...#', '#...#', '####.', '#...#', '#...#', '####.'],
        'C' => ['.####', '#....', '#....', '#....', '#....', '#....', '.####'],
        'D' => ['####.', '#...#', '#...#', '#...#', '#...#', '#...#', '####.'],
        'E' => ['#####', '#....', '#....', '####.', '#....', '#....', '#####'],
        'F' => ['#####', '#....', '#....', '####.', '#....', '#....', '#....'],
        'G' => ['.####', '#....', '#....', '#..##', '#...#', '#...#', '.###.'],
        'H' => ['#...#', '#...#', '#...#', '#####', '#...#', '#...#', '#...#'],
        'I' => ['#####', '..#..', '..#..', '..#..', '..#..', '..#..', '#####'],
        'J' => ['..###', '....#', '....#', '....#', '....#', '#...#', '.###.'],
        'K' => ['#...#', '#..#.', '#.#..', '##...', '#.#..', '#..#.', '#...#'],
        'L' => ['#....', '#....', '#....', '#....', '#....', '#....', '#####'],
        'M' => ['#...#', '##.##', '#.#.#', '#.#.#', '#...#', '#...#', '#...#'],
        'N' => ['#...#', '##..#', '#.#.#', '#.#.#', '#..##', '#...#', '#...#'],
        'O' => ['.###.', '#...#', '#...#', '#...#', '#...#', '#...#', '.###.'],
        'P' => ['####.', '#...#', '#...#', '####.', '#....', '#....', '#....'],
        'Q' => ['.###.', '#...#', '#...#', '#...#', '#.#.#', '#..#.', '.##.#'],
        'R' => ['####.', '#...#', '#...#', '####.', '#.#..', '#..#.', '#...#'],
        'S' => ['.####', '#....', '#....', '.###.', '....#', '....#', '####.'],
        'T' => ['#####', '..#..', '..#..', '..#..', '..#..', '..#..', '..#..'],
        'U' => ['#...#', '#...#', '#...#', '#...#', '#...#', '#...#', '.###.'],
        'V' => ['#...#', '#...#', '#...#', '#...#', '#...#', '.#.#.', '..#..'],
        'W' => ['#...#', '#...#', '#...#', '#.#.#', '#.#.#', '##.##', '#...#'],
        'X' => ['#...#', '#...#', '.#.#.', '..#..', '.#.#.', '#...#', '#...#'],
        'Y' => ['#...#', '#...#', '.#.#.', '..#..', '..#..', '..#..', '..#..'],
        'Z' => ['#####', '....#', '...#.', '..#..', '.#...', '#....', '#####'],
        '0' => ['.###.', '#...#', '#..##', '#.#.#', '##..#', '#...#', '.###.'],
        '1' => ['..#..', '.##..', '..#..', '..#..', '..#..', '..#..', '.###.'],
        '2' => ['.###.', '#...#', '....#', '...#.', '..#..', '.#...', '#####'],
        '3' => ['####.', '....#', '....#', '.###.', '....#', '....#', '####.'],
        '4' => ['#...#', '#...#', '#...#', '#####', '....#', '....#', '....#'],
        '5' => ['#####', '#....', '####.', '....#', '....#', '#...#', '.###.'],
        '6' => ['.###.', '#....', '#....', '####.', '#...#', '#...#', '.###.'],
        '7' => ['#####', '....#', '...#.', '..#..', '..#..', '..#..', '..#..'],
        '8' => ['.###.', '#...#', '#...#', '.###.', '#...#', '#...#', '.###.'],
        '9' => ['.###.', '#...#', '#...#', '.####', '....#', '....#', '.###.'],
    ];

    /** A square logo: the owner code stencilled in gold on graphite, over a gold rule. */
    public function logo(string $code): UploadedFile
    {
        $size = 512;
        $image = $this->canvas($size, $size, self::GRAPHITE);
        $code = $this->letters($code, 4);

        $cell = (int) floor(($size - 96) / $this->cellsWide(strlen($code)));
        $width = $this->cellsWide(strlen($code)) * $cell;
        $this->write($image, $code, (int) (($size - $width) / 2), (int) (($size - 7 * $cell) / 2) - 12, $cell, self::STENCIL);
        imagefilledrectangle($image, 48, $size - 84, $size - 48, $size - 72, $this->colour($image, self::STENCIL));

        return $this->upload($image, 'demo-logo.png');
    }

    /** A wide cover: a ribbed container wall, the owner code large, and DEMONSTRATION along the bottom rule. */
    public function cover(string $code): UploadedFile
    {
        [$w, $h] = [1600, 500];
        $image = $this->canvas($w, $h, self::GRAPHITE);

        $rib = $this->colour($image, self::GRAPHITE_RIB);
        for ($x = 0; $x < $w; $x += 64) {
            imagefilledrectangle($image, $x, 0, $x + 30, $h, $rib);
        }

        $gold = $this->colour($image, self::STENCIL);
        imagefilledrectangle($image, 0, 0, $w, 13, $gold);
        imagefilledrectangle($image, 0, $h - 14, $w, $h, $gold);

        $code = $this->letters($code, 4);
        $cell = 36;
        $this->write($image, $code, 96, (int) (($h - 7 * $cell) / 2) - 16, $cell, self::STENCIL);

        $word = 'DEMONSTRATION';
        $small = 8;
        $this->write($image, $word, $w - 96 - $this->cellsWide(strlen($word)) * $small, $h - 14 - 44 - 7 * $small, $small, self::INK);

        return $this->upload($image, 'demo-cover.png');
    }

    /**
     * A person's picture: their initial on one of the Yard's area colours.
     *
     * @param  array{0: int, 1: int, 2: int}  $ground
     */
    public function portrait(string $name, array $ground): UploadedFile
    {
        $size = 640;
        $image = $this->canvas($size, $size, $ground);
        $initial = $this->letters($name, 1);

        $cell = 56;
        $this->write($image, $initial, (int) (($size - 5 * $cell) / 2), (int) (($size - 7 * $cell) / 2), $cell, self::INK);

        return $this->upload($image, 'demo-portrait.png');
    }

    /** Only what the alphabet can draw, upper case, at most $max characters; "X" when nothing is left. */
    private function letters(string $text, int $max): string
    {
        $clean = substr((string) preg_replace('/[^A-Z0-9]/', '', strtoupper($text)), 0, $max);

        return $clean === '' ? 'X' : $clean;
    }

    /** Letters are five cells wide with one cell between them. */
    private function cellsWide(int $letters): int
    {
        return $letters * 5 + max(0, $letters - 1);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function write(\GdImage $image, string $text, int $x, int $y, int $cell, array $rgb): void
    {
        $ink = $this->colour($image, $rgb);
        // The stencil bridge: a thin break through each letter where a real stencil would hold together.
        $bridge = max(1, (int) round($cell / 6));

        foreach (str_split($text) as $i => $char) {
            $left = $x + $i * 6 * $cell;

            foreach (self::GLYPHS[$char] ?? [] as $row => $cells) {
                foreach (str_split($cells) as $col => $on) {
                    if ($on !== '#') {
                        continue;
                    }

                    $x1 = $left + $col * $cell;
                    $y1 = $y + $row * $cell;
                    imagefilledrectangle($image, $x1, $y1, $x1 + $cell - 1, $y1 + $cell - ($row === 3 ? $bridge + 1 : 1), $ink);
                }
            }
        }
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function canvas(int $width, int $height, array $rgb): \GdImage
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('The GD extension is needed to draw the demonstration pictures.');
        }

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new RuntimeException('The demonstration picture could not be drawn.');
        }
        imagefilledrectangle($image, 0, 0, $width, $height, $this->colour($image, $rgb));

        return $image;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function colour(\GdImage $image, array $rgb): int
    {
        return (int) imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
    }

    /** The drawn picture as a PNG in the system's temporary folder, wrapped the way an upload arrives. */
    private function upload(\GdImage $image, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'infodot-demo-');
        if ($path === false || ! imagepng($image, $path)) {
            throw new RuntimeException('The demonstration picture could not be written.');
        }

        // "test" mode only says the file did not arrive through an HTTP request, which is true: it was drawn here.
        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}

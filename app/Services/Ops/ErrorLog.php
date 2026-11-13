<?php

namespace App\Services\Ops;

use Carbon\CarbonInterface;

/**
 * Reads the daily Laravel log for entries at ERROR or worse, so `ops:alert` and `ops:check` count the same thing in the same way.
 * Only the first line of an entry is ever looked at: the stack trace that follows it is skipped, and the context (which can name a
 * person) is cut off the message. Files are read as a stream from a byte position, so a large log costs time, not memory.
 */
class ErrorLog
{
    private const LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    /** The file the `daily` channel writes on the given day (laravel-2026-11-13.log). */
    public function path(CarbonInterface $day): string
    {
        $base = pathinfo((string) config('logging.channels.daily.path', storage_path('logs/laravel.log')));

        return ($base['dirname'] ?? '.').'/'.$base['filename'].'-'.$day->format('Y-m-d').(isset($base['extension']) ? '.'.$base['extension'] : '');
    }

    /**
     * Hand every error entry after $offset to $each and return the position reached. A missing file reads as empty; a file shorter
     * than $offset was replaced and is read from its start; a last line still being written is left for the next call.
     *
     * @param  callable(array{time: string, level: string, class: ?string, message: string}): void  $each
     */
    public function read(string $path, int $offset, callable $each): int
    {
        if (! is_file($path) || ! is_readable($path)) {
            return 0;
        }

        clearstatcache(true, $path);
        $offset = $offset > (int) filesize($path) ? 0 : $offset;
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return $offset;
        }

        try {
            fseek($handle, $offset);

            while (($line = fgets($handle)) !== false && str_ends_with($line, "\n")) {
                $offset += strlen($line);

                if (($entry = $this->parse($line)) !== null) {
                    $each($entry);
                }
            }
        } finally {
            fclose($handle);
        }

        return $offset;
    }

    /** How many error entries carry a time at or after $since (the time in the log is app time, as now() is). */
    public function countSince(CarbonInterface $since): int
    {
        $from = $since->format('Y-m-d H:i:s');
        $count = 0;

        for ($day = $since->copy()->startOfDay(); $day->lessThanOrEqualTo(now()); $day = $day->addDay()) {
            $this->read($this->path($day), 0, function (array $entry) use (&$count, $from) {
                $count += $entry['time'] >= $from ? 1 : 0;
            });
        }

        return $count;
    }

    /**
     * The first line of an error entry, in the plain format or as one JSON object (LOG_FORMAT=json); null for anything else.
     *
     * @return array{time: string, level: string, class: ?string, message: string}|null
     */
    public function parse(string $line): ?array
    {
        if (preg_match('/^\[(\d{4}-\d\d-\d\d)[ T](\d\d:\d\d:\d\d)[^\]]*\] [A-Za-z0-9_-]+\.('.implode('|', self::LEVELS).'): (.*)$/s', $line, $m) === 1) {
            return [
                'time' => $m[1].' '.$m[2],
                'level' => $m[3],
                'class' => preg_match('/\[object\] \(([A-Za-z0-9_\\\\]+)/', $m[4], $c) === 1 ? $c[1] : null,
                // The context starts at the first ` {"`; the message is what comes before it.
                'message' => trim((string) preg_replace('/( \[\])+\s*$/', '', explode(' {"', $m[4], 2)[0])),
            ];
        }

        if (! str_starts_with($line, '{') || ! is_array($json = json_decode($line, true)) || ! in_array($json['level_name'] ?? null, self::LEVELS, true)) {
            return null;
        }

        $class = $json['context']['exception']['class'] ?? null;

        return [
            'time' => substr(str_replace('T', ' ', (string) ($json['datetime'] ?? '')), 0, 19),
            'level' => (string) $json['level_name'],
            'class' => is_string($class) ? $class : null,
            'message' => trim((string) ($json['message'] ?? '')),
        ];
    }
}

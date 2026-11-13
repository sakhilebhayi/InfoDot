<?php

namespace App\Services\Ops;

use App\Mail\OpsAlert as OpsAlertMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells one person when something is failing, without an outside service: the error lines the daily log gained since the last look,
 * and the `ops:check` conditions that are not ok, in one plain email. It exists because a 500 on the host was otherwise found only
 * when somebody read the log.
 *
 * What it remembers (the log file and byte position reached, what is waiting to be sent, when it last sent) is one small file
 * under storage/framework. At most one email an hour: errors found in between are counted and go out with the next one. A check that
 * keeps failing is emailed once a day, not every hour. Nothing that names a person leaves the host: first lines only, context
 * removed, addresses and anything shaped like a token, hash, id or long number masked, 200 characters at most.
 */
class OpsAlert
{
    public const NO_CLASS = 'logged without an exception';

    private const SAMPLES = 5;

    private const SAMPLE_LENGTH = 200;

    private const CLASSES = 40;

    public function __construct(private readonly ErrorLog $log, private readonly OpsCheck $checks) {}

    /** One pass. Returns what happened: off, quiet, held, sent or failed. Never throws for a mail failure. */
    public function run(): string
    {
        $address = trim((string) config('ops.alert_address'));

        if ($address === '') {
            if (Cache::add('ops:alert:off:'.now()->format('Y-m-d'), 1, 90000)) {
                Log::info('ops.alert is off: OPS_ALERT_ADDRESS is not set, so nobody is emailed about errors.');
            }

            return 'off';
        }

        $state = $this->state();
        $new = $this->readNewErrors($state);
        $failing = $this->failingChecks();
        $today = now()->format('Y-m-d');
        $unreported = array_filter(array_keys($failing), fn (string $kind) => ($state['checks'][$kind] ?? null) !== $today);

        if ($state['pending']['total'] === 0 && $unreported === []) {
            $this->save($state);

            return 'quiet';
        }

        $every = (int) config('ops.alert_every_minutes', 60) * 60;

        if ($state['sent_at'] !== null && now()->timestamp - $state['sent_at'] < $every) {
            $state['pending']['held'] += $new;
            $this->save($state);

            return 'held';
        }

        try {
            Mail::to($address)->send(new OpsAlertMail(
                $state['pending']['total'],
                $state['pending']['held'],
                $state['sent_at'] === null ? null : Carbon::createFromTimestamp($state['sent_at'], config('app.timezone'))->format('H:i'),
                $state['pending']['classes'],
                $state['pending']['samples'],
                $failing,
            ));
        } catch (Throwable $e) {
            // A warning, not an error: an error line here would be counted by the next run and reported for ever. The exception
            // message is left out because a transport failure names the mail host and the mailbox.
            Log::warning('ops.alert could not send its email; what it found is kept for the next run.', ['exception' => $e::class]);
            $this->save($state);

            return 'failed';
        }

        $state['pending'] = $this->nothingPending();
        $state['sent_at'] = now()->timestamp;
        $state['checks'] = array_fill_keys(array_keys($failing), $today);
        $this->save($state);

        return 'sent';
    }

    /** Email addresses and anything that looks like a secret, an id or a long number are replaced; the result is cut to 200 characters. */
    public static function mask(string $text): string
    {
        $text = (string) preg_replace([
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
            '/\b(password|passwd|pwd|secret|token|api[_-]?key|key|authorization|signature)(["\']?\s*[=:]\s*["\']?)[^\s"\'&,;)]{4,}/i',
            '/\b(bearer|basic)(\s+)[A-Za-z0-9._~+\/=-]{8,}/i',
            '/\beyJ[A-Za-z0-9_-]{8,}(\.[A-Za-z0-9_-]+){1,2}/',
            '/\$2[aby]\$\d\d\$[.\/A-Za-z0-9]{53}/',
            '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i',
            '/\b[A-Fa-f0-9]{16,}\b/',
            '/(?<![A-Za-z0-9_\\\\\/-])(?=[A-Za-z0-9_+\/=-]*\d)(?=[A-Za-z0-9_+\/=-]*[A-Za-z])[A-Za-z0-9_+=-]{24,}(?![A-Za-z0-9_\\\\\/-])/',
            '/\b\d{1,3}(\.\d{1,3}){3}\b/',
            '/(?<![A-Za-z0-9])\+?\d(?: ?\d){6,}(?![A-Za-z0-9])/',
            '#(/home\d*|/Users|/var/www)/[^/\s]+#',
        ], ['[email]', '$1$2[masked]', '$1$2[masked]', '[token]', '[hash]', '[id]', '[hash]', '[token]', '[ip]', '[number]', '~'], $text);

        return mb_substr((string) preg_replace('/\s+/', ' ', $text), 0, self::SAMPLE_LENGTH);
    }

    /**
     * Read what the log gained since the last run into $state['pending'] and move the position on. After midnight the rest of the
     * file that was being followed is read first, then the new day's file from its start.
     *
     * @param  array<string, mixed>  $state
     */
    private function readNewErrors(array &$state): int
    {
        $today = $this->log->path(now());
        $new = 0;

        $collect = function (array $entry) use (&$state, &$new) {
            // ops:check logs each failing check once a day; those reach the email through the checks section, not as errors.
            if (str_starts_with($entry['message'], 'ops.check') || str_starts_with($entry['message'], 'Operations check:')) {
                return;
            }

            $new++;
            $pending = &$state['pending'];
            $pending['total']++;
            $class = $entry['class'] ?? self::NO_CLASS;

            if (isset($pending['classes'][$class]) || count($pending['classes']) < self::CLASSES) {
                $pending['classes'][$class] = ($pending['classes'][$class] ?? 0) + 1;
            }

            $what = self::mask($entry['message']);

            if (count($pending['samples']) < self::SAMPLES && ! isset($pending['seen'][$key = md5($class.'|'.$what)])) {
                $pending['seen'][$key] = 1;
                $pending['samples'][] = mb_substr(substr($entry['time'], 11).' '.$entry['level'].' '.($entry['class'] === null ? '' : $entry['class'].': ').$what, 0, self::SAMPLE_LENGTH);
            }
        };

        if ($state['file'] !== null && $state['file'] !== basename($today)) {
            $this->log->read(dirname($today).'/'.$state['file'], $state['offset'], $collect);
            $state['offset'] = 0;
        }

        $state['offset'] = $this->log->read($today, $state['offset'], $collect);
        $state['file'] = basename($today);

        return $new;
    }

    /** @return array<string, int> the checks that are not ok; a check that cannot run at all is one of them */
    private function failingChecks(): array
    {
        try {
            return array_filter($this->checks->run(), fn (int $count) => $count > 0);
        } catch (Throwable $e) {
            Log::warning('ops.alert could not run the operations check.', ['exception' => $e::class]);

            return ['ops_check_could_not_run' => 1];
        }
    }

    /** @return array{file: ?string, offset: int, sent_at: ?int, checks: array<string, string>, pending: array{total: int, held: int, classes: array<string, int>, samples: list<string>, seen: array<string, int>}} */
    private function state(): array
    {
        $path = (string) config('ops.alert_state_path');
        $saved = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $saved = is_array($saved) ? $saved : [];
        $pending = is_array($saved['pending'] ?? null) ? $saved['pending'] : [];

        return [
            'file' => isset($saved['file']) ? basename((string) $saved['file']) : null,
            'offset' => max(0, (int) ($saved['offset'] ?? 0)),
            'sent_at' => isset($saved['sent_at']) ? (int) $saved['sent_at'] : null,
            'checks' => array_map(strval(...), is_array($saved['checks'] ?? null) ? $saved['checks'] : []),
            'pending' => [
                'total' => max(0, (int) ($pending['total'] ?? 0)),
                'held' => max(0, (int) ($pending['held'] ?? 0)),
                'classes' => array_map(intval(...), is_array($pending['classes'] ?? null) ? $pending['classes'] : []),
                'samples' => array_values(array_map(strval(...), is_array($pending['samples'] ?? null) ? $pending['samples'] : [])),
                'seen' => array_map(intval(...), is_array($pending['seen'] ?? null) ? $pending['seen'] : []),
            ],
        ];
    }

    /** @return array{total: int, held: int, classes: array<string, int>, samples: list<string>, seen: array<string, int>} */
    private function nothingPending(): array
    {
        return ['total' => 0, 'held' => 0, 'classes' => [], 'samples' => [], 'seen' => []];
    }

    /** @param  array<string, mixed>  $state */
    private function save(array $state): void
    {
        $path = (string) config('ops.alert_state_path');

        if (@file_put_contents($path, json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            Log::warning('ops.alert could not save its position; the next run may repeat this one.', ['file' => basename($path)]);
        }
    }
}

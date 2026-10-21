<?php

namespace App\Console\Commands;

use App\Mail\PointsExpiryReminder;
use App\Models\User;
use App\Services\Points\ExpiryResultRefusedException;
use App\Services\Points\ExpiryWarning;
use App\Services\Points\PointsClient;
use App\Services\Points\PointsUnavailableException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails people before some of their Dot Points lapse. The points service holds no addresses, so InfoDot pulls the
 * warnings that are due, sends ONE plain email per warning and reports back what happened.
 *
 * Safety properties: a warning is never emailed twice (points_expiry_mail_log is written in the same transaction as
 * the send, so a failed report just reports "delivered" next run); a failed send is reported as "retry" and leaves no
 * log row; nothing is reported "delivered" unless a real mail transport accepted the message; and the output holds
 * counts only, never an address.
 */
class DeliverPointsExpiryWarnings extends Command
{
    /** Most warnings handled in one run, whatever --cap says. */
    public const CAP = 200;

    /** Stop the run after this many sends in a row fail: the mail transport is down, not the one message. */
    private const MAX_CONSECUTIVE_SEND_FAILURES = 3;

    protected $signature = 'points:deliver-expiry-warnings {--cap=200 : Most warnings to handle in this run (never more than 200)}';

    protected $description = 'Email people whose Dot Points are about to lapse, and report the result to the points service';

    private PointsClient $client;

    /** @var array<string, int> */
    private array $counts = ['sent' => 0, 'closed' => 0, 'retry' => 0, 'reported_again' => 0, 'unreported' => 0];

    public function handle(PointsClient $client): int
    {
        $this->client = $client;
        if (! $client->enabled()) {
            $this->info('The points service is not configured (DOPEMINE_URL and DOPEMINE_INGEST_SECRET); nothing to do.');

            return self::SUCCESS;
        }
        if (! $this->mailIsDeliverable()) {
            $this->warn('Mail not configured: the mailer is log, array or missing, or MAIL_FROM_ADDRESS is unset, so no email can be delivered. Nothing was fetched or reported; the warnings stay pending.');

            return self::SUCCESS;
        }

        $cap = max(1, min(self::CAP, (int) $this->option('cap')));
        $seen = [];
        $handled = 0;
        $sendFailures = 0;
        $status = self::SUCCESS;

        try {
            while ($handled < $cap) {
                $batch = array_values(array_filter(
                    $client->expiryWarnings(min(100, $cap - $handled)),
                    fn (ExpiryWarning $w) => ! isset($seen[(string) $w->warningId]),
                ));
                if ($batch === []) {
                    break;
                }

                foreach ($batch as $warning) {
                    if ($handled >= $cap) {
                        break 2;
                    }
                    $seen[(string) $warning->warningId] = true;
                    $handled++;

                    $outcome = $this->handleWarning($warning, $sendFailures);
                    if ($outcome === 'send_failed' && $sendFailures >= self::MAX_CONSECUTIVE_SEND_FAILURES) {
                        $this->warn('Mail sending failed '.self::MAX_CONSECUTIVE_SEND_FAILURES.' times in a row; stopping this run. The rest stay pending.');
                        break 2;
                    }
                    if ($outcome === 'unreachable') {
                        break 2;
                    }
                }
            }
        } catch (PointsUnavailableException $e) {
            // Nothing sensitive in the message: it is ours. Not configured/unreachable is routine; a rejection is not.
            $this->warn('The points service could not be used ('.$e->reason.'); the rest stay pending.');
            $status = in_array($e->reason, [PointsUnavailableException::REJECTED, PointsUnavailableException::MALFORMED], true) ? self::FAILURE : self::SUCCESS;
        }

        $c = $this->counts;
        $this->info("Emailed {$c['sent']}; closed without an email {$c['closed']}; to retry {$c['retry']}; reported again after an earlier send {$c['reported_again']}; could not report {$c['unreported']}.");

        return $status;
    }

    /**
     * @return string 'done' | 'send_failed' | 'unreachable'
     */
    private function handleWarning(ExpiryWarning $warning, int &$sendFailures): string
    {
        $id = (string) $warning->warningId;

        // Already emailed on an earlier run whose report failed: do not email again, just report it.
        if (DB::table('points_expiry_mail_log')->where('warning_id', $id)->exists()) {
            $this->counts['reported_again']++;

            return $this->report($warning, 'delivered', null);
        }

        $user = User::where('uuid', $warning->userUuid)->first();
        if ($user === null || ! $user->wantsPointsExpiryEmails() || ! filled($user->email) || $user->email_verified_at === null) {
            // Unknown person, opted out, or an address nobody has confirmed (and who therefore cannot reach the opt-out):
            // the warning is simply closed and no email is sent.
            return $this->report($warning, 'permanent_failure', 'closed');
        }

        try {
            $duplicate = false;
            DB::transaction(function () use ($user, $warning, $id, &$duplicate) {
                if (DB::table('points_expiry_mail_log')->insertOrIgnore(['warning_id' => $id, 'user_id' => $user->id, 'sent_at' => now()]) === 0) {
                    $duplicate = true; // Another run got here first.

                    return;
                }
                Mail::to($user)->send(new PointsExpiryReminder((string) $user->name, $warning->points, $warning->expiresOn));
            });
        } catch (Throwable $e) {
            // The transaction rolled back, so no log row exists. Log the class only: a transport's message can hold an address.
            Log::warning('points.expiry_mail_failed', ['exception' => $e::class]);
            $sendFailures++;

            return $this->report($warning, 'retry', 'retry') === 'unreachable' ? 'unreachable' : 'send_failed';
        }
        $sendFailures = 0;

        if ($duplicate) {
            $this->counts['reported_again']++;

            return $this->report($warning, 'delivered', null);
        }

        return $this->report($warning, 'delivered', 'sent');
    }

    /** @param ?string $count the counter to raise once the report is accepted (null: already counted) */
    private function report(ExpiryWarning $warning, string $outcome, ?string $count): string
    {
        try {
            $this->client->reportExpiryResult($warning->warningId, $outcome);
        } catch (ExpiryResultRefusedException $e) {
            // Unknown to the service (nothing left to close) or an invalid report. Either way, retrying will not change it.
            Log::warning('points.expiry_result_refused', ['reason' => $e->reason]);
        } catch (PointsUnavailableException) {
            // The report did not get through. If we sent an email, the log row makes the next run only re-report it.
            $this->counts['unreported']++;

            return 'unreachable';
        }
        if ($count !== null) {
            $this->counts[$count]++;
        }

        return 'done';
    }

    /**
     * Whether mail sent now would really leave the building: not the log or array transports, and for failover or
     * round-robin mailers none of the transports behind them may be one of those (a fallback to the log would look
     * like a delivery).
     */
    private function mailIsDeliverable(): bool
    {
        if (blank(config('mail.from.address')) || str_ends_with((string) config('mail.from.address'), '@example.com')) {
            return false;
        }

        return $this->mailerIsReal((string) config('mail.default'));
    }

    private function mailerIsReal(string $mailer, int $depth = 0): bool
    {
        $config = config("mail.mailers.{$mailer}");
        if (! is_array($config) || $depth > 3) {
            return false;
        }
        $transport = (string) ($config['transport'] ?? '');
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $inner = (array) ($config['mailers'] ?? []);

            return $inner !== [] && collect($inner)->every(fn ($m) => $this->mailerIsReal((string) $m, $depth + 1));
        }

        return $transport !== '' && ! in_array($transport, ['log', 'array'], true);
    }
}

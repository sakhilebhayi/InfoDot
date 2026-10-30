<?php

namespace App\Services\Ops;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The failures that used to be silent: events parked after their last retry, deliveries that never went out, refunds Billing never confirmed,
 * holds nobody released, and a scheduler that stopped. Counts only: no row, name or address is ever read out.
 *
 * `run()` returns the counts; `alert()` reports each non-zero kind once a day (to the log and, through the exception handler, to Sentry).
 * The scheduler heartbeat is separate because a dead scheduler cannot run this check: /ready exposes it to an outside monitor.
 */
class OpsCheck
{
    public const HEARTBEAT_KEY = 'ops:scheduler-heartbeat';

    private const OUTBOXES = ['reward_outbox' => 'points', 'participation_outbox' => 'memory', 'library_outcomes' => 'library'];

    /** @return array<string, int> */
    public function run(): array
    {
        $found = [];

        foreach (self::OUTBOXES as $table => $label) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $found["outbox_{$label}_parked"] = (int) DB::table($table)->whereNotNull('failed_at')->whereNull('sent_at')->count();
            $found["outbox_{$label}_backlog"] = (int) DB::table($table)->whereNull('sent_at')->whereNull('failed_at')->where('created_at', '<', now()->subHours(6))->count();
        }

        // A person who appeals a score or a removal is owed an answer; nothing else puts a clock on a moderator's queue.
        $found['score_appeals_open_14d'] = (int) (Schema::hasTable('reputation_appeals') ? DB::table('reputation_appeals')->where('status', 'open')->where('created_at', '<', now()->subDays(14))->count() : 0);
        $found['removal_appeals_open_7d'] = (int) (Schema::hasTable('seminar_removal_appeals') ? DB::table('seminar_removal_appeals')->where('status', 'open')->where('created_at', '<', now()->subDays(7))->count() : 0);

        $found['refunds_unconfirmed_24h'] = (int) DB::table('seminar_registrations')->whereNotNull('refund_requested_at')->whereNull('refunded_at')
            ->where('refund_requested_at', '<', now()->subDay())->whereIn('payment_status', ['paid', 'partially_refunded'])->count();
        $found['holds_not_released_1h'] = (int) DB::table('seminar_registrations')->where('status', 'pending_payment')->where('hold_expires_at', '<', now()->subHour())->count();

        return $found;
    }

    /** @param  array<string, int>  $found @return list<string> the kinds reported now */
    public function alert(array $found): array
    {
        $sent = [];

        foreach ($found as $kind => $count) {
            // Once a day per kind: a standing problem should be one alert, not 96.
            if ($count > 0 && Cache::add("ops:alerted:$kind:".now()->format('Y-m-d'), 1, 90000)) {
                $e = new \RuntimeException("Operations check: $kind = $count");
                Log::error('ops.check', ['kind' => $kind, 'count' => $count]);
                report($e);
                $sent[] = $kind;
            }
        }

        return $sent;
    }

    public function beat(): void
    {
        Cache::put(self::HEARTBEAT_KEY, now()->timestamp, 7200);
    }

    /** Minutes since the scheduler last ran; null when it has never been seen (a fresh install or a cleared cache). */
    public function minutesSinceHeartbeat(): ?int
    {
        $at = Cache::get(self::HEARTBEAT_KEY);

        return $at === null ? null : (int) floor((now()->timestamp - (int) $at) / 60);
    }
}

<?php

namespace App\Services\Ops;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;

/**
 * The failures that used to be silent: events parked after their last retry, deliveries that never went out, refunds Finance never confirmed,
 * holds nobody released, a scheduler that stopped, a folder the application cannot write to and a disk that is nearly full. Counts
 * only: no row, name or address is ever read out.
 *
 * `run()` returns the counts; `alert()` reports each non-zero kind once a day (to the log and, through the exception handler, to Sentry).
 * The scheduler heartbeat is separate because a dead scheduler cannot run this check: /ready exposes it to an outside monitor.
 */
class OpsCheck
{
    public const HEARTBEAT_KEY = 'ops:scheduler-heartbeat';

    private const OUTBOXES = ['reward_outbox' => 'points', 'participation_outbox' => 'memory', 'library_outcomes' => 'library', 'entitlement_outbox' => 'entitlements'];

    public function __construct(private readonly ErrorLog $log) {}

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

        // RFC 9116 requires a future Expires in security.txt; this starts shouting 30 days before it lapses (R-19).
        $found['security_txt_expiring_30d'] = $this->securityTxtExpiring() ? 1 : 0;

        // R-20: without the OIDC signing keys every platform sign-in fails; deploy generates them once.
        $found['sso_signing_keys_missing'] = is_file(Passport::keyPath('oauth-private.key')) ? 0 : 1;

        // R-50: the OAuth door finds a platform's entitlement by its registry slug. A slug that is not a key in config/ecosystem.php
        // cannot be judged, so that platform's sign-in is let through unchecked until the slug is corrected.
        $found['sso_platform_slug_not_in_registry'] = (int) (Schema::hasTable('dot_platforms')
            ? DB::table('dot_platforms')->whereNotIn('slug', array_map('strval', array_keys((array) config('ecosystem.platforms', []))))->count() : 0);

        // R-50: nothing may be paid without Dot.Finance's signed confirmation, and nothing may be on sale without a recorded approval.
        // Either count above zero means a row was written around the services and needs a person to look at it.
        if (Schema::hasTable('platform_payment_confirmations')) {
            $found['paid_entitlement_without_confirmation'] = (int) DB::table('platform_entitlements as e')->where('e.source', 'paid')->where('e.status', '!=', 'pending')
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('platform_payment_confirmations as c')->whereColumn('c.team_id', 'e.team_id')
                    ->whereColumn('c.platform_key', 'e.platform_key')->whereIn('c.event', ['paid', 'renewed'])->where('c.outcome', 'applied'))->count();

            $approved = DB::table('platform_catalogue_events')->where('type', 'price_approved');
            $found['plan_on_sale_without_approval_record'] = (int) DB::table('platform_plans')->whereNotNull('approved_at')->get(['platform_key', 'slug'])
                ->reject(fn ($plan) => (clone $approved)->where('subject_type', 'plan')->where('subject_key', $plan->platform_key.'/'.$plan->slug)->exists())->count()
                + (int) DB::table('platform_bundles')->whereNotNull('approved_at')->pluck('slug')
                    ->reject(fn ($slug) => (clone $approved)->where('subject_type', 'bundle')->where('subject_key', $slug)->exists())->count();

            // A trial or dated grant still open an hour after its end means the lifecycle command is not running.
            $found['entitlements_overdue_1h'] = (int) DB::table('platform_entitlements')->where(fn ($w) => $w
                ->where(fn ($q) => $q->where('status', 'trial')->where('trial_ends_at', '<', now()->subHour()))
                ->orWhere(fn ($q) => $q->where('status', 'active')->whereNotNull('ended_at')->where('ended_at', '<', now()->subHour())))->count();
        }

        // The host conditions behind uploads and sign-ups failing: a folder the web user cannot write to, and a full disk.
        foreach ($this->writablePaths() as $label => $path) {
            $found["{$label}_not_writable"] = $this->canWrite($path) ? 0 : 1;
        }

        if (($free = $this->freeBytes()) !== null) {
            $found['disk_space_low'] = $free < (float) config('ops.min_free_bytes') ? 1 : 0;
        }

        return $found;
    }

    /**
     * What is worth a look but is not a failure of its own: the command prints these as "warn", they do not change its exit code and
     * `alert()` never sees them. Errors in the log are reported by `ops:alert`; counted here as a problem, each day's report would
     * write an error line that the next day's count finds again.
     *
     * @return array<string, int>
     */
    public function warnings(): array
    {
        return ['errors_logged_24h' => $this->log->countSince(now()->subDay())];
    }

    /** @return array<string, string> */
    private function writablePaths(): array
    {
        return [
            'storage_app' => storage_path('app'),
            'profile_photos' => storage_path('app/public/profile-photos'),
            'storage_framework' => storage_path('framework'),
            'bootstrap_cache' => app()->bootstrapPath('cache'),
        ];
    }

    /**
     * Report only: nothing is created or written. A folder that does not exist yet (profile-photos before the first picture) is ok
     * when the nearest folder above it that does exist is writable, because the first upload creates it.
     */
    private function canWrite(string $path): bool
    {
        while (! file_exists($path) && dirname($path) !== $path) {
            $path = dirname($path);
        }

        return is_dir($path) && is_writable($path);
    }

    /** Free bytes on the disk that holds storage/, or null when PHP cannot tell (the function is disabled on some shared hosts). */
    protected function freeBytes(): ?float
    {
        try {
            $free = function_exists('disk_free_space') ? @disk_free_space(storage_path()) : false;
        } catch (\Throwable) {
            return null;
        }

        return $free === false ? null : (float) $free;
    }

    private function securityTxtExpiring(): bool
    {
        $path = public_path('.well-known/security.txt');

        if (! is_file($path) || ! preg_match('/^Expires:\s*(\S+)/mi', (string) file_get_contents($path), $m)) {
            return true; // missing file or missing Expires is itself a problem worth one alert a day
        }

        try {
            return now()->addDays(30)->greaterThan(Carbon::parse($m[1]));
        } catch (\Throwable) {
            return true;
        }
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

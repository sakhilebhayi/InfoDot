<?php

namespace App\Services\Growth;

use App\Models\CompanyProfileImport;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * R-62 phase 6: the extra onboarding figures, each counted from records InfoDot keeps (import records, accounts, companies, onboarding events and
 * the days people signed in). A figure with no record behind it is returned as null so the page says "not yet" instead of showing a number.
 */
class OnboardingInsights
{
    /** The days after sign-up at which "came back" is measured. */
    public const WINDOWS = [1, 7, 30];

    /**
     * For companies set up by reading a website: per field, how many reads found it, and of the companies confirmed, how many kept the value found
     * and how many changed it.
     *
     * @return list<array{field: string, found: int, kept: int, changed: int}>
     */
    public function websiteFields(?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $imports = CompanyProfileImport::query()->where('source', CompanyProfileImport::WEBSITE)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))->when($to, fn ($q) => $q->where('created_at', '<=', $to))->get();

        $rows = [];
        foreach ($imports as $import) {
            foreach ((array) $import->extracted as $field => $entry) {
                $rows[$field] ??= ['field' => (string) $field, 'found' => 0, 'kept' => 0, 'changed' => 0];
                $rows[$field]['found']++;

                if ($import->status === CompanyProfileImport::CONFIRMED) {
                    $final = Arr::get((array) $import->confirmed, $field);
                    $found = Arr::get((array) $entry, 'value');
                    $same = is_array($found) || is_array($final) ? json_encode($found) === json_encode($final) : mb_strtolower(trim((string) $found)) === mb_strtolower(trim((string) $final));
                    $rows[$field][$same ? 'kept' : 'changed']++;
                }
            }
        }

        ksort($rows);

        return array_values($rows);
    }

    /**
     * How long people took from making an account to creating their first company: the median and the number of people it is from.
     * Only people whose first company is one they own.
     *
     * @return array{people: int, median_minutes: ?int}
     */
    public function timeToFirstCompany(?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $rows = DB::table('users')
            ->joinSub(
                DB::table('teams')->where('personal_team', false)->selectRaw('user_id as owner_id, min(created_at) as first_company')->groupBy('user_id'),
                't', 't.owner_id', '=', 'users.id'
            )
            ->when($from, fn ($q) => $q->where('users.created_at', '>=', $from))->when($to, fn ($q) => $q->where('users.created_at', '<=', $to))
            ->get(['users.created_at as made', 't.first_company']);

        $minutes = $rows->map(fn ($r) => max(0, (int) round((strtotime((string) $r->first_company) - strtotime((string) $r->made)) / 60)))->sort()->values();

        if ($minutes->isEmpty()) {
            return ['people' => 0, 'median_minutes' => null];
        }

        $n = $minutes->count();
        $median = $n % 2 === 1 ? $minutes[intdiv($n, 2)] : (int) round(($minutes[$n / 2 - 1] + $minutes[$n / 2]) / 2);

        return ['people' => $n, 'median_minutes' => $median];
    }

    /**
     * Where people stopped: for everyone who has at least one recorded step, the last step they reached, in the order of config/onboarding.php.
     * "Stopped" means the last step so far; someone may still carry on.
     *
     * @return list<array{step: string, label: string, people: int}>
     */
    public function stoppedAt(?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $order = array_keys((array) config('onboarding.steps'));
        $labels = (array) config('onboarding.steps');

        $perPerson = DB::table('onboarding_events')
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))->when($to, fn ($q) => $q->where('occurred_at', '<=', $to))
            ->select('user_id', 'step')->get()->groupBy('user_id');

        $counts = array_fill_keys($order, 0);
        foreach ($perPerson as $steps) {
            $last = collect($steps)->pluck('step')->map(fn ($s) => array_search($s, $order, true))->filter(fn ($i) => $i !== false)->max();
            if ($last !== null) {
                $counts[$order[$last]]++;
            }
        }

        return array_map(fn (string $step) => ['step' => $step, 'label' => (string) $labels[$step], 'people' => $counts[$step]], $order);
    }

    /**
     * Of the people whose account is at least N days old, how many signed in on a day at least N days after they made it (so on or after day N).
     * Counted from the days people signed in, which are recorded from the day recording began; accounts older than that have no earlier record,
     * so only people who made their account after recording began are counted.
     *
     * @return array{since: ?string, windows: list<array{days: int, cohort: int, returned: int, share: ?float}>}
     */
    public function retention(): array
    {
        $since = DB::table('user_login_days')->min('day');
        $out = [];

        foreach (self::WINDOWS as $days) {
            $cohort = DB::table('users')->when($since, fn ($q) => $q->where('created_at', '>=', $since))
                ->where('created_at', '<=', now()->subDays($days))->pluck('created_at', 'id');

            $returned = 0;
            if ($cohort->isNotEmpty() && $since !== null) {
                $returned = DB::table('user_login_days as l')->join('users as u', 'u.id', '=', 'l.user_id')
                    ->whereIn('u.id', $cohort->keys())
                    ->whereRaw($this->dayDiff().' >= ?', [$days])
                    ->distinct()->count('l.user_id');
            }

            $out[] = ['days' => $days, 'cohort' => $cohort->count(), 'returned' => $returned, 'share' => $cohort->isEmpty() || $since === null ? null : round($returned / $cohort->count() * 100, 1)];
        }

        return ['since' => $since === null ? null : (string) $since, 'windows' => $out];
    }

    /** SQL for whole days between the day a person made their account and the day they signed in, on SQLite, MySQL and PostgreSQL. */
    private function dayDiff(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => 'julianday(l.day) - julianday(date(u.created_at))',
            'pgsql' => 'l.day - u.created_at::date',
            default => 'DATEDIFF(l.day, DATE(u.created_at))',
        };
    }
}

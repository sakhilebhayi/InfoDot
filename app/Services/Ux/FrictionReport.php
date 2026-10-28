<?php

namespace App\Services\Ux;

use Illuminate\Support\Facades\DB;

/**
 * Reads the daily counts and says where people struggle. Deterministic rules, no model: each finding names the rule, the
 * numbers, the same numbers for the period before (so a fix can be seen to work or not), how much to trust it, and one
 * suggested look. Advisory only: it changes nothing and decides nothing.
 */
class FrictionReport
{
    /**
     * @return array{from: string, to: string, days: int, findings: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function build(int $days = 14): array
    {
        $days = max(1, min(90, $days));
        $to = now()->toDateString();
        $from = now()->subDays($days - 1)->toDateString();
        $prevTo = now()->subDays($days)->toDateString();
        $prevFrom = now()->subDays(2 * $days - 1)->toDateString();

        $now = $this->rows($from, $to);
        $before = $this->rows($prevFrom, $prevTo);
        $r = config('ux.rules');
        $findings = [];

        foreach ($now as $route => $c) {
            $p = $before[$route] ?? [];
            $views = $this->n($c, 'nav.view:success');
            $prevViews = $this->n($p, 'nav.view:success');

            $submits = $this->n($c, 'form.submit:success') + $this->n($c, 'form.validation_failed:failure');
            $prevSubmits = $this->n($p, 'form.submit:success') + $this->n($p, 'form.validation_failed:failure');
            $fails = $this->n($c, 'form.validation_failed:failure');
            if ($submits >= $r['min_events'] && $fails / $submits >= $r['validation_rate']) {
                $findings[] = $this->finding($route, 'validation_failures', $fails, $submits, $this->n($p, 'form.validation_failed:failure'), $prevSubmits,
                    'People are submitting this form and being sent back. Read the field labels, hints and required fields as a first-time user would.');
            }

            $searches = $this->n($c, 'search.run:success') + $this->n($c, 'search.empty:failure');
            $prevSearches = $this->n($p, 'search.run:success') + $this->n($p, 'search.empty:failure');
            $empty = $this->n($c, 'search.empty:failure');
            if ($searches >= $r['min_events'] && $empty / $searches >= $r['empty_search_rate']) {
                $findings[] = $this->finding($route, 'empty_searches', $empty, $searches, $this->n($p, 'search.empty:failure'), $prevSearches,
                    'Many searches find nothing. Add the missing topics or answers, or accept more ways of saying the same thing.');
            }

            $slow = $this->n($c, 'request.slow:partial');
            if ($views >= $r['min_events'] && $slow / max(1, $views) >= $r['slow_rate']) {
                $findings[] = $this->finding($route, 'slow_pages', $slow, $views, $this->n($p, 'request.slow:partial'), $prevViews,
                    'A large share of views of this page take longer than '.config('ux.slow_ms').' ms. Look at its queries and what it loads.');
            }

            $nf = $this->n($c, 'nav.view:failure:not_found');
            if ($nf >= $r['not_found_count']) {
                $findings[] = $this->finding($route, 'not_found', $nf, max($nf, $views + $nf), $this->n($p, 'nav.view:failure:not_found'), $prevViews,
                    'People keep arriving at addresses that do not exist. Find which link or bookmark points here and fix or redirect it.');
            }

            $err = $this->n($c, 'request.failed:failure:server');
            if ($err >= $r['server_error_count']) {
                $findings[] = $this->finding($route, 'server_errors', $err, max($err, $views + $err), $this->n($p, 'request.failed:failure:server'), $prevViews,
                    'This page errors on the server. Read the application log for the time of the day it happens.');
            }
        }

        usort($findings, fn ($a, $b) => [$b['rate'], $b['events']] <=> [$a['rate'], $a['events']]);

        return [
            'from' => $from, 'to' => $to, 'days' => $days, 'findings' => $findings,
            'totals' => ['routes' => count($now), 'events' => (int) array_sum(array_map('array_sum', $now))],
        ];
    }

    /** @return array<string, array<string, int>> route => ["type:status" or "type:status:error" => hits] */
    private function rows(string $from, string $to): array
    {
        $out = [];
        $rows = DB::table('ux_daily_counts')->whereBetween('day', [$from, $to])
            ->selectRaw('route, type, status, error, SUM(hits) as hits')->groupBy('route', 'type', 'status', 'error')->get();

        foreach ($rows as $row) {
            $key = $row->type.':'.$row->status;
            $out[$row->route][$key] = ($out[$row->route][$key] ?? 0) + (int) $row->hits;
            if ($row->error !== '') {
                $out[$row->route][$key.':'.$row->error] = (int) $row->hits;
            }
        }

        return $out;
    }

    private function n(array $counts, string $key): int
    {
        return (int) ($counts[$key] ?? 0);
    }

    /** @return array<string, mixed> */
    private function finding(string $route, string $rule, int $events, int $of, int $prevEvents, int $prevOf, string $suggestion): array
    {
        $rate = $of > 0 ? $events / $of : 0.0;
        $prevRate = $prevOf > 0 ? $prevEvents / $prevOf : null;
        $change = null;
        if ($prevRate !== null && $prevOf >= (int) config('ux.rules.min_events')) {
            $change = $rate > $prevRate + 0.05 ? 'worse' : ($rate < $prevRate - 0.05 ? 'better' : 'about the same');
        }

        return [
            'route' => $route, 'rule' => $rule, 'events' => $events, 'of' => $of, 'rate' => round($rate, 3),
            'previous_rate' => $prevRate === null ? null : round($prevRate, 3), 'change' => $change,
            'confidence' => $of >= 200 ? 'high' : ($of >= 60 ? 'medium' : 'low'), 'suggestion' => $suggestion,
        ];
    }
}

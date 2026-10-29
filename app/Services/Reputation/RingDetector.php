<?php

namespace App\Services\Reputation;

use App\Models\Answer;
use App\Models\Questions;
use App\Models\ReputationRingFlag;
use App\Models\Solutions;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Finds groups of accounts that accept each other's answers (see config/reputation.php `rings`). It only ever writes advisory
 * flags: it bans no one, hides nothing and tells no one. Detecting twice changes nothing the second time, because a flag is
 * keyed by its kind and its member set; a moderator's decision on a flag is never overwritten.
 */
class RingDetector
{
    /**
     * @return array{links: int, cycles: int, clusters: int, created: int, refreshed: int}
     */
    public function detect(): array
    {
        $edges = $this->edges();
        $found = [];

        foreach ($this->cycles($edges) as $members) {
            $found[ReputationRingFlag::CYCLE.':'.ReputationRingFlag::hashOf($members)] = [ReputationRingFlag::CYCLE, $members];
        }
        foreach ($this->clusters($edges) as $members) {
            $found[ReputationRingFlag::CLUSTER.':'.ReputationRingFlag::hashOf($members)] = [ReputationRingFlag::CLUSTER, $members];
        }

        $created = $refreshed = $cycles = $clusters = 0;

        foreach ($found as [$kind, $members]) {
            $kind === ReputationRingFlag::CYCLE ? $cycles++ : $clusters++;
            $stats = $this->stats($members, $edges);
            $flag = ReputationRingFlag::firstOrNew(['kind' => $kind, 'member_hash' => ReputationRingFlag::hashOf($members)]);

            if (! $flag->exists) {
                $flag->forceFill(['member_ids' => $members, 'detected_at' => now(), 'status' => ReputationRingFlag::OPEN]);
                $created++;
            } else {
                $refreshed++; // status, reviewer and note are left exactly as the moderator set them
            }

            $flag->forceFill(['edge_count' => $stats['internal'], 'internal_ratio' => $stats['ratio']])->save();
        }

        return ['links' => array_sum(array_map('count', $edges)), 'cycles' => $cycles, 'clusters' => $clusters, 'created' => $created, 'refreshed' => $refreshed];
    }

    /**
     * Acceptances inside the window as acceptor => author => how many times. Public, published, unhidden work only, never help
     * given to yourself, and no moderator on either end.
     *
     * @return array<int, array<int, int>>
     */
    public function edges(): array
    {
        $since = Carbon::now()->subDays(max(1, (int) config('reputation.rings.window_days', 180)));
        $moderators = User::where('is_moderator', true)->pluck('id')->flip();
        $public = Questions::withoutGlobalScopes()->where('visibility', Questions::VISIBILITY_PUBLIC)->whereNull('hidden_at')->select('id');
        $edges = [];

        $add = function ($rows) use (&$edges, $moderators): void {
            foreach ($rows as $row) {
                if ($row->acceptor === $row->author || $moderators->has($row->acceptor) || $moderators->has($row->author)) {
                    continue;
                }
                $edges[(int) $row->acceptor][(int) $row->author] = ($edges[(int) $row->acceptor][(int) $row->author] ?? 0) + (int) $row->n;
            }
        };

        $add(Answer::withoutGlobalScope('moderation-visibility')
            ->where('status', Answer::PUBLISHED)->whereNull('hidden_at')->where('is_self_answer', false)
            ->whereNotNull('accepted_at')->whereNotNull('accepted_by')->whereColumn('accepted_by', '!=', 'user_id')
            ->where('accepted_at', '>=', $since)->whereIn('question_id', $public)
            ->selectRaw('accepted_by as acceptor, user_id as author, count(*) as n')->groupBy('accepted_by', 'user_id')->get());

        // Verification is a moderator's act, so these rows normally add nothing: a verifier who is no longer a moderator is the only way in.
        $add(Solutions::withoutGlobalScopes()
            ->where('visibility', Solutions::VISIBILITY_PUBLIC)->whereNull('hidden_at')
            ->whereNotNull('verified_at')->whereNotNull('verified_by')->whereColumn('verified_by', '!=', 'user_id')
            ->where('verified_at', '>=', $since)
            ->selectRaw('verified_by as acceptor, user_id as author, count(*) as n')->groupBy('verified_by', 'user_id')->get());

        return $edges;
    }

    /**
     * Distinct member sets of directed cycles of 3 or 4 accounts.
     *
     * @param  array<int, array<int, int>>  $edges
     * @return list<list<int>>
     */
    public function cycles(array $edges): array
    {
        $min = max(1, (int) config('reputation.rings.min_edges_per_link', 1));
        $out = [];
        foreach ($edges as $from => $to) {
            foreach ($to as $author => $n) {
                if ($n >= $min) {
                    $out[$from][] = $author;
                }
            }
        }

        $found = [];
        $walk = function (int $start, int $node, array $path) use (&$walk, &$found, $out): void {
            foreach ($out[$node] ?? [] as $next) {
                if ($next === $start && count($path) >= 3) {
                    $members = $path;
                    sort($members);
                    $found[implode(',', $members)] = $members;
                } elseif ($next > $start && ! in_array($next, $path, true) && count($path) < 4) {
                    $walk($start, $next, [...$path, $next]); // $start is the smallest id, so each cycle is found from one place only
                }
            }
        };

        foreach (array_keys($out) as $start) {
            $walk($start, $start, [$start]);
        }

        ksort($found);

        return array_values($found);
    }

    /**
     * Member sets of tight clusters: components of the mutual-acceptance graph that meet every threshold.
     *
     * @param  array<int, array<int, int>>  $edges
     * @return list<list<int>>
     */
    public function clusters(array $edges): array
    {
        $cfg = config('reputation.rings.cluster');
        $min = max(1, (int) config('reputation.rings.min_edges_per_link', 1));

        $adj = [];
        foreach ($edges as $a => $to) {
            foreach ($to as $b => $n) {
                if ($n >= $min && ($edges[$b][$a] ?? 0) >= $min) {
                    $adj[$a][$b] = true;
                }
            }
        }

        $seen = [];
        $found = [];
        foreach (array_keys($adj) as $root) {
            if (isset($seen[$root])) {
                continue;
            }
            $component = [];
            $stack = [$root];
            $seen[$root] = true;
            while ($stack) {
                $node = array_pop($stack);
                $component[] = $node;
                foreach (array_keys($adj[$node] ?? []) as $next) {
                    if (! isset($seen[$next])) {
                        $seen[$next] = true;
                        $stack[] = $next;
                    }
                }
            }
            sort($component);
            $size = count($component);

            if ($size < (int) $cfg['min_members'] || $size > (int) $cfg['max_members']) {
                continue;
            }

            $stats = $this->stats($component, $edges);
            $density = $stats['distinct'] / ($size * ($size - 1));

            if ($stats['internal'] >= (int) $cfg['min_internal_edges'] && $density >= (float) $cfg['min_density'] && $stats['lowest_ratio'] >= (float) $cfg['min_internal_ratio']) {
                $found[] = $component;
            }
        }

        return $found;
    }

    /**
     * @param  list<int>  $members
     * @param  array<int, array<int, int>>  $edges
     * @return array{internal: int, distinct: int, ratio: float, lowest_ratio: float}
     */
    private function stats(array $members, array $edges): array
    {
        $in = array_flip($members);
        $internal = $distinct = 0;
        $receivedTotal = array_fill_keys($members, 0);
        $receivedInside = array_fill_keys($members, 0);

        foreach ($edges as $from => $to) {
            foreach ($to as $author => $n) {
                if (! isset($in[$author])) {
                    continue;
                }
                $receivedTotal[$author] += $n;
                if (isset($in[$from])) {
                    $receivedInside[$author] += $n;
                    $internal += $n;
                    $distinct++;
                }
            }
        }

        $lowest = 1.0;
        foreach ($members as $m) {
            $lowest = min($lowest, $receivedTotal[$m] > 0 ? $receivedInside[$m] / $receivedTotal[$m] : 0.0);
        }

        $total = array_sum($receivedTotal);

        return ['internal' => $internal, 'distinct' => $distinct, 'ratio' => $total > 0 ? round($internal / $total, 4) : 0.0, 'lowest_ratio' => $lowest];
    }
}

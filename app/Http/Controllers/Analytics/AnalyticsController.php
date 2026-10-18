<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Questions;
use App\Models\Solutions;
use App\Support\Platforms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Analytics for the company the person is acting for, and only that company:
 * its members, its questions and solutions, the replies on them. Nothing here
 * counts or names people or content from other companies.
 */
class AnalyticsController extends Controller
{
    public function dashboard(): View
    {
        $team = auth()->user()->currentTeam;
        $teamId = $team?->id ?? 0;

        $questions = fn (): Builder => Questions::withoutGlobalScope('knowledge-visibility')->where('team_id', $teamId);
        $solutions = fn (): Builder => Solutions::withoutGlobalScope('knowledge-visibility')->where('team_id', $teamId);

        $members = $team ? $team->allUsers() : collect();
        $memberIds = $members->pluck('id');

        // ── Headline counts ───────────────────────────────────────────────
        $totalUsers = $members->count();
        $totalSolutions = $solutions()->count();
        $totalQuestions = $questions()->count();
        $totalComments = Comment::query()
            ->where(function (Builder $q) use ($questions, $solutions) {
                $q->where(fn (Builder $x) => $x->where('commentable_type', 'questions')->whereIn('commentable_id', $questions()->select('id')))
                    ->orWhere(fn (Builder $x) => $x->where('commentable_type', 'solutions')->whereIn('commentable_id', $solutions()->select('id')));
            })->count();
        $solvedQuestions = $questions()->where('status', 1)->count();

        // ── Month-over-month deltas (current vs previous month) ───────────
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        $usersThisMonth = $members->filter(fn ($u) => $u->created_at >= $thisMonth)->count();
        $usersLastMonth = $members->filter(fn ($u) => $u->created_at >= $lastMonth && $u->created_at < $thisMonth)->count();
        $solutionsThisMonth = $solutions()->where('created_at', '>=', $thisMonth)->count();
        $solutionsLastMonth = $solutions()->whereBetween('created_at', [$lastMonth, $thisMonth])->count();
        $questionsThisMonth = $questions()->where('created_at', '>=', $thisMonth)->count();
        $questionsLastMonth = $questions()->whereBetween('created_at', [$lastMonth, $thisMonth])->count();

        // ── 6-month activity trend ────────────────────────────────────────
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        $solutionTrend = $months->map(fn (Carbon $m) => $solutions()->whereYear('created_at', $m->year)
            ->whereMonth('created_at', $m->month)->count()
        );
        $questionTrend = $months->map(fn (Carbon $m) => $questions()->whereYear('created_at', $m->year)
            ->whereMonth('created_at', $m->month)->count()
        );

        $monthLabels = $months->map(fn (Carbon $m) => $m->format('M'));

        // ── Most discussed solutions (replies) ────────────────────────────
        $topSolutions = $solutions()->withCount('comments')
            ->orderByDesc('comments_count')
            ->limit(5)
            ->get(['id', 'solution_title']);

        // ── Top contributors: members who asked the most ──────────────────
        $topContributors = $members->map(function ($u) use ($questions) {
            $u->questions_count = $questions()->where('user_id', $u->id)->count();

            return $u;
        })->sortByDesc('questions_count')->take(5)->values();

        // ── Knowledge health (0–100): how much gets answered ──────────────
        // 60% solve rate, 40% replies per question (5 or more counts in full).
        $solveRate = $totalQuestions > 0 ? ($solvedQuestions / $totalQuestions) : 0;
        $avgCommentsPerQ = $totalQuestions > 0 ? ($totalComments / $totalQuestions) : 0;

        $engagementScore = (int) min(100, round(
            ($solveRate * 60) +
            (min($avgCommentsPerQ / 5, 1) * 40)
        ));

        $engagementLabel = match (true) {
            $engagementScore >= 80 => 'Excellent',
            $engagementScore >= 60 => 'Good',
            $engagementScore >= 40 => 'Fair',
            default => 'Building',
        };

        // ── Platform registry ─────────────────────────────────────────────
        $platforms = Platforms::visible();

        return view('analytics.dashboard', compact(
            'team', 'totalUsers', 'totalSolutions', 'totalQuestions', 'totalComments',
            'solvedQuestions',
            'usersThisMonth', 'usersLastMonth',
            'solutionsThisMonth', 'solutionsLastMonth',
            'questionsThisMonth', 'questionsLastMonth',
            'solutionTrend', 'questionTrend', 'monthLabels',
            'topSolutions', 'topContributors',
            'engagementScore', 'engagementLabel',
            'solveRate', 'avgCommentsPerQ',
            'platforms',
        ));
    }
}

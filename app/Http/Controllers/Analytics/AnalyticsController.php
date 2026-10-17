<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Associates;
use App\Models\Comment;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function dashboard(): View
    {
        // ── Headline counts ───────────────────────────────────────────────
        $totalUsers = User::count();
        $totalSolutions = Solutions::count();
        $totalQuestions = Questions::count();
        $totalComments = Comment::count();
        $totalTeams = Team::count();
        $totalFollows = Associates::count();
        $solvedQuestions = Questions::where('status', 'solved')->count();

        // ── Month-over-month deltas (current vs previous month) ───────────
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        $usersThisMonth = User::where('created_at', '>=', $thisMonth)->count();
        $usersLastMonth = User::whereBetween('created_at', [$lastMonth, $thisMonth])->count();
        $solutionsThisMonth = Solutions::where('created_at', '>=', $thisMonth)->count();
        $solutionsLastMonth = Solutions::whereBetween('created_at', [$lastMonth, $thisMonth])->count();
        $questionsThisMonth = Questions::where('created_at', '>=', $thisMonth)->count();
        $questionsLastMonth = Questions::whereBetween('created_at', [$lastMonth, $thisMonth])->count();

        // ── 6-month activity trend ────────────────────────────────────────
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        $solutionTrend = $months->map(fn (Carbon $m) => Solutions::whereYear('created_at', $m->year)
            ->whereMonth('created_at', $m->month)->count()
        );

        $questionTrend = $months->map(fn (Carbon $m) => Questions::whereYear('created_at', $m->year)
            ->whereMonth('created_at', $m->month)->count()
        );

        $monthLabels = $months->map(fn (Carbon $m) => $m->format('M'));

        // ── Most discussed solutions (replies) ────────────────────────────
        $topSolutions = Solutions::withCount('comments')
            ->orderByDesc('comments_count')
            ->limit(5)
            ->get(['id', 'solution_title']);

        // ── Top contributors (most content created) ───────────────────────
        $topContributors = User::withCount('questions')
            ->orderByDesc('questions_count')
            ->limit(5)
            ->get(['id', 'name', 'email']);

        // ── Knowledge health (0–100): how much gets answered ──────────────
        // 60% solve rate, 40% replies per question (5 or more counts in full).
        // Likes are deliberately not part of it.
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
        $platforms = config('ecosystem.platforms', []);

        return view('analytics.dashboard', compact(
            'totalUsers', 'totalSolutions', 'totalQuestions',
            'totalComments', 'totalTeams', 'totalFollows',
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

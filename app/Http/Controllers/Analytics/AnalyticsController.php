<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Associates;
use App\Models\Comment;
use App\Models\Like;
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
        $totalLikes = Like::count();
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

        // ── Top solutions by likes ────────────────────────────────────────
        $topSolutions = Solutions::withCount('likes')
            ->orderByDesc('likes_count')
            ->limit(5)
            ->get(['id', 'solution_title']);

        // ── Top contributors (most content created) ───────────────────────
        $topContributors = User::withCount(['questions', 'likes'])
            ->orderByDesc('questions_count')
            ->limit(5)
            ->get(['id', 'name', 'email']);

        // ── Engagement score (0–100) ──────────────────────────────────────
        $solveRate = $totalQuestions > 0 ? ($solvedQuestions / $totalQuestions) : 0;
        $avgCommentsPerQ = $totalQuestions > 0 ? ($totalComments / $totalQuestions) : 0;
        $avgLikesPerItem = ($totalSolutions + $totalQuestions) > 0
            ? ($totalLikes / ($totalSolutions + $totalQuestions))
            : 0;

        $engagementScore = (int) min(100, round(
            ($solveRate * 40) +
            (min($avgCommentsPerQ / 5, 1) * 30) +
            (min($avgLikesPerItem / 10, 1) * 30)
        ));

        $engagementLabel = match (true) {
            $engagementScore >= 80 => 'Excellent',
            $engagementScore >= 60 => 'Good',
            $engagementScore >= 40 => 'Fair',
            default => 'Growing',
        };

        // ── Platform registry ─────────────────────────────────────────────
        $platforms = config('ecosystem.platforms', []);

        return view('analytics.dashboard', compact(
            'totalUsers', 'totalSolutions', 'totalQuestions',
            'totalComments', 'totalLikes', 'totalTeams', 'totalFollows',
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

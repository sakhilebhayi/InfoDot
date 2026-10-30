<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\User;
use App\Support\Platforms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        // Ids and join dates only (not whole user rows): a company can have many members.
        $members = $team
            ? DB::table('users')->where(fn ($q) => $q->where('users.id', $team->user_id)->orWhereIn('users.id', DB::table('team_user')->where('team_id', $team->id)->select('user_id')))->get(['users.id', 'users.created_at'])
            : collect();

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

        $usersThisMonth = $members->filter(fn ($u) => Carbon::parse($u->created_at) >= $thisMonth)->count();
        $usersLastMonth = $members->filter(fn ($u) => Carbon::parse($u->created_at) >= $lastMonth && Carbon::parse($u->created_at) < $thisMonth)->count();
        $solutionsThisMonth = $solutions()->where('created_at', '>=', $thisMonth)->count();
        $solutionsLastMonth = $solutions()->whereBetween('created_at', [$lastMonth, $thisMonth])->count();
        $questionsThisMonth = $questions()->where('created_at', '>=', $thisMonth)->count();
        $questionsLastMonth = $questions()->whereBetween('created_at', [$lastMonth, $thisMonth])->count();

        // ── 6-month activity trend ────────────────────────────────────────
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        // A plain date range per month (the index on created_at can serve it); YEAR()/MONTH() of the column could not.
        $solutionTrend = $months->map(fn (Carbon $m) => $solutions()->where('created_at', '>=', $m)->where('created_at', '<', $m->copy()->addMonth())->count());
        $questionTrend = $months->map(fn (Carbon $m) => $questions()->where('created_at', '>=', $m)->where('created_at', '<', $m->copy()->addMonth())->count());

        $monthLabels = $months->map(fn (Carbon $m) => $m->format('M'));

        // ── Most discussed solutions (replies) ────────────────────────────
        $topSolutions = $solutions()->withCount('comments')
            ->orderByDesc('comments_count')
            ->limit(5)
            ->get(['id', 'solution_title']);

        // ── Top contributors: members who asked the most ──────────────────
        // One grouped query for the five who asked most, then only those five people are loaded (it was a count query per member).
        $counts = $questions()->whereIn('user_id', $members->pluck('id'))->selectRaw('user_id, count(*) as asked')->groupBy('user_id')->orderByDesc('asked')->orderBy('user_id')->limit(5)->pluck('asked', 'user_id');
        // The list always shows up to five members, so people who have not asked yet fill the remaining places (the owner first), as before.
        $fill = $members->sortBy(fn ($m) => [$m->id === $team?->user_id ? 0 : 1, $m->id])->pluck('id')->reject(fn ($id) => $counts->has($id))->take(max(0, 5 - $counts->count()));
        $people = User::whereIn('id', $counts->keys()->merge($fill))->get()->keyBy('id');
        $topContributors = $counts->keys()->merge($fill)->map(fn ($id) => $people->get($id))->filter()->each(fn ($u) => $u->questions_count = (int) ($counts[$u->id] ?? 0))->values();

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

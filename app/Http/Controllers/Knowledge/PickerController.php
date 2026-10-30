<?php

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Controller;
use App\Models\SolutionConfirmation;
use App\Models\Solutions;
use App\Services\Knowledge\MentionService;
use App\Support\Reference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The small JSON lookups behind the composers (R-16): @mention autocomplete and the recommend-a-solution picker.
 * Both are throttled at the route, debounced client-side, briefly cached per person, and return names only.
 */
class PickerController extends Controller
{
    public function mentions(Request $request, MentionService $mentions): JsonResponse
    {
        $query = Str::limit(trim((string) $request->query('q', '')), 60, '');
        $user = $request->user();

        $results = Cache::remember('mentions:u'.$user->id.':'.md5(mb_strtolower($query)), 60,
            fn () => $mentions->search($user, $query));

        return response()->json($results);
    }

    public function solutions(Request $request): JsonResponse
    {
        $query = Str::limit(trim((string) $request->query('q', '')), 80, '');

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $user = $request->user();
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';

        // The visibility scope keeps this to what the person may see; the cache key carries person and company.
        $results = Cache::remember('solpick:u'.$user->id.':t'.($user->current_team_id ?? 0).':'.md5(mb_strtolower($query)), 60, function () use ($like) {
            $solutions = Solutions::with('user')
                ->where(fn ($q) => $q->where('solution_title', 'like', $like)->orWhere('tags', 'like', $like)->orWhere('solution_description', 'like', $like))
                ->latest()->limit(5)->get();

            $confirmations = SolutionConfirmation::whereIn('solution_id', $solutions->pluck('id'))
                ->selectRaw('solution_id, count(*) as n')->groupBy('solution_id')->pluck('n', 'solution_id');

            return $solutions->map(fn (Solutions $s) => [
                'id' => $s->id,
                'name' => $s->solution_title,
                'ref' => Reference::for($s),
                'author' => $s->user->name ?? 'Former member',
                'excerpt' => Str::limit((string) $s->solution_description, 110),
                'tried' => (int) ($confirmations[$s->id] ?? 0),
            ])->values()->all();
        });

        return response()->json($results);
    }
}

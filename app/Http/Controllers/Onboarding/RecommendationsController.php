<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Services\Growth\OnboardingEvents;
use App\Services\Onboarding\PlatformRecommender;
use App\Support\GettingStarted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * R-59 Phase 1B: after a business is set up, the platforms that fit what it said it wants, each with the reason it was
 * chosen. The business can change its aims here and the list follows. Nothing here opens, buys or licenses anything.
 */
class RecommendationsController extends Controller
{
    public function show(Request $request, PlatformRecommender $recommender, OnboardingEvents $events): View|RedirectResponse
    {
        $user = $request->user();
        $team = GettingStarted::managedBusiness($user);

        if ($team === null) {
            return redirect()->route('onboarding.business');
        }

        $recommendations = $recommender->for($team);
        $events->record($user, 'platforms_recommended', $team);

        return view('onboarding.recommendations', [
            'team' => $team,
            'recommendations' => $recommendations,
            'objectives' => config('onboarding.objectives'),
            'selected' => (array) ($team->profile()->first()->objectives ?? []),
            'maxObjectives' => (int) config('onboarding.max_objectives'),
        ]);
    }

    public function update(Request $request, OnboardingEvents $events): RedirectResponse
    {
        $user = $request->user();
        $team = GettingStarted::managedBusiness($user);
        abort_if($team === null, 404);

        $data = $request->validate([
            'objectives' => ['nullable', 'array', 'max:'.(int) config('onboarding.max_objectives')],
            'objectives.*' => ['string', Rule::in(array_keys(config('onboarding.objectives')))],
        ]);

        $objectives = array_values(array_unique($data['objectives'] ?? []));
        $team->profileOrNew()->forceFill(['objectives' => $objectives === [] ? null : $objectives])->save();

        if ($objectives !== []) {
            $events->record($user, 'objectives_set', $team);
        }

        return redirect()->route('onboarding.recommendations')->with('status', 'Your aims are saved.');
    }
}

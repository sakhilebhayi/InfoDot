<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Growth\OnboardingEvents;
use App\Services\Onboarding\FirstFourPlatforms;
use App\Services\Onboarding\PlatformRecommender;
use App\Services\Platforms\EntitlementRefused;
use App\Services\Platforms\Entitlements;
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
    public function show(Request $request, PlatformRecommender $recommender, OnboardingEvents $events, FirstFourPlatforms $firstFour): View|RedirectResponse
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
            'firstFour' => $firstFour->for($user, $team),
            'canSwitchOn' => $user->can('managePlatforms', $team),
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

    /**
     * Switches on the platforms the person ticked from the first-four list. Only platforms that are in that list right now are accepted
     * (so nothing paid, held or unavailable can be passed in), only by someone who may manage the company's platforms, and each one goes
     * through the same activation as everywhere else. One that is refused does not stop the others.
     */
    public function activate(Request $request, FirstFourPlatforms $firstFour, Entitlements $entitlements): RedirectResponse
    {
        $user = $request->user();
        $team = GettingStarted::managedBusiness($user);
        abort_if(! $team instanceof Team, 404);
        abort_unless($user->can('managePlatforms', $team), 403);

        $data = $request->validate([
            'company' => ['required', 'string'],
            'platforms' => ['required', 'array', 'min:1', 'max:'.FirstFourPlatforms::LIMIT],
            'platforms.*' => ['string'],
        ]);

        // The form says which company it was drawn for; if the person has since changed company, nothing is switched on.
        abort_unless(hash_equals((string) $team->uuid, $data['company']), 409, 'You have switched company since this page was opened. Go back and try again.');

        $offered = $firstFour->for($user, $team)->pluck('name', 'key');
        $on = [];
        $refused = [];

        foreach (array_values(array_unique($data['platforms'])) as $key) {
            if (! $offered->has($key)) {
                $refused[] = $key.' is not on the list.';

                continue;
            }

            try {
                $entitlements->activate($team, $key, $user);
                $on[] = $offered[$key];
                app(OnboardingEvents::class)->record($user, 'platform_activated', $team);
            } catch (EntitlementRefused $e) {
                $refused[] = $offered[$key].': '.$e->getMessage();
            }
        }

        $message = $on === [] ? 'Nothing was switched on.' : 'Switched on: '.implode(', ', $on).'.';

        return redirect()->route('onboarding.recommendations')->with('status', trim($message.' '.implode(' ', $refused)));
    }
}

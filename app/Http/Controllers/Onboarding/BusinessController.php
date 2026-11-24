<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\TeamProfile;
use App\Services\Growth\OnboardingEvents;
use App\Services\Sso\BusinessOnboarding;
use App\Services\Sso\PlatformContext;
use App\Support\Industry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Jetstream\Contracts\CreatesTeams;

/**
 * R-54 "Set up your business": the short step between a new account and a platform that is for businesses. It creates
 * the company through the existing team action (CreateTeam: same validation, same profile row, same audit line) and
 * then carries on with the sign-in that was pending. It adds no rule of its own about licences or entitlements; the
 * authorize endpoint judges those after it, exactly as before.
 */
class BusinessController extends Controller
{
    public function __construct(private readonly BusinessOnboarding $onboarding, private readonly PlatformContext $context) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->onboarding->needsBusiness($request->user())) {
            return redirect($this->context->pendingUrl() ?? route('dashboard'));
        }

        return view('onboarding.business', [
            'branding' => $this->context->branding(),
            'industries' => Industry::options(),
            'visibility' => TeamProfile::VISIBILITY,
            'stages' => config('onboarding.stages'),
            'sizeBands' => config('onboarding.size_bands'),
            'objectives' => config('onboarding.objectives'),
            'maxObjectives' => (int) config('onboarding.max_objectives'),
        ]);
    }

    public function store(Request $request, CreatesTeams $creator): RedirectResponse
    {
        $user = $request->user();

        // A second tap, or a person who already has a business: nothing is created twice.
        if (! $this->onboarding->needsBusiness($user)) {
            return redirect($this->context->pendingUrl() ?? route('dashboard'));
        }

        $about = $request->validate([
            'stage' => ['nullable', Rule::in(array_keys(config('onboarding.stages')))],
            'size_band' => ['nullable', Rule::in(array_keys(config('onboarding.size_bands')))],
            'objectives' => ['nullable', 'array', 'max:'.(int) config('onboarding.max_objectives')],
            'objectives.*' => ['string', Rule::in(array_keys(config('onboarding.objectives')))],
        ]);

        try {
            $team = $creator->create($user, $request->only(['name', 'industry', 'description', 'visibility']));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        // What the business says about itself is optional; it is kept with the profile the action just wrote.
        $objectives = array_values(array_unique($about['objectives'] ?? []));
        $team->profile()->update([
            'stage' => $about['stage'] ?? null,
            'size_band' => $about['size_band'] ?? null,
            'objectives' => $objectives === [] ? null : $objectives,
        ]);
        if ($objectives !== []) {
            app(OnboardingEvents::class)->record($user, 'objectives_set', $team);
        }

        $pending = $this->context->pendingUrl();

        return $pending !== null
            ? redirect($pending)
            : redirect()->route('onboarding.recommendations')->with('status', 'Your business is set up.');
    }
}

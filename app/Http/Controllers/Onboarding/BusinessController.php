<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\TeamProfile;
use App\Services\Sso\BusinessOnboarding;
use App\Services\Sso\PlatformContext;
use App\Support\Industry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
    }

    public function store(Request $request, CreatesTeams $creator): RedirectResponse
    {
        $user = $request->user();

        // A second tap, or a person who already has a business: nothing is created twice.
        if (! $this->onboarding->needsBusiness($user)) {
            return redirect($this->context->pendingUrl() ?? route('dashboard'));
        }

        try {
            $creator->create($user, $request->only(['name', 'industry', 'description', 'visibility']));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $pending = $this->context->pendingUrl();

        return $pending !== null
            ? redirect($pending)
            : redirect()->route('dashboard')->with('status', 'Your business is set up.');
    }
}

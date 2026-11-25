<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\CompanyProfileImport;
use App\Models\TeamProfile;
use App\Models\User;
use App\Services\Growth\OnboardingEvents;
use App\Services\Onboarding\CompanyCapture;
use App\Services\Onboarding\DuplicateCompanyFound;
use App\Services\Sso\BusinessOnboarding;
use App\Services\Sso\PlatformContext;
use App\Support\Industry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Jetstream\Jetstream;

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
        $another = $this->wantsAnother($request);

        if (! $another && ! $this->onboarding->needsBusiness($request->user())) {
            return redirect($this->context->pendingUrl() ?? route('dashboard'));
        }

        abort_if($another && ! Gate::forUser($request->user())->allows('create', Jetstream::newTeamModel()), 403);

        return view('onboarding.business', $this->formData($another));
    }

    public function store(Request $request, CompanyCapture $capture): View|RedirectResponse
    {
        $user = $request->user();
        $another = $this->wantsAnother($request);

        // A second tap, or a person who already has a business: nothing is created twice. (Asking for another company is a choice, and the
        // duplicate check below catches an accidental second tap on that.)
        if (! $another && ! $this->onboarding->needsBusiness($user)) {
            return redirect($this->context->pendingUrl() ?? route('dashboard'));
        }

        abort_if($another && ! Gate::forUser($user)->allows('create', Jetstream::newTeamModel()), 403);

        $about = $request->validate([
            // R-62: a name and one sentence about what the business does are all that is asked up front; everything else comes later.
            'description' => ['required', 'string', 'max:600'],
            'stage' => ['nullable', Rule::in(array_keys(config('onboarding.stages')))],
            'size_band' => ['nullable', Rule::in(array_keys(config('onboarding.size_bands')))],
            'objectives' => ['nullable', 'array', 'max:'.(int) config('onboarding.max_objectives')],
            'objectives.*' => ['string', Rule::in(array_keys(config('onboarding.objectives')))],
        ], ['description.required' => 'Say what the business does in a sentence or two.']);

        $values = $request->only(['name', 'industry', 'description', 'visibility']);
        $objectives = array_values(array_unique($about['objectives'] ?? []));

        // The same steps whichever way the details arrive (R-62 phase 2): an import is started (or resumed after the duplicate question), the
        // typed values are proposed as its manual source, and confirming it creates the company through the ordinary team action.
        $import = $this->importFor($request, $user, $capture);
        $capture->propose($import, CompanyProfileImport::MANUAL, Arr::except($values, ['visibility']));

        try {
            $team = $capture->confirm($import, $user, $values, [
                'stage' => $about['stage'] ?? null,
                'size_band' => $about['size_band'] ?? null,
                'objectives' => $objectives === [] ? null : $objectives,
            ], $request->boolean('confirm_duplicate'));
        } catch (DuplicateCompanyFound $e) {
            return view('onboarding.duplicate', [
                'found' => $e->own,
                'elsewhere' => $e->elsewhere,
                'fields' => Arr::except($request->only(['name', 'industry', 'description', 'visibility', 'stage', 'size_band', 'objectives']), []),
                'import' => $import->id,
                'another' => $another,
            ]);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        if ($objectives !== []) {
            app(OnboardingEvents::class)->record($user, 'objectives_set', $team);
        }

        if ($another) {
            return redirect()->route('dashboard')->with('status', $team->name.' is created and is now your active company.');
        }

        $pending = $this->context->pendingUrl();

        return $pending !== null
            ? redirect($pending)
            : redirect()->route('onboarding.recommendations')->with('status', 'Your business is set up.');
    }

    /** Asking for a further company, from the menu, is a choice made by someone who already has one. */
    private function wantsAnother(Request $request): bool
    {
        return $request->boolean('another');
    }

    /** @return array<string, mixed> */
    private function formData(bool $another): array
    {
        return [
            'branding' => $another ? null : $this->context->branding(),
            'another' => $another,
            'industries' => Industry::options(),
            'visibility' => TeamProfile::VISIBILITY,
            'stages' => config('onboarding.stages'),
            'sizeBands' => config('onboarding.size_bands'),
            'objectives' => config('onboarding.objectives'),
            'maxObjectives' => (int) config('onboarding.max_objectives'),
        ];
    }

    /** The import the person is resuming (after the duplicate question), if it is theirs and still open; otherwise a new manual one. */
    private function importFor(Request $request, User $user, CompanyCapture $capture): CompanyProfileImport
    {
        $resume = $request->integer('import') > 0
            ? CompanyProfileImport::where('id', $request->integer('import'))->where('user_id', $user->id)->where('status', CompanyProfileImport::DRAFT)->first()
            : null;

        return $resume ?? $capture->start($user, CompanyProfileImport::MANUAL);
    }
}

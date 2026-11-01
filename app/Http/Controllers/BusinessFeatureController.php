<?php

namespace App\Http\Controllers;

use App\Models\BusinessFeature;
use App\Models\Team;
use App\Services\Directory\FeaturedPlacement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Featured placements (R-33 §8): the published rules, a business's request, and reviewers' decisions. */
class BusinessFeatureController extends Controller
{
    public function __construct(private FeaturedPlacement $featured) {}

    public function rules(): View
    {
        return view('businesses.featured-rules', ['rules' => FeaturedPlacement::RULES]);
    }

    public function request(Request $request, Team $team): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:600']]);
        $this->featured->request($request->user(), $team, $data['reason'] ?? null);

        return redirect()->route('business.edit', $team)->withFragment('featured')->with('status', 'Asked. A reviewer will decide and you will be told.');
    }

    public function decide(Request $request, BusinessFeature $feature, string $decision): RedirectResponse
    {
        $user = $request->user();

        match ($decision) {
            'grant' => $this->featured->grant($user, $feature, (int) $request->validate(['days' => ['required', 'integer', 'min:1', 'max:'.BusinessFeature::MAX_DAYS]])['days'], (string) $request->validate(['note' => ['required', 'string', 'min:5', 'max:600']])['note']),
            'decline' => $this->featured->decline($user, $feature, (string) $request->validate(['note' => ['required', 'string', 'min:10', 'max:600']])['note']),
            'end' => $this->featured->end($user, $feature),
            default => abort(404),
        };

        return redirect()->route('moderation.trust')->withFragment('featured')->with('status', 'Recorded. The business is told.');
    }
}

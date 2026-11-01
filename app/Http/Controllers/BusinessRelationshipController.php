<?php

namespace App\Http\Controllers;

use App\Models\BusinessRelationship;
use App\Models\Team;
use App\Services\Identity\IdentityProtection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Linked businesses (R-29 §19): an account names a verified business by its owner code and says
 * how they are related; the verified business's owner or an administrator confirms or declines.
 */
class BusinessRelationshipController extends Controller
{
    public function __construct(private readonly IdentityProtection $identity) {}

    public function claim(Request $request, Team $team): RedirectResponse
    {
        abort_if($team->personal_team, 404);
        Gate::authorize('updateProfile', $team);

        $data = $request->validate([
            'code' => ['required', 'string', 'size:4'],
            'kind' => ['required', Rule::in(array_keys(BusinessRelationship::KINDS))],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['code.size' => 'An owner code has four letters.']);

        $parent = Team::where('code', strtoupper($data['code']))->where('personal_team', false)->first();
        if (! $parent) {
            return back()->withErrors(['code' => 'No business has that owner code.'])->withInput();
        }

        $this->identity->claim($request->user(), $team, $parent, $data['kind'], $data['note'] ?? null);

        return back()->with('status', 'Sent to '.$parent->name.'. Their owner or an administrator confirms or declines it.');
    }

    public function decide(Request $request, Team $team, BusinessRelationship $relationship, string $decision): RedirectResponse
    {
        if ((int) $relationship->parent_team_id !== (int) $team->getKey()) {
            abort(404);
        }
        $this->identity->decide($request->user(), $relationship, $decision === 'confirm');

        return back()->with('status', $decision === 'confirm' ? 'Confirmed. The two businesses are linked.' : 'Declined.');
    }

    public function end(Request $request, Team $team, BusinessRelationship $relationship): RedirectResponse
    {
        if (! in_array((int) $team->getKey(), [(int) $relationship->team_id, (int) $relationship->parent_team_id], true)) {
            abort(404);
        }
        $this->identity->end($request->user(), $relationship);

        return back()->with('status', 'The link was ended.');
    }
}

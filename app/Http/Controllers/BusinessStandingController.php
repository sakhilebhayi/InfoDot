<?php

namespace App\Http\Controllers;

use App\Models\BusinessAppeal;
use App\Models\BusinessInvestigation;
use App\Models\BusinessVerification;
use App\Models\Team;
use App\Services\Trust\BusinessTrustService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * A business's standing on InfoDot, for its owner and administrators (R-29 §23): reviews it has
 * been told about, with InfoDot's explanation; decisions it can appeal; and its appeals. Reports
 * and who made them are never shown here.
 */
class BusinessStandingController extends Controller
{
    public function __construct(private BusinessTrustService $trust) {}

    public function show(Request $request, Team $team): View
    {
        Gate::authorize('updateProfile', $team);

        return view('trust.standing', [
            'team' => $team,
            'investigations' => BusinessInvestigation::where('team_id', $team->id)->latest('id')->get()->filter->isKnownToBusiness()->values(),
            'verifications' => BusinessVerification::where('team_id', $team->id)->whereIn('status', [BusinessVerification::REJECTED, BusinessVerification::SUSPENDED, BusinessVerification::REVOKED])->latest('id')->limit(5)->get(),
            'appeals' => BusinessAppeal::where('team_id', $team->id)->latest('id')->get(),
            'appealable' => $this->trust->appealable($team),
        ]);
    }

    public function appeal(Request $request, Team $team): RedirectResponse
    {
        Gate::authorize('updateProfile', $team);

        $data = $request->validate([
            'subject' => ['required', 'string', 'regex:/^(investigation|verification):\d+$/'],
            'body' => ['required', 'string', 'min:30', 'max:3000'],
            'files' => ['nullable', 'array', 'max:'.BusinessTrustService::MAX_FILES],
            'files.*' => ['file'],
        ], ['body.min' => 'Explain why the decision is wrong in at least :min characters — what the reviewer should look at again.']);

        [$type, $id] = explode(':', $data['subject']);
        $this->trust->appeal($request->user(), $team, $type, (int) $id, $data['body'], $request->file('files', []));

        return redirect()->route('business.standing', $team)->with('status', 'Your appeal is with InfoDot. A reviewer who did not make the original decision will decide it, and you will be told.');
    }
}

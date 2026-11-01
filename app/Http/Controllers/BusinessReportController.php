<?php

namespace App\Http\Controllers;

use App\Models\BusinessReport;
use App\Models\Team;
use App\Services\Trust\BusinessTrustService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Reporting a business (R-33 §7, R-29 §21): signed-in people, about businesses they can see. */
class BusinessReportController extends Controller
{
    public function __construct(private BusinessTrustService $trust) {}

    public function create(Request $request, Team $team): View
    {
        abort_unless($team->isVisibleTo($request->user()), 404);

        return view('trust.report', [
            'team' => $team,
            'reason' => $this->trust->whyNotReport($request->user(), $team),
            'categories' => BusinessReport::CATEGORIES,
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        abort_unless($team->isVisibleTo($request->user()), 404);

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(BusinessReport::CATEGORIES))],
            'detail' => ['required', 'string', 'min:20', 'max:2000'],
            'files' => ['nullable', 'array', 'max:'.BusinessTrustService::MAX_FILES],
            'files.*' => ['file'],
        ], ['detail.min' => 'Tell the reviewers a little more — at least :min characters — so they know where to look.']);

        $this->trust->report($request->user(), $team, $data, $request->file('files', []));

        return redirect()->route('business.show', $team)->with('status', 'Thank you. A reviewer will look at it; you will be told when it has been reviewed. Nothing changes for the business because of a report alone.');
    }
}

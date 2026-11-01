<?php

namespace App\Http\Controllers;

use App\Models\BusinessService;
use App\Models\Team;
use App\Services\Enquiries\EnquiryService;
use App\Services\Insights\BusinessInsights;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Business insights (R-33 §9): what the business's InfoDot presence did, from recorded events only,
 * for its owner and administrators. Also the endpoint the business page reports contact actions to.
 */
class BusinessInsightsController extends Controller
{
    public function __construct(private BusinessInsights $insights) {}

    public function show(Request $request, Team $team, EnquiryService $enquiries): View
    {
        Gate::authorize('updateProfile', $team);
        $days = (int) ($request->validate(['days' => ['nullable', 'in:30,90']])['days'] ?? 30);
        $report = $this->insights->report($team, $days);

        return view('business.insights', [
            'team' => $team,
            'r' => $report,
            'enquiries' => $enquiries->stats($team),
            'enquiriesInRange' => $team->enquiries()->where('created_at', '>=', now()->subDays($days))->count(),
            'serviceNames' => BusinessService::where('team_id', $team->id)->whereIn('id', collect($report['services'])->pluck('id'))->pluck('name', 'id'),
        ]);
    }

    /** A visitor used a contact action on the business page. Counted once per visitor, action and day. */
    public function track(Request $request, Team $team, string $action): Response
    {
        if ($team->isVisibleTo($request->user()) && $this->insights->counts($team, $request->user(), $request->userAgent())) {
            $this->insights->bump($team, $action, true);
        }

        return response()->noContent();
    }
}

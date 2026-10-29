<?php

namespace App\Http\Controllers;

use App\Models\Seminar;
use App\Models\SeminarReport;
use App\Services\Seminars\SeminarService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Moderators only (route middleware): reports about seminars, and removing a seminar. */
class SeminarModerationController extends Controller
{
    public function __construct(private readonly SeminarService $seminars) {}

    public function index(): View
    {
        return view('seminars.moderation', [
            'reports' => SeminarReport::with(['seminar', 'reporter'])->where('status', 'open')->orderBy('id')->get()->groupBy('seminar_id'),
            'recent' => SeminarReport::with(['seminar', 'reporter'])->where('status', '!=', 'open')->orderByDesc('id')->limit(15)->get(),
        ]);
    }

    public function remove(Request $request, Seminar $seminar): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        $this->seminars->removeByModerator($seminar, $request->user(), $data['reason']);

        return back()->with('seminar_notice', 'The seminar was removed. People who registered were told, anyone who paid is being refunded, and the host was told why.');
    }

    public function dismiss(Request $request, SeminarReport $report): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:500']]);
        abort_unless($report->status === 'open', 404);
        $this->seminars->dismissReport($report, $request->user(), $data['note']);

        return back()->with('seminar_notice', 'The report was dismissed.');
    }
}

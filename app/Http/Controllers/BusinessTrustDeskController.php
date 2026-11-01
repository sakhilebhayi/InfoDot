<?php

namespace App\Http\Controllers;

use App\Models\BusinessAppeal;
use App\Models\BusinessInvestigation;
use App\Models\BusinessReport;
use App\Models\IdentityConflict;
use App\Models\Team;
use App\Services\Trust\BusinessTrustService;
use App\Services\Verification\VerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The reviewers' desk for business trust (R-29 §22): reports, investigations and appeals. A
 * reviewer who belongs to the business may read but not act; an appeal is decided by someone
 * other than the person whose decision is appealed.
 */
class BusinessTrustDeskController extends Controller
{
    public function __construct(private BusinessTrustService $trust, private VerificationService $verification) {}

    public function index(): View
    {
        $order = "case priority when 'urgent' then 0 when 'high' then 1 else 2 end";

        return view('moderation.trust', [
            'reports' => BusinessReport::where('status', BusinessReport::OPEN)->with('team:id,name,code')->orderByRaw($order)->oldest()->limit(100)->get(),
            'investigations' => BusinessInvestigation::where('status', BusinessInvestigation::OPEN)->with('team:id,name,code')->withCount('reports')->oldest()->get(),
            'appeals' => BusinessAppeal::where('status', BusinessAppeal::OPEN)->with('team:id,name,code')->oldest()->get(),
            'recent' => BusinessInvestigation::where('status', '!=', BusinessInvestigation::OPEN)->with('team:id,name')->latest('decided_at')->limit(15)->get(),
        ]);
    }

    public function report(Request $request, BusinessReport $report): View
    {
        $team = $report->team;

        return view('moderation.trust-report', [
            'report' => $report->load('reporter:id,name'),
            'team' => $team,
            'files' => $this->trust->files($report),
            'others' => BusinessReport::where('team_id', $team->id)->whereKeyNot($report->id)->latest()->limit(20)->get(),
            'open' => BusinessInvestigation::where('team_id', $team->id)->where('status', BusinessInvestigation::OPEN)->first(),
            'canAct' => $this->verification->canReview($request->user(), $team),
        ]);
    }

    public function resolveReport(Request $request, BusinessReport $report, string $outcome): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:10', 'max:2000']]);

        if ($outcome === 'link') {
            $open = BusinessInvestigation::where('team_id', $report->team_id)->where('status', BusinessInvestigation::OPEN)->firstOrFail();
            $this->trust->linkReport($request->user(), $open, $report);
            $this->trust->note($request->user(), $open, $data['note']);

            return redirect()->route('moderation.trust.investigation', $open)->with('status', 'Report linked to this investigation.');
        }

        $this->trust->resolveReport($request->user(), $report, $outcome === 'action', $data['note']);

        return redirect()->route('moderation.trust')->with('status', 'Report closed. The reporter is told it was reviewed.');
    }

    public function openInvestigation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'concern' => ['required', Rule::in(array_keys(BusinessInvestigation::CONCERNS))],
            'summary' => ['required', 'string', 'min:10', 'max:3000'],
            'report_ids' => ['nullable', 'array'],
            'report_ids.*' => ['integer'],
            'restrict' => ['nullable', 'boolean'],
            'message' => ['nullable', 'string', 'min:10', 'max:2000', 'required_if:restrict,1'],
            'conflict_id' => ['nullable', 'integer', 'exists:identity_conflicts,id'],
        ], ['message.required_if' => 'Taking a business out of the directory needs a message telling it why and what it can send.']);

        $inv = $this->trust->open(
            $request->user(),
            Team::findOrFail($data['team_id']),
            $data['concern'],
            $data['summary'],
            array_map('intval', $data['report_ids'] ?? []),
            (bool) ($data['restrict'] ?? false),
            $data['message'] ?? null,
            isset($data['conflict_id']) ? IdentityConflict::find($data['conflict_id']) : null,
        );

        return redirect()->route('moderation.trust.investigation', $inv)->with('status', 'Investigation opened.');
    }

    public function investigation(Request $request, BusinessInvestigation $investigation): View
    {
        $team = $investigation->team;

        return view('moderation.trust-investigation', [
            'inv' => $investigation->load(['events.user:id,name', 'reports', 'opener:id,name', 'decider:id,name']),
            'team' => $team,
            'standing' => $team->standing,
            'unlinked' => BusinessReport::where('team_id', $team->id)->where('status', BusinessReport::OPEN)->get(),
            'appeal' => BusinessAppeal::where('subject_type', 'investigation')->where('subject_id', $investigation->id)->first(),
            'canAct' => $this->verification->canReview($request->user(), $team),
        ]);
    }

    public function act(Request $request, BusinessInvestigation $investigation, string $action): RedirectResponse
    {
        $user = $request->user();
        $text = fn (int $min = 10) => (string) $request->validate(['text' => ['required', 'string', 'min:'.$min, 'max:3000']])['text'];

        match ($action) {
            'note' => $this->trust->note($user, $investigation, $text()),
            'message' => $this->trust->message($user, $investigation, $text()),
            'restrict' => $this->trust->restrict($user, $investigation),
            'lift' => $this->trust->lift($user, $investigation),
            'conclude' => $this->trust->conclude($user, $investigation, (string) $request->validate(['outcome' => ['required', Rule::in([BusinessInvestigation::CLEARED, BusinessInvestigation::CONFIRMED_IMPERSONATION, BusinessInvestigation::CONFIRMED_MISCONDUCT, BusinessInvestigation::CORRECTED])]])['outcome'], $text(20)),
            default => abort(404),
        };

        return redirect()->route('moderation.trust.investigation', $investigation)->with('status', 'Recorded.');
    }

    public function appeal(Request $request, BusinessAppeal $appeal): View
    {
        return view('moderation.trust-appeal', [
            'appeal' => $appeal->load(['team', 'appellant:id,name', 'decider:id,name']),
            'subject' => $appeal->subject(),
            'files' => $this->trust->files($appeal),
            'canDecide' => $this->trust->canDecideAppeal($request->user(), $appeal),
        ]);
    }

    public function decideAppeal(Request $request, BusinessAppeal $appeal, string $decision): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:20', 'max:3000']]);
        $this->trust->decideAppeal($request->user(), $appeal, $decision === 'uphold', $data['note']);

        return redirect()->route('moderation.trust')->with('status', 'Appeal decided. The business is told.');
    }
}

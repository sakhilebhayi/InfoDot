<?php

namespace App\Http\Controllers;

use App\Models\BusinessRelationship;
use App\Models\BusinessVerification;
use App\Models\IdentityConflict;
use App\Models\Team;
use App\Services\Identity\IdentityProtection;
use App\Services\Verification\VerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The business's side of verification (R-29 §9): apply, add evidence, confirm an official email,
 * answer a request for more information, or withdraw. Only the business's owner and
 * administrators — the people who may edit its profile — can reach any of it. The status itself
 * is never a form field: it moves only through VerificationService.
 */
class BusinessVerificationController extends Controller
{
    /** Evidence labels the form offers. ID documents are deliberately not on the list (POPIA minimality). */
    public const EVIDENCE_LABELS = [
        'registration' => 'Registration certificate (e.g. CIPC CoR 14.3)',
        'founding' => 'Founding document (partnership agreement, trust deed, constitution)',
        'authority' => 'Authority to act for the business (resolution or letter)',
        'address' => 'Proof of the operating address',
        'other' => 'Other supporting document',
    ];

    public function __construct(private readonly VerificationService $verification, private readonly IdentityProtection $identity) {}

    public function show(Request $request, Team $team): View
    {
        $this->authoriseBusiness($team);

        $open = $team->openVerification();
        $standing = $team->standing;
        $current = $open ?? $team->verifications()->first();

        return view('business.verification', [
            'team' => $team,
            'profile' => $team->profile,
            'open' => $open?->load('evidence'),
            'standing' => $standing,
            'latest' => $current,
            'history' => $team->verifications()->with('events')->limit(5)->get(),
            'labels' => self::EVIDENCE_LABELS,
            // R-29 Part C: links to other businesses, alerts about accounts matching this one,
            // and whether an extra check applies to this account.
            'outgoing' => BusinessRelationship::where('team_id', $team->id)->whereIn('status', ['pending', 'confirmed'])->with('parent')->get(),
            'incoming' => BusinessRelationship::where('parent_team_id', $team->id)->whereIn('status', ['pending', 'confirmed'])->with('team')->get(),
            'alerts' => IdentityConflict::where('protected_team_id', $team->id)->where('level', '!=', 'low')->whereIn('status', ['open', 'escalated'])->with('subject')->latest()->get(),
            'extraCheck' => $this->identity->blockingConflict($team) !== null,
            'kinds' => BusinessRelationship::KINDS,
            'canApply' => $open === null && $standing?->status !== BusinessVerification::SUSPENDED
                && ! ($standing?->isCurrent() && $standing->expires_at?->greaterThan(now()->addDays((int) config('verification.renewal_warning_days')))),
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->authoriseBusiness($team);

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(BusinessVerification::CATEGORIES))],
            'registered_name' => ['required', 'string', 'max:160'],
            'trading_name' => ['nullable', 'string', 'max:160'],
            'registration_number' => ['nullable', 'required_if:category,registered_company', 'string', 'max:60'],
            'registration_country' => ['required', 'string', 'max:60'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:500'],
            'applicant_role' => ['required', 'string', 'max:80'],
            'declaration' => ['accepted'],
            'evidence' => ['required', 'array', 'min:1', 'max:'.config('verification.max_evidence_files')],
            'evidence.*.file' => ['required', 'file'],
            'evidence.*.label' => ['required', Rule::in(array_keys(self::EVIDENCE_LABELS))],
        ], [
            'registration_number.required_if' => 'A registered company needs its registration number.',
            'declaration.accepted' => 'Confirm you are authorised to act for this business.',
            'evidence.required' => 'Attach at least one document — for example the registration certificate.',
        ]);

        $this->verification->apply($request->user(), $team, collect($data)->except(['declaration', 'evidence'])->all(), $this->evidence($data['evidence']));

        return redirect()->route('business.verification', $team)->with('status', 'Your application is with InfoDot. A reviewer who is not part of your business will look at it; you will hear here and in your notices.');
    }

    public function addEvidence(Request $request, Team $team): RedirectResponse
    {
        $open = $this->openFor($team);
        $data = $request->validate([
            'evidence' => ['required', 'array', 'min:1'],
            'evidence.*.file' => ['required', 'file'],
            'evidence.*.label' => ['required', Rule::in(array_keys(self::EVIDENCE_LABELS))],
        ]);

        $this->verification->addEvidence($request->user(), $open, $this->evidence($data['evidence']));

        return back()->with('status', 'Added to the application.');
    }

    public function resubmit(Request $request, Team $team): RedirectResponse
    {
        $this->verification->resubmit($request->user(), $this->openFor($team));

        return back()->with('status', 'Sent back to the reviewers with what you added.');
    }

    public function withdraw(Request $request, Team $team): RedirectResponse
    {
        $this->verification->withdraw($request->user(), $this->openFor($team));

        return back()->with('status', 'The application was withdrawn. You can apply again at any time.');
    }

    public function sendCode(Request $request, Team $team): RedirectResponse
    {
        $open = $this->openFor($team);
        $data = $request->validate(['official_email' => ['required', 'email', 'max:255']]);

        $this->verification->sendEmailCode($request->user(), $open, $data['official_email']);

        return back()->with('status', 'A six-digit code is on its way to '.$data['official_email'].'. It works for '.config('verification.email_code.ttl_minutes').' minutes.');
    }

    public function confirmCode(Request $request, Team $team): RedirectResponse
    {
        $open = $this->openFor($team);
        $data = $request->validate(['code' => ['required', 'string', 'max:12']]);

        $this->verification->confirmEmailCode($request->user(), $open, $data['code']);

        return back()->with('status', 'Confirmed: the business controls '.$open->official_email.'.');
    }

    private function authoriseBusiness(Team $team): void
    {
        abort_if($team->personal_team, 404);
        Gate::authorize('updateProfile', $team);
    }

    private function openFor(Team $team): BusinessVerification
    {
        $this->authoriseBusiness($team);

        return $team->openVerification() ?? abort(404);
    }

    /**
     * @param  array<int, array{file: UploadedFile, label: string}>  $rows
     * @return array<int, array{file: UploadedFile, label: string}>
     */
    private function evidence(array $rows): array
    {
        return array_values(array_map(fn (array $row) => ['file' => $row['file'], 'label' => self::EVIDENCE_LABELS[$row['label']]], $rows));
    }
}

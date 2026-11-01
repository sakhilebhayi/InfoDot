<?php

namespace App\Http\Controllers;

use App\Models\BusinessVerification;
use App\Services\Verification\VerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The reviewer desk (R-29 §9F manual verification), for moderators (route middleware). A
 * reviewer who belongs to the business can read the queue but every decision refuses them —
 * the service enforces it, not this page.
 */
class VerificationDeskController extends Controller
{
    public function __construct(private readonly VerificationService $verification) {}

    public function index(Request $request): View
    {
        $base = BusinessVerification::with(['team', 'applicant'])->withCount('evidence');

        return view('moderation.verifications', [
            'waiting' => (clone $base)->whereIn('status', [BusinessVerification::PENDING, BusinessVerification::UNDER_REVIEW])->orderBy('updated_at')->get(),
            'withBusiness' => (clone $base)->where('status', BusinessVerification::INFO_REQUIRED)->orderBy('updated_at')->get(),
            'standing' => (clone $base)->where('status', BusinessVerification::SUSPENDED)->orderBy('updated_at')->get(),
            'recent' => (clone $base)->whereIn('status', [BusinessVerification::VERIFIED, BusinessVerification::REJECTED, BusinessVerification::REVOKED])->latest('decided_at')->limit(15)->get(),
        ]);
    }

    public function show(Request $request, BusinessVerification $verification): View
    {
        return view('moderation.verification', [
            'v' => $verification->load(['team.profile', 'applicant', 'decider', 'evidence', 'events.actor']),
            'canDecide' => $this->verification->canReview($request->user(), $verification->team),
            'checks' => BusinessVerification::CHECKS,
        ]);
    }

    public function decide(Request $request, BusinessVerification $verification, string $action): RedirectResponse
    {
        $needsMessage = in_array($action, ['info', 'reject', 'suspend', 'revoke'], true);
        $data = $request->validate([
            'message' => [$needsMessage ? 'required' : 'nullable', 'string', 'min:10', 'max:2000'],
            'note' => ['nullable', 'string', 'max:2000'],
            'checks' => ['nullable', 'array'],
            'checks.*' => [Rule::in(array_keys(BusinessVerification::CHECKS))],
        ], ['message.required' => 'Write what the business needs to know — it is shown to them.']);

        $user = $request->user();
        $message = (string) ($data['message'] ?? '');
        $note = $data['note'] ?? null;

        match ($action) {
            'review' => $this->verification->startReview($user, $verification),
            'info' => $this->verification->requestInformation($user, $verification, $message, $note),
            'approve' => $this->verification->approve($user, $verification, $data['checks'] ?? [], $note),
            'reject' => $this->verification->reject($user, $verification, $message, $note),
            'reconfirm' => $this->verification->reconfirm($user, $verification, $note),
            'suspend' => $this->verification->suspend($user, $verification, $message, $note),
            'reinstate' => $this->verification->reinstate($user, $verification, $note),
            'revoke' => $this->verification->revoke($user, $verification, $message, $note),
            default => abort(404),
        };

        return redirect()->route('moderation.verifications.show', $verification)->with('status', [
            'review' => 'You are reviewing this application.',
            'info' => 'The business was asked for more information.',
            'approve' => 'Verified. The badge is on the business page.',
            'reject' => 'Not approved. The business was told why.',
            'reconfirm' => 'The new details are confirmed; the badge is back.',
            'suspend' => 'Suspended. The badge is off and the business was told why.',
            'reinstate' => 'Reinstated.',
            'revoke' => 'Revoked. The business was told why.',
        ][$action]);
    }
}

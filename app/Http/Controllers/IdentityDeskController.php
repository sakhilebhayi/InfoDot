<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\BusinessRelationship;
use App\Models\IdentityConflict;
use App\Services\Identity\IdentityProtection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Identity conflicts for reviewers (R-29 §18–20), moderators only (route middleware). Deciding
 * needs a reviewer who belongs to neither business. The outcomes here are deliberately not
 * accusations: different businesses, related businesses, or escalated for investigation.
 */
class IdentityDeskController extends Controller
{
    public function __construct(private readonly IdentityProtection $identity) {}

    public function index(): View
    {
        $base = IdentityConflict::with(['subject', 'protected']);

        return view('moderation.identity', [
            'open' => (clone $base)->whereIn('status', ['open', 'escalated'])
                ->orderByRaw("case level when 'critical' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")
                ->orderByDesc('score')->get(),
            'recent' => (clone $base)->whereIn('status', ['dismissed', 'related'])->latest('resolved_at')->limit(15)->get(),
        ]);
    }

    public function show(Request $request, IdentityConflict $conflict): View
    {
        $conflict->load(['subject.profile', 'protected.profile', 'resolver']);

        return view('moderation.identity-conflict', [
            'c' => $conflict,
            'canDecide' => $this->identity->canReview($request->user(), $conflict),
            'subjectLogo' => Attachment::shown($conflict->subject->currentMedia('logo')),
            'subjectCover' => Attachment::shown($conflict->subject->currentMedia('cover')),
            'protectedLogo' => Attachment::shown($conflict->protected->currentMedia('logo')),
            'protectedCover' => Attachment::shown($conflict->protected->currentMedia('cover')),
            'kinds' => BusinessRelationship::KINDS,
        ]);
    }

    public function resolve(Request $request, IdentityConflict $conflict, string $action): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'min:10', 'max:2000'],
            'kind' => [$action === 'relate' ? 'required' : 'nullable', Rule::in(array_keys(BusinessRelationship::KINDS))],
        ], ['note.required' => 'Write down why — the next reviewer reads it.']);

        $this->identity->resolve($request->user(), $conflict, $action, $data['note'], $data['kind'] ?? null);

        return redirect()->route('moderation.identity.show', $conflict)->with('status', [
            'dismiss' => 'Dismissed: different businesses. A stronger match later would reopen it.',
            'relate' => 'Recorded as related businesses.',
            'escalate' => 'Escalated. Verification stays withheld until the investigation decides.',
        ][$action]);
    }
}

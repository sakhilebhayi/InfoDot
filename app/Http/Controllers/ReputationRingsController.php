<?php

namespace App\Http\Controllers;

use App\Models\ReputationRingFlag;
use App\Models\User;
use App\Services\Reputation\ReputationEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Moderators only (route middleware). Advisory ring flags: names, counts and ratios, never content. The people in a flag are not told
 * and see nothing change except their score. Off (404) unless REPUTATION_ENABLED.
 */
class ReputationRingsController extends Controller
{
    private function switchedOn(): void
    {
        abort_unless(config('reputation.enabled') && config('reputation.rings.enabled', true), 404);
    }

    public function index(Request $request): View
    {
        $this->switchedOn();
        $status = in_array($request->query('status'), [ReputationRingFlag::OPEN, ReputationRingFlag::CONFIRMED, ReputationRingFlag::DISMISSED], true) ? $request->query('status') : ReputationRingFlag::OPEN;
        $flags = ReputationRingFlag::with('reviewer:id,name')->where('status', $status)->orderByDesc('detected_at')->orderByDesc('id')->limit(100)->get();
        $names = User::whereIn('id', $flags->pluck('member_ids')->flatten()->unique()->all())->pluck('name', 'id');

        return view('reputation.rings', [
            'flags' => $flags, 'names' => $names, 'status' => $status,
            'counts' => ReputationRingFlag::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'discount' => (float) config('reputation.rings.discount_factor', 0.25),
        ]);
    }

    public function review(Request $request, ReputationRingFlag $flag, ReputationEngine $engine): RedirectResponse
    {
        $this->switchedOn();
        $data = $request->validate([
            'decision' => ['required', 'in:dismissed,confirmed'],
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $flag->forceFill(['status' => $data['decision'], 'reviewed_by' => $request->user()->id, 'review_note' => trim($data['note']), 'reviewed_at' => now()])->save();

        // Scores reflect the decision straight away; nobody is told, and a person who switched their score off keeps nothing.
        User::whereIn('id', $flag->member_ids)->get()->each(fn (User $member) => $engine->snapshot($member));

        return redirect()->route('moderation.rings')->with('rings_notice', $data['decision'] === 'dismissed'
            ? 'Dismissed. The flag no longer affects anyone\'s score.'
            : 'Confirmed. Acceptances between these accounts keep counting for less.');
    }
}

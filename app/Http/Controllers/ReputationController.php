<?php

namespace App\Http\Controllers;

use App\Models\ReputationAppeal;
use App\Models\ReputationSnapshot;
use App\Services\Reputation\ReputationEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Your own progress, and nobody else's. Off until REPUTATION_ENABLED. Every action is scoped to the signed-in person; none takes a user id. */
class ReputationController extends Controller
{
    public function __construct(private readonly ReputationEngine $engine) {}

    /** Checked on every action (not in the constructor): a route's controller instance is reused. */
    private function switchedOn(): void
    {
        abort_unless(config('reputation.enabled'), 404);
    }

    public function show(Request $request): View
    {
        $this->switchedOn();
        $user = $request->user();
        $snapshot = $this->engine->snapshot($user);

        return view('reputation.show', [
            'result' => $this->engine->compute($user), 'snapshot' => $snapshot,
            'appeals' => ReputationAppeal::where('user_id', $user->id)->latest('id')->limit(5)->get(),
            'history' => ReputationSnapshot::where('user_id', $user->id)->latest('id')->limit(6)->get(),
        ]);
    }

    public function appeal(Request $request): RedirectResponse
    {
        $this->switchedOn();
        $data = $request->validate(['message' => ['required', 'string', 'min:10', 'max:1000']]);
        $user = $request->user();

        if (ReputationAppeal::where('user_id', $user->id)->where('status', 'open')->exists()) {
            return back()->with('reputation_notice', 'You already have an appeal waiting for a moderator.');
        }

        ReputationAppeal::create(['user_id' => $user->id, 'snapshot_id' => $this->engine->snapshot($user)->id, 'message' => $data['message']]);

        return back()->with('reputation_notice', 'Your appeal was sent. A moderator will check how your score was worked out and reply here.');
    }

    public function appeals(): View
    {
        $this->switchedOn();
        return view('reputation.appeals', ['appeals' => ReputationAppeal::with('user')->where('status', 'open')->oldest('id')->get(), 'engine' => $this->engine]);
    }

    public function resolve(Request $request, ReputationAppeal $appeal): RedirectResponse
    {
        $this->switchedOn();
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:1000']]);
        abort_unless($appeal->status === 'open', 404);

        $this->engine->snapshot($appeal->user); // recalculated from the facts as they are now
        $appeal->forceFill(['status' => 'reviewed', 'resolved_by' => $request->user()->id, 'resolution_note' => $data['note'], 'resolved_at' => now()])->save();

        return redirect()->route('moderation.reputation')->with('reputation_notice', 'Reviewed. The person sees your note and the recalculated score.');
    }
}

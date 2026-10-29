<?php

namespace App\Http\Controllers;

use App\Models\Seminar;
use App\Models\User;
use App\Services\Seminars\LiveKit;
use App\Services\Seminars\MarketplaceUnavailable;
use App\Services\Seminars\SeminarService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The seminar's InfoDot room. The page is open only to the host and to people who registered, inside the window; the token is fetched when they connect. */
class SeminarRoomController extends Controller
{
    public function __construct(private readonly SeminarService $seminars, private readonly LiveKit $livekit) {}

    public function show(Request $request, Seminar $seminar): View|RedirectResponse
    {
        $why = $this->why($request, $seminar);

        if ($why !== null) {
            return redirect()->route('seminars.show', $seminar)->with('seminar_notice', $why);
        }

        return view('seminars.room', ['seminar' => $seminar, 'isHost' => $seminar->host_id === $request->user()->id, 'name' => $request->user()->name]);
    }

    /** A fresh short-lived join token. Never cached, never logged. */
    public function token(Request $request, Seminar $seminar): JsonResponse
    {
        $why = $this->why($request, $seminar);

        if ($why !== null) {
            return response()->json(['error' => $why], 403, ['Cache-Control' => 'no-store']);
        }

        return response()->json($this->livekit->joinToken($seminar, $request->user()), 200, ['Cache-Control' => 'no-store, private']);
    }

    /** The host removes someone from the room. They stay registered; this only disconnects them. */
    public function remove(Request $request, Seminar $seminar, int $person): RedirectResponse
    {
        abort_unless($seminar->host_id === $request->user()->id && $this->livekit->configured() && $seminar->isRoom(), 403);

        try {
            $target = User::findOrFail($person);
            \Illuminate\Support\Facades\DB::table('seminar_room_bans')->insertOrIgnore(['seminar_id' => $seminar->id, 'user_id' => $target->id, 'created_at' => now()]);   // they cannot get a new token
            $this->livekit->remove($seminar, $target);
        } catch (MarketplaceUnavailable $e) {
            return back()->withErrors(['room' => $e->getMessage()]);
        }

        return back()->with('seminar_notice', 'They were removed from the room.');
    }

    private function why(Request $request, Seminar $seminar): ?string
    {
        if (! $this->livekit->configured()) {
            abort(404);
        }

        return $this->seminars->roomRefusal($seminar, $request->user());
    }
}

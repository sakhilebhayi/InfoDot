<?php

namespace App\Http\Controllers;

use App\Models\Seminar;
use App\Models\User;
use App\Services\Seminars\P2pRoom;
use App\Services\Seminars\SeminarService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The peer-to-peer room: the page, and the small JSON calls the browser makes to find who feeds it and to pass connection messages. No video
 * ever reaches the server. Every call checks again that the person may be in this room right now.
 */
class SeminarP2pController extends Controller
{
    public function __construct(private readonly P2pRoom $room) {}

    public function show(Request $request, Seminar $seminar): View|RedirectResponse
    {
        $why = $this->why($request, $seminar);

        if ($why !== null) {
            return redirect()->route('seminars.show', $seminar)->with('seminar_notice', $why);
        }

        return view('seminars.p2p-room', ['seminar' => $seminar, 'isHost' => $seminar->host_id === $request->user()->id]);
    }

    public function join(Request $request, Seminar $seminar): JsonResponse
    {
        $this->guard($request, $seminar);
        $data = $request->validate(['can_relay' => ['nullable', 'boolean']]);

        return $this->json($this->room->join($seminar, $request->user(), (bool) ($data['can_relay'] ?? false)));
    }

    public function poll(Request $request, Seminar $seminar): JsonResponse
    {
        // Someone the host removed still gets an answer, so their page can say so, instead of a bare refusal.
        if (! $this->room->isRemoved($seminar, $request->user())) {
            $this->guard($request, $seminar);
        }

        return $this->json($this->room->poll($seminar, $request->user(), (int) $request->query('chat', 0)));
    }

    public function signal(Request $request, Seminar $seminar): JsonResponse
    {
        $this->guard($request, $seminar);
        $data = $request->validate(['to' => ['required', 'uuid'], 'type' => ['required', 'in:hello,offer,answer,ice'], 'payload' => ['nullable', 'string', 'max:20000']]);
        $this->room->signal($seminar, $request->user(), $data['to'], $data['type'], $data['payload'] ?? null);

        return $this->json(['ok' => true]);
    }

    public function ready(Request $request, Seminar $seminar): JsonResponse
    {
        $this->guard($request, $seminar);
        $this->room->ready($seminar, $request->user());

        return $this->json(['ok' => true]);
    }

    public function report(Request $request, Seminar $seminar): JsonResponse
    {
        $this->guard($request, $seminar);

        return $this->json($this->room->report($seminar, $request->user()));
    }

    /** Deliberately lenient: it only ever ends the caller's own place, so even someone who is no longer allowed in can always leave. */
    public function leave(Request $request, Seminar $seminar): JsonResponse
    {
        abort_unless(config('seminars.p2p.enabled'), 404);
        $this->room->leave($seminar, $request->user());

        return $this->json(['ok' => true]);
    }

    public function chat(Request $request, Seminar $seminar): JsonResponse
    {
        $this->guard($request, $seminar);
        $data = $request->validate(['text' => ['required', 'string', 'max:300']]);
        $this->room->chat($seminar, $request->user(), $data['text']);

        return $this->json(['ok' => true]);
    }

    /** The host removes someone from the room. They stay registered. */
    public function remove(Request $request, Seminar $seminar, int $person): RedirectResponse
    {
        abort_unless(config('seminars.p2p.enabled') && $seminar->isP2p() && $seminar->host_id === $request->user()->id, 403);
        $this->room->remove($seminar, User::findOrFail($person));

        return back()->with('seminar_notice', 'They were removed from the room.');
    }

    /**
     * A host's upload test: the browser sends a block of data and times it. Nothing is kept. The server only checks the size so this cannot be
     * used to fill the disk or the line.
     */
    public function speedtest(Request $request): JsonResponse
    {
        abort_unless(config('seminars.p2p.enabled'), 404);
        $bytes = strlen($request->getContent());
        abort_if($bytes < 50_000 || $bytes > (int) config('seminars.p2p.speedtest_max_bytes', 3_000_000), 422);

        return $this->json(['bytes' => $bytes]);
    }

    /** What each quality would allow at a measured upload speed (kbps), for the host's form. */
    public function plan(Request $request): JsonResponse
    {
        abort_unless(config('seminars.p2p.enabled'), 404);
        $kbps = (int) $request->query('kbps');
        abort_if($kbps < 50 || $kbps > 1_000_000, 422);

        return $this->json(\App\Services\Seminars\P2pCapacity::all($kbps));
    }

    private function guard(Request $request, Seminar $seminar): void
    {
        abort_if($this->why($request, $seminar) !== null, 403);
    }

    private function why(Request $request, Seminar $seminar): ?string
    {
        abort_unless(config('seminars.p2p.enabled'), 404);

        return $this->room->refusal($seminar, $request->user());
    }

    private function json(array $body): JsonResponse
    {
        return response()->json($body, 200, ['Cache-Control' => 'no-store, private']);
    }
}

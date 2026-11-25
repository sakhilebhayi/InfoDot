<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use App\Services\Licensing\SeatLedger;
use App\Support\Toast;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\AddsTeamMembers;

/** The signed-in person's own notices. Every action is scoped to the signed-in person; none takes a user id. */
class NoticesController extends Controller
{
    public function __construct(private readonly InAppNotices $notices) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $archived = $request->query('show') === 'archived';
        $category = is_string($request->query('category')) && isset(UserNotice::CATEGORIES[$request->query('category')]) ? (string) $request->query('category') : null;

        $page = $this->notices->page($user, 30, $category, $archived)->withQueryString();
        $this->notices->preload($page->getCollection());
        $rows = $page->getCollection()->map(fn ($n) => ['notice' => $n] + $this->notices->describe($n));

        return view('notices.index', [
            'page' => $page, 'rows' => $rows, 'unread' => $this->notices->unreadCount($user), 'wants' => $user->wantsInAppNotices(),
            'category' => $category, 'archived' => $archived, 'counts' => $this->notices->categoryCounts($user, $archived),
            'archivedTotal' => UserNotice::where('user_id', $user->id)->whereNotNull('archived_at')->count(),
        ]);
    }

    /** Marks one read and opens what it is about (or goes back to the list when that is no longer available). */
    public function open(Request $request, int $id): RedirectResponse
    {
        $notice = $this->notices->markRead($request->user(), $id);
        abort_if($notice === null, 404);

        $url = $this->notices->describe($notice)['url'];

        return $url !== null ? redirect()->to($url) : redirect()->route('notices');
    }

    /** Back to the page the person was on (a category, the archive), or the list itself when there is no such page. */
    private function backToList(): RedirectResponse
    {
        return redirect()->back(fallback: route('notices'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $count = $this->notices->markAllRead($request->user());

        return $this->backToList()->with('notices_notice', $count > 0 ? 'All marked as read.' : 'Nothing was unread.');
    }

    /** Puts one notice away. It stays in the archive until it is old enough to be removed. */
    public function archive(Request $request, int $id): RedirectResponse
    {
        abort_if($this->notices->archive($request->user(), $id) === null, 404);

        Toast::flash('Put away. It is in the archive.');

        return $this->backToList();
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        abort_if($this->notices->restore($request->user(), $id) === null, 404);

        Toast::flash('Brought back to your list.');

        return $this->backToList();
    }

    public function archiveRead(Request $request): RedirectResponse
    {
        $count = $this->notices->archiveRead($request->user());

        return $this->backToList()->with('notices_notice', $count > 0 ? $count.' read '.($count === 1 ? 'notification' : 'notifications').' put away.' : 'There was nothing read to put away.');
    }

    /**
     * What arrived since the page was drawn, for the pop-ups. The page sends the newest id it knew about; only the person's own newer, unread notices come
     * back, each described from what they may see now. Polled while the page is open and visible.
     */
    public function arrivals(Request $request): JsonResponse
    {
        $user = $request->user();
        $after = max(0, (int) $request->query('after', 0));
        $fresh = $this->notices->arrivedAfter($user, $after);
        $this->notices->preload($fresh);

        return response()->json([
            'unread' => $this->notices->unreadCount($user),
            'last' => max($after, (int) ($fresh->max('id') ?? $this->notices->latestId($user))),
            'notices' => $fresh->map(function (UserNotice $n) {
                $d = $this->notices->describe($n);

                return [
                    'id' => $n->id, 'label' => InAppNotices::label($n->kind), 'text' => $d['text'],
                    'href' => ($d['url'] !== null || isset($d['actions'])) ? route('notices.open', ['id' => $n->id]) : route('notices'),
                    'actionable' => isset($d['actions']),
                ];
            })->values(),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Accept or decline a company invitation from its notice. Only the person the invitation was sent to may answer it, and only while it is open;
     * accepting does exactly what the link in the email does (a seat is taken under the company's lock), declining removes the invitation.
     */
    public function answerInvitation(Request $request, int $id, string $answer): RedirectResponse
    {
        abort_unless(in_array($answer, ['accept', 'decline'], true), 404);

        $user = $request->user();
        $notice = UserNotice::where('user_id', $user->id)->where('kind', UserNotice::TEAM_INVITATION)->whereKey($id)->firstOrFail();

        $closed = fn (string $why) => tap(redirect()->route('notices')->with('notices_notice', $why), fn () => $this->notices->archive($user, $id));

        // The invitation row is locked while it is answered, so two clicks (or a click and the emailed link) cannot both use it.
        return DB::transaction(function () use ($notice, $user, $id, $answer, $closed) {
            $invitation = TeamInvitation::whereKey($notice->subject_id)->lockForUpdate()->first();
            $team = $invitation === null ? null : Team::find($invitation->team_id);
            $owner = $team?->owner;

            if ($invitation === null || $team === null || $owner === null || $invitation->isExpired() || ! InAppNotices::invitationIsFor($invitation, $user)) {
                return $closed('That invitation is no longer open.');
            }

            if ($answer === 'decline') {
                app(SeatLedger::class)->record($team, $user, 'invitation_cancelled', $invitation->email, ['reason' => 'declined']);
                $invitation->delete();
                $this->notices->archive($user, $id);

                return redirect()->route('notices')->with('notices_notice', 'You declined the invitation to join '.$team->name.'.');
            }

            if ($user->belongsToTeam($team)) {
                $invitation->delete();

                return $closed('You are already in '.$team->name.'.');
            }

            try {
                // The address on the account is the one that finds the account; the invitation was matched to it without regard to case.
                app(AddsTeamMembers::class)->add($owner, $team, $user->email, $invitation->role);
            } catch (ValidationException $e) {
                return redirect()->route('notices')->with('notices_notice', (string) collect($e->errors())->flatten()->first());
            }

            $invitation->delete();
            $this->notices->archive($user, $id);

            return redirect()->route('teams.show', $team)->with('notices_notice', 'You joined '.$team->name.'.');
        });
    }

    public function preference(Request $request): RedirectResponse
    {
        $data = $request->validate(['notices_in_app' => ['required', 'in:0,1']]);
        $on = $data['notices_in_app'] === '1';

        User::whereKey($request->user()->getKey())->update(['notices_in_app' => $on]);

        return redirect()->route('notices')->with('notices_notice', $on
            ? 'Notifications are on. You will be told when someone answers your question or your work is accepted, verified or added to the library.'
            : 'Notifications about your work are off. Notices about a seminar you booked, paid for or host are still shown, because missing one can cost you money or a place. What you already have stays until you read it.');
    }
}

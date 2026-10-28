<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The signed-in person's own notices. Every action is scoped to the signed-in person; none takes a user id. */
class NoticesController extends Controller
{
    public function __construct(private readonly InAppNotices $notices) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $page = $this->notices->page($user);
        $rows = $page->getCollection()->map(fn ($n) => ['notice' => $n] + $this->notices->describe($n));

        return view('notices.index', ['page' => $page, 'rows' => $rows, 'unread' => $this->notices->unreadCount($user), 'wants' => $user->wantsInAppNotices()]);
    }

    /** Marks one read and opens what it is about (or goes back to the list when that is no longer available). */
    public function open(Request $request, int $id): RedirectResponse
    {
        $notice = $this->notices->markRead($request->user(), $id);
        abort_if($notice === null, 404);

        $url = $this->notices->describe($notice)['url'];

        return $url !== null ? redirect()->to($url) : redirect()->route('notices');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $count = $this->notices->markAllRead($request->user());

        return redirect()->route('notices')->with('notices_notice', $count > 0 ? 'All marked as read.' : 'Nothing was unread.');
    }

    public function preference(Request $request): RedirectResponse
    {
        $data = $request->validate(['notices_in_app' => ['required', 'in:0,1']]);
        $on = $data['notices_in_app'] === '1';

        User::whereKey($request->user()->getKey())->update(['notices_in_app' => $on]);

        return redirect()->route('notices')->with('notices_notice', $on
            ? 'Notifications are on. You will be told when someone answers your question or your work is accepted, verified or added to the library.'
            : 'Notifications are off. Nothing new will be recorded; what you already have stays until you read it.');
    }
}

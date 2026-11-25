<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * What the signed-in person wants to hear and how (R-64 phase 8), and the one-click way out of the daily summary from the email itself (a signed link,
 * so it works without signing in and cannot be used by anyone who was not sent it).
 */
class NotificationPreferencesController extends Controller
{
    public function __construct(private readonly NotificationPreferences $preferences) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        return view('notices.preferences', [
            'categories' => NotificationPreferences::CATEGORIES, 'chosen' => $this->preferences->for($user), 'modeWords' => NotificationPreferences::MODE_WORDS,
            'knowledgeInApp' => $user->wantsInAppNotices(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'prefs' => ['nullable', 'array'],
            'prefs.*' => ['array'],
            'prefs.*.popup' => ['nullable', 'in:0,1'],
            'prefs.*.email' => ['nullable', 'string', 'max:12'],
            'knowledge_in_app' => ['nullable', 'in:0,1'],
        ]);

        $this->preferences->save($request->user(), (array) ($data['prefs'] ?? []) + ['knowledge_in_app' => $data['knowledge_in_app'] ?? '0']);

        return redirect()->route('notices.preferences')->with('notices_notice', 'Your notification settings are saved.');
    }

    /** From the summary email: shows what the link does and asks once. */
    public function unsubscribe(Request $request, int $user): View
    {
        abort_if(User::whereKey($user)->doesntExist(), 404);

        return view('notices.unsubscribe', ['user' => $user, 'done' => false]);
    }

    /** Turns every summary off. Also the target of the List-Unsubscribe header's one-click POST. */
    public function confirmUnsubscribe(Request $request, int $user): View
    {
        $person = User::find($user);
        abort_if($person === null, 404);

        $this->preferences->turnSummariesOff($person);

        return view('notices.unsubscribe', ['user' => $user, 'done' => true]);
    }
}

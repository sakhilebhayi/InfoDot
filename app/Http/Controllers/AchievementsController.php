<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AchievementsController extends Controller
{
    /**
     * The signed-in person's own switch for showing their achievements and public contributions to other people. It
     * takes no user id: it can only ever change the row of whoever is signed in.
     */
    public function updateVisibility(Request $request): RedirectResponse
    {
        $data = $request->validate(['achievements_public' => ['required', 'in:0,1']]);
        $on = $data['achievements_public'] === '1';

        // A query-builder update, not ->save(): this is one flag and should not re-index the person for search.
        User::whereKey($request->user()->getKey())->update(['achievements_public' => $on]);

        return redirect()->to(route('profile.show', ['id' => $request->user()->getKey()]).'#profile-achievements')->with('achievements_notice',
            $on ? 'Other people can now see your achievements and public contributions.' : 'Only you can see your achievements now.');
    }

    /**
     * The signed-in person's own switch for specialist badges. On unless they hide them; hidden means nobody sees them, on
     * the profile or beside an answer. Like the switch above it takes no user id.
     */
    public function updateBadges(Request $request): RedirectResponse
    {
        $data = $request->validate(['badges_visible' => ['required', 'in:0,1']]);
        $on = $data['badges_visible'] === '1';

        User::whereKey($request->user()->getKey())->update(['badges_visible' => $on]);

        return redirect()->to(route('profile.show', ['id' => $request->user()->getKey()]).'#profile-badges')->with('badges_notice',
            $on ? 'Your specialist badges are visible again.' : 'Your specialist badges are hidden. Nobody can see them now.');
    }
}

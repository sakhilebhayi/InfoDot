<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TopicSuggestionsController extends Controller
{
    /**
     * The signed-in person's own switch for "questions you might be able to answer" on their points page. It takes no
     * user id: it can only ever change the row of whoever is signed in. Off is the default and the safe state.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['topic_suggestions' => ['required', 'in:0,1']]);
        $on = $data['topic_suggestions'] === '1';

        // A query-builder update, not ->save(): this is one flag and should not re-index the person for search.
        User::whereKey($request->user()->getKey())->update(['topic_suggestions' => $on]);

        return redirect()->to(route('points').'#topic-suggestions')->with('topic_suggestions_notice',
            $on ? 'Suggestions are on. They appear below, for you only.' : 'Suggestions are off. Nothing is shown or worked out.');
    }
}

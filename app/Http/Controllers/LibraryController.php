<?php

namespace App\Http\Controllers;

use App\Models\LibraryEntry;
use App\Models\Topic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The Verified library: answers and solutions a moderator has adopted. Everything is read from the source at the moment
 * of the request (nothing is copied), and an entry whose source is hidden, deleted or no longer public is left out for
 * every viewer alike. No author is named here: the source page shows what it shows.
 */
class LibraryController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $slug = $request->query('topic');
        $slug = is_string($slug) && $slug !== '' ? $slug : null;
        $topic = $slug !== null ? Topic::where('slug', $slug)->firstOrFail() : null;

        $entries = LibraryEntry::query()->shown()
            ->when($topic, fn ($q) => $q->inTopic($topic->id))
            ->with(['answer.question.topics', 'solution.topics'])
            ->orderByDesc('library_entries.adopted_at')->orderByDesc('library_entries.id')
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('library.index', [
            'entries' => $entries,
            'topic' => $topic,
            'topicChoices' => Topic::active()->ordered()->get(),
        ]);
    }
}

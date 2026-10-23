<?php

namespace App\Http\Controllers;

use App\Models\LibraryEntry;
use App\Services\Library\LibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A moderator adds an answer or a solution to the library without a Dot.Brain recommendation, by its reference
 * (infodot:answer:12 or infodot:solution:7). The same rules, audit row and ledger event as adopting a recommendation:
 * LibraryService decides, this only asks. Moderators only (route middleware, and the service checks again).
 */
class LibraryEntryController extends Controller
{
    public function store(Request $request, LibraryService $library): RedirectResponse
    {
        $data = $request->validate(['ref' => ['required', 'string', 'max:60']], ['ref.required' => 'Enter the reference of an answer or a solution, such as infodot:solution:7.']);

        $parsed = LibraryEntry::parseRef($data['ref']);
        $item = $parsed ? $library->find(LibraryEntry::refFor($parsed[0], $parsed[1])) : null;

        if ($item === null) {
            throw ValidationException::withMessages(['ref' => 'That is not the reference of an answer or a solution that exists. Use infodot:answer:ID or infodot:solution:ID.']);
        }

        try {
            ['added' => $added] = $library->adopt($item, $request->user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['ref' => collect($e->errors())->flatten()->first()]);
        }

        return redirect()->route('moderation.library')->with('moderation-status', $added
            ? 'Added to the library. Its author\'s reward can now be paid.'
            : 'It was already in the library: nothing changed.');
    }
}

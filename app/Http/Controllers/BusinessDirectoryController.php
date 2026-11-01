<?php

namespace App\Http\Controllers;

use App\Models\BusinessCategory;
use App\Models\TeamProfile;
use App\Services\Directory\BusinessDirectory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The business directory (R-33 §2–3). Open to visitors: they see businesses that chose
 * "anyone"; signed-in InfoDot users also see those that chose "InfoDot users". Private
 * businesses never appear.
 */
class BusinessDirectoryController extends Controller
{
    public function index(Request $request, BusinessDirectory $directory): View
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:80'],
            'size' => ['nullable', 'string', 'max:40'],
            'type' => ['nullable', Rule::in(array_keys(TeamProfile::BUSINESS_TYPES))],
            'verified' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:relevance,newest,name'],
            'view' => ['nullable', 'in:grid,list'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'all' => ['nullable', 'boolean'],
        ]);
        $f['verified'] = (bool) ($f['verified'] ?? false);

        // Results show once someone searches, filters, sorts, pages or asks for everything; before that, browsing.
        $searching = collect($f)->except(['view', 'verified'])->filter()->isNotEmpty() || $f['verified'];
        $viewer = $request->user();

        return view('businesses.index', [
            'f' => $f,
            'searching' => $searching,
            'view' => $f['view'] ?? 'grid',
            'search' => $directory->search($f, $viewer),
            'browse' => $searching ? null : $directory->browse($viewer),
            'categories' => BusinessCategory::where('active', true)->orderBy('sort')->get(),
            'types' => TeamProfile::BUSINESS_TYPES,
            'sizes' => $directory->sizes($viewer),
            'guest' => $viewer === null,
        ]);
    }
}

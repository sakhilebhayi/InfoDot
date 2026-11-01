<?php

namespace App\Http\Controllers;

use App\Models\BusinessCategory;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Services\Directory\BusinessDirectory;
use App\Services\Directory\FeaturedPlacement;
use App\Services\Insights\BusinessInsights;
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
    public function index(Request $request, BusinessDirectory $directory, BusinessInsights $insights, FeaturedPlacement $featured): View
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
        $search = $directory->search($f, $viewer);

        // Each business on this page of results appeared in a search (R-33 §9), counted for it.
        if ($searching && $search['results']->isNotEmpty()) {
            $shown = collect($search['results']->items())->filter(fn (Team $t) => $insights->counts($t, $viewer, $request->userAgent()));
            $insights->searched($shown, $search['words'], $search['service_hits']);
        }

        return view('businesses.index', [
            'f' => $f,
            'searching' => $searching,
            'view' => $f['view'] ?? 'grid',
            'search' => $search,
            'browse' => $searching ? null : $directory->browse($viewer),
            'featured' => $searching ? collect() : $featured->showing($viewer),
            'places' => $searching ? null : $directory->places($viewer),
            'categories' => BusinessCategory::where('active', true)->orderBy('sort')->get(),
            'types' => TeamProfile::BUSINESS_TYPES,
            'sizes' => $directory->sizes($viewer),
            'guest' => $viewer === null,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\BusinessCollection;
use App\Models\BusinessCollectionItem;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A person's private collections of businesses (R-33 §8). Only the person sees them; a business
 * that is no longer visible to them shows as unavailable, never by name.
 */
class BusinessCollectionController extends Controller
{
    private const MAX_COLLECTIONS = 20;

    private const MAX_ITEMS = 200;

    public function index(Request $request): View
    {
        $collections = BusinessCollection::where('user_id', $request->user()->id)->withCount('items')->orderBy('name')->get();

        return view('collections.index', ['collections' => $collections]);
    }

    public function show(Request $request, BusinessCollection $collection): View
    {
        $this->own($request, $collection);
        $items = $collection->items()->with(['team.profile', 'team.categories', 'team.services', 'team.standing', 'team.logo'])->get();

        return view('collections.show', ['collection' => $collection, 'items' => $items, 'viewer' => $request->user()]);
    }

    /** Save a business to a collection, making the collection on the way if it is new. */
    public function save(Request $request, Team $team): RedirectResponse
    {
        abort_unless($team->isVisibleTo($request->user()), 404);
        $user = $request->user();

        $data = $request->validate([
            'collection_id' => ['nullable', 'integer'],
            'new_name' => ['nullable', 'string', 'min:2', 'max:80', 'required_without:collection_id'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        if (filled($data['collection_id'] ?? null)) {
            $collection = BusinessCollection::where('user_id', $user->id)->findOrFail($data['collection_id']);
        } else {
            abort_if(BusinessCollection::where('user_id', $user->id)->count() >= self::MAX_COLLECTIONS, 422, 'You can keep up to '.self::MAX_COLLECTIONS.' collections.');
            $name = trim($data['new_name']);
            $collection = BusinessCollection::where('user_id', $user->id)->where('name', $name)->first();
            if ($collection === null) {
                $collection = new BusinessCollection(['name' => $name]);
                $collection->user_id = $user->id;
                $collection->save();
            }
        }

        abort_if($collection->items()->count() >= self::MAX_ITEMS, 422, 'A collection holds up to '.self::MAX_ITEMS.' businesses.');
        $collection->items()->updateOrCreate(['team_id' => $team->id], ['note' => $data['note'] ?? null]);

        return back()->with('status', $team->name.' is in “'.$collection->name.'”.');
    }

    public function remove(Request $request, BusinessCollection $collection, BusinessCollectionItem $item): RedirectResponse
    {
        $this->own($request, $collection);
        abort_unless($item->business_collection_id === $collection->id, 404);
        $item->delete();

        return back()->with('status', 'Taken out of “'.$collection->name.'”.');
    }

    public function update(Request $request, BusinessCollection $collection): RedirectResponse
    {
        $this->own($request, $collection);
        $collection->fill($request->validate(['name' => ['required', 'string', 'min:2', 'max:80']]))->save();

        return back()->with('status', 'Renamed.');
    }

    public function destroy(Request $request, BusinessCollection $collection): RedirectResponse
    {
        $this->own($request, $collection);
        $collection->delete();

        return redirect()->route('collections')->with('status', 'Collection deleted. The businesses in it are not affected.');
    }

    private function own(Request $request, BusinessCollection $collection): void
    {
        abort_unless($collection->user_id === $request->user()->id, 404);
    }
}

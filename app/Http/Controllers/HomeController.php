<?php

namespace App\Http\Controllers;

use App\Models\Questions;
use App\Models\Solutions;
use App\Support\GettingStarted;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function index(): View
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        return view('home', [
            'unsolved' => Questions::query()->where('status', '!=', 1)->withCount('comments')->with('user')->latest()->limit(5)->get(),
            'unsolvedTotal' => Questions::query()->where('status', '!=', 1)->count(),
            'solutions' => Solutions::query()->with('user')->latest()->limit(5)->get(),
            'team' => $team,
            'people' => $team ? $team->allUsers()->take(8)->values() : collect([$user]),
            'memberCount' => $team ? $team->allUsers()->count() : 1,
            'role' => $team ? ($user->ownsTeam($team) ? 'Owner' : $user->teamRole($team)?->name) : null,
            'gettingStarted' => GettingStarted::stepsToShow($user),
        ]);
    }

    /** Puts the "Getting started" block away for good. The steps themselves are never stored, only this choice. */
    public function dismissGettingStarted(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->getting_started_dismissed_at === null) {
            $user->forceFill(['getting_started_dismissed_at' => now()])->save();
        }

        return redirect()->route('dashboard')->with('getting-started-status', 'Getting started is put away. Everything it listed is still in the tabs above.');
    }
}

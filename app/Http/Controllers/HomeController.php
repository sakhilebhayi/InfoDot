<?php

namespace App\Http\Controllers;

use App\Models\Questions;
use App\Models\Solutions;
use Illuminate\Contracts\View\View;

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
            'memberCount' => $team ? $team->allUsers()->count() : 1,
            'role' => $team ? ($user->ownsTeam($team) ? 'Owner' : $user->teamRole($team)?->name) : null,
        ]);
    }
}

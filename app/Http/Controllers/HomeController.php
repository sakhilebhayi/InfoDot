<?php

namespace App\Http\Controllers;

use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\User;
use App\Services\Platforms\CompanyCatalogue;
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
            'company' => $this->companySummary($user, $team instanceof Team ? $team : null),
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

    /**
     * What the company in view has, in one place: the platforms it holds (and the person may open), its people, and what is still missing from its
     * page. Null for a personal workspace, where the page asks the person to set a company up instead.
     *
     * @return array{platforms: list<array{key: string, name: string}>, switched_on: int, people: int, missing: list<string>, can_manage: bool}|null
     */
    private function companySummary(User $user, ?Team $team): ?array
    {
        if ($team === null || $team->personal_team) {
            return null;
        }

        $profile = $team->profile()->first();
        $missing = array_keys(array_filter([
            'a description' => blank($profile?->description),
            'an industry' => blank($team->industry) || $team->industry === 'general',
            'a way to contact it' => blank($profile?->website) && blank($profile?->contact_email) && blank($profile?->contact_phone),
            'a logo' => ! $team->logo()->exists(),
        ]));

        $rows = CompanyCatalogue::shared()->rows($user, $team);

        return [
            'platforms' => $rows->filter(fn (array $row) => $row['held'] === true && $row['open'] === true)->map(fn (array $row, string $key) => ['key' => $key, 'name' => (string) $row['name']])->values()->all(),
            // Platforms the company chose, beyond the free core every company has.
            'switched_on' => $rows->filter(fn (array $row) => $row['held'] === true && $row['state'] !== 'included')->count(),
            'people' => $team->allUsers()->count(),
            'missing' => $missing,
            'can_manage' => $user->can('managePlatforms', $team),
        ];
    }
}

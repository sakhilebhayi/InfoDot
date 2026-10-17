<?php

namespace App\Http\Controllers;

use App\Support\NavSection;
use Illuminate\Contracts\View\View;

/**
 * The people of the company the person is acting for: who they are and what role
 * they hold. Only the current company's members, never anyone else's.
 */
class PeopleController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $team = $user->currentTeam;

        $people = $team
            ? $team->allUsers()->map(function ($member) use ($team) {
                $member->company_role = $member->id === $team->user_id ? 'Owner' : ($member->teamRole($team)?->name ?? 'Member');

                return $member;
            })->sortBy([['company_role', 'asc'], ['name', 'asc']])->values()
            : collect([$user]);

        return view('people', [
            'team' => $team,
            'people' => $people,
            'canManage' => NavSection::administersTeam($user),
            'invitations' => $team && NavSection::administersTeam($user) ? $team->teamInvitations()->get() : collect(),
        ]);
    }
}

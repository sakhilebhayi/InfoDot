<?php

namespace App\Http\Controllers;

use App\Actions\Jetstream\JoinByInviteLink;
use App\Models\TeamInviteLink;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Joining a company from a shareable link (R-62 phase 4). The page shows only the company's name and what the link allows; it never
 * reveals anything else about the company. A guest is sent to sign in or register and brought back here.
 */
class JoinLinkController extends Controller
{
    public function show(Request $request, string $token)
    {
        $link = TeamInviteLink::findByToken($token);
        $problem = $link === null ? 'unknown' : $link->problem();

        if ($problem === null && $link !== null && $request->user() === null) {
            // Come back to this page after signing in or registering.
            $request->session()->put('url.intended', route('join.show', $token));
        }

        return response()->view('teams.join', [
            'token' => $token,
            'company' => $problem === null ? $link?->team?->name : null,
            'problem' => $problem,
        ], $problem === null ? 200 : 404);
    }

    public function join(Request $request, string $token, JoinByInviteLink $action)
    {
        $link = TeamInviteLink::findByToken($token);
        abort_if($link === null, 404);

        $user = $request->user();

        if ($link->problem() !== null && ! ($link->team?->hasUser($user) || $user->ownsTeam($link->team))) {
            return redirect()->route('join.show', $token);
        }

        try {
            $team = $action->join($user, $link);
        } catch (ValidationException $e) {
            return redirect()->route('join.show', $token)->withErrors($e->errors());
        }

        $user->switchTeam($team);

        return redirect(config('fortify.home'))->banner(__('You joined :company.', ['company' => $team->name]));
    }
}

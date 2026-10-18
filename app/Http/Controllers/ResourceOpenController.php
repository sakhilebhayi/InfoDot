<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Support\EcosystemHandoff;
use Illuminate\Http\Request;

/**
 * Opens a registered object: sends the person to the platform that owns it, signed in, at the
 * exact object. The PLATFORM decides whether they may see it (company membership, a document
 * collaborator, a live file share), so InfoDot never guesses and never reveals anything itself.
 *
 * A member of the owning company is also switched to that company, so the destination shows the
 * right workspace. Anyone else keeps their own workspace and meets the platform's own
 * request-access page if they have no right to the object.
 */
class ResourceOpenController extends Controller
{
    public function __invoke(Request $request, Resource $resource)
    {
        $user = $request->user();
        $team = $resource->team;

        if ($team !== null && $user->belongsToTeam($team) && $user->current_team_id !== $team->id) {
            $user->switchTeam($team);
        }

        $url = EcosystemHandoff::launchUrl($user, $resource->platform, $resource->path);

        if ($url === null) {
            return response()->view('resources.unavailable', ['platform' => config('ecosystem.platforms.'.$resource->platform.'.name', $resource->platform)], 503);
        }

        return redirect()->away($url);
    }
}

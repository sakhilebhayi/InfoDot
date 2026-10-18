<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Support\EcosystemHandoff;
use Illuminate\Http\Request;

/**
 * Opens a registered object. The person must belong to the company that owns it; opening
 * it also makes that company their current one, so the destination shows the right
 * workspace. A person outside the company gets a request-access page that says nothing
 * about the object. The destination re-authorises on arrival: this check is the first
 * gate, not the only one.
 */
class ResourceOpenController extends Controller
{
    public function __invoke(Request $request, Resource $resource)
    {
        $user = $request->user();
        $team = $resource->team;

        if ($team === null || ! $user->belongsToTeam($team)) {
            return response()->view('resources.request-access', [], 403);
        }

        if ($user->current_team_id !== $team->id) {
            $user->switchTeam($team);
        }

        $url = EcosystemHandoff::launchUrl($user, $resource->platform, $resource->path);

        if ($url === null) {
            return response()->view('resources.unavailable', ['platform' => config('ecosystem.platforms.'.$resource->platform.'.name', $resource->platform)], 503);
        }

        return redirect()->away($url);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\Platforms\EntitlementRefused;
use App\Services\Platforms\Entitlements;
use App\Services\Platforms\PlatformAccess;
use App\Services\Platforms\PlatformRefusal;
use App\Support\EcosystemHandoff;
use App\Support\Input;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The page that explains why a platform did not open, and the one action it offers (R-50). Links that cannot open a
 * platform lead here instead of to nothing; a person who may open it after all is sent straight on to the launch.
 * Switching a platform on always acts on the person's current company: a company named in the request is only
 * compared with it, so a stale tab or a forged form cannot switch something on for a different company.
 */
class PlatformAccessController extends Controller
{
    public function show(Request $request, string $platform, PlatformAccess $access, PlatformRefusal $refusal): Response|RedirectResponse
    {
        abort_unless($access->governs($platform), 404);

        $redirect = Input::text($request->query('redirect')) ?: null;
        $decision = $access->decide($request->user(), $platform);

        if ($decision->allowed) {
            return $this->onward($request, $platform, $redirect);
        }

        return $refusal->response($request->user(), $platform, $decision, $redirect);
    }

    public function activate(Request $request, string $platform, PlatformAccess $access, Entitlements $entitlements): RedirectResponse
    {
        abort_unless($access->governs($platform), 404);

        $user = $request->user();
        $team = $user->currentTeam;
        $redirect = Input::text($request->input('redirect')) ?: null;

        // The form says which company it was drawn for. If the person has since switched company, nothing is switched on.
        abort_if(! $team instanceof Team || ! hash_equals((string) $team->uuid, Input::text($request->input('company'))), 409, 'You have switched company since this page was opened. Go back and try again.');
        abort_unless($user->can('managePlatforms', $team), 403);

        try {
            $entitlements->activate($team, $platform, $user);
        } catch (EntitlementRefused $e) {
            return redirect()->route('platform.access', ['platform' => $platform])->with('status', $e->getMessage());
        }

        return $this->onward($request, $platform, $redirect);
    }

    private function onward(Request $request, string $platform, ?string $redirect): RedirectResponse
    {
        return redirect()->away(EcosystemHandoff::launchUrl($request->user(), $platform, $redirect) ?? route('ecosystem.widget'));
    }
}

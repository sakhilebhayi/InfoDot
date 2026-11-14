<?php

namespace App\Http\Controllers\Admin\Platforms;

use App\Http\Controllers\Controller;
use App\Models\DotPlatform;
use App\Models\PlatformCatalogue;
use App\Models\PlatformCatalogueEvent;
use App\Services\Platforms\EntitlementOutbox;
use App\Support\EcosystemHandoff;
use App\Support\Platforms;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The staff view of the fleet (R-50 section 9), for platform administrators (route middleware): what InfoDot itself
 * has on record about each platform. Every cell is read from a row InfoDot wrote: the catalogue status an
 * administrator set, the registry entry, the single sign-on registration and its last sign-in, and the outbox of
 * entitlement changes with the platform's last answer. InfoDot does not probe the platforms, so there is no uptime
 * figure and nothing here says a platform "is up": the nearest fact is when it last answered a delivery or completed
 * a sign-in. This is the fleet, never a company: a business sees its own platforms on its Analytics page.
 */
class InfrastructureController extends Controller
{
    public function __invoke(): View
    {
        $registry = (array) config('ecosystem.platforms', []);
        $sso = DotPlatform::query()->get()->keyBy('slug');
        $people = DB::table('sso_platform_sessions')->selectRaw('dot_platform_id, count(*) as n')->groupBy('dot_platform_id')->pluck('n', 'dot_platform_id');
        $changed = PlatformCatalogueEvent::query()->where('subject_type', 'platform')->selectRaw('subject_key, max(created_at) as at')->groupBy('subject_key')->pluck('at', 'subject_key');

        $outbox = DB::table('entitlement_outbox')->selectRaw(
            'platform_key,'
            .' sum(case when sent_at is null and failed_at is null then 1 else 0 end) as waiting,'
            .' sum(case when sent_at is null and failed_at is not null then 1 else 0 end) as parked,'
            .' sum(case when sent_at is not null and last_error is null then 1 else 0 end) as accepted,'
            .' sum(case when sent_at is not null and last_error is not null then 1 else 0 end) as unbuilt,'
            .' max(case when sent_at is not null and last_error is null then sent_at end) as last_accepted_at'
        )->groupBy('platform_key')->get()->keyBy('platform_key');

        // The newest undelivered row per platform says what the platform answered last time it was tried.
        $stuck = DB::table('entitlement_outbox')->whereNull('sent_at')->whereNotNull('last_error')->orderBy('id')->get(['platform_key', 'last_error'])->pluck('last_error', 'platform_key');

        $rows = PlatformCatalogue::query()->orderBy('sort')->orderBy('name')->get()->map(function (PlatformCatalogue $entry) use ($registry, $sso, $people, $changed, $outbox, $stuck) {
            $key = (string) $entry->key;
            $registered = $registry[$key] ?? null;
            $client = $sso->get($key);
            $queue = $outbox->get($key);
            $ownSignIn = $registered !== null && EcosystemHandoff::hasNoReceiver($key);
            $at = fn ($value) => $value === null ? null : Carbon::parse($value);

            return [
                'entry' => $entry,
                'changed_at' => $at($changed->get($key)),
                'registry' => match (true) {
                    $registered === null => 'missing',
                    ($registered['active'] ?? true) === false => 'off',
                    EcosystemHandoff::endpointFor($registered) === null => 'untrusted',
                    default => 'live',
                },
                'internal' => $registered !== null && Platforms::isInternal($key, $registered),
                'own_sign_in' => $ownSignIn,
                // Only whether a secret is configured: its value is never read into a page.
                'secret' => (string) config('ecosystem.service_secrets.'.$key, '') !== '',
                'sso' => $client === null ? 'missing' : (string) $client->status,
                'last_sign_in_at' => $client?->last_authenticated_at,
                'people' => $client === null ? 0 : (int) ($people[$client->id] ?? 0),
                'waiting' => (int) ($queue->waiting ?? 0),
                'parked' => (int) ($queue->parked ?? 0),
                'accepted' => (int) ($queue->accepted ?? 0),
                'unbuilt' => (int) ($queue->unbuilt ?? 0),
                'last_accepted_at' => $at($queue->last_accepted_at ?? null),
                'last_answer' => $stuck->get($key),
            ];
        });

        return view('admin.platforms.infrastructure', [
            'rows' => $rows,
            'notImplemented' => EntitlementOutbox::NOT_IMPLEMENTED,
            // Registered in the registry but with no catalogue row: nothing a business can be shown, and worth knowing about.
            'uncatalogued' => collect($registry)->keys()->map(fn ($key) => (string) $key)
                ->reject(fn (string $key) => $key === 'infodot' || $rows->contains(fn (array $row) => $row['entry']->key === $key))->values(),
        ]);
    }
}

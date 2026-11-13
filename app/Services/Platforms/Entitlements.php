<?php

namespace App\Services\Platforms;

use App\Models\PlatformEntitlement;
use App\Models\PlatformEntitlementEvent;
use App\Models\PlatformUserAccess;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The only writer of a company's platform entitlements (R-50 section 6). Every change runs in one transaction: the
 * company row is locked (so two people pressing Activate at once make one entitlement, not an error), the status
 * changes, an event is appended to the history, and a message for the platform is put in the outbox. Nothing here
 * deletes an entitlement or its history: a platform that is switched off is marked cancelled and can come back.
 *
 * In this step a company can only switch on what costs nothing: a platform with an approved free plan, or one with
 * no approved price yet ("free while pricing is being finalised"). The paid statuses exist and are honoured by
 * PlatformAccess, but only a signed confirmation from Dot.Finance may ever create them, and that door is not built
 * here. Company actions are authorised through TeamPolicy; complimentary access, suspension and restoration belong
 * to platform administrators.
 */
class Entitlements
{
    public function __construct(private readonly PlatformAccess $access, private readonly EntitlementOutbox $outbox) {}

    /** Switch a free or unpriced platform on for a company. Owner or administrator. */
    public function activate(Team $team, string $key, User $actor): PlatformEntitlement
    {
        Gate::forUser($actor)->authorize('managePlatforms', $team);
        $this->mustBeActivatable($team, $key);

        $offer = $this->access->offer($key);

        if ($offer === 'paid') {
            throw new EntitlementRefused('This platform has a price, and paid plans cannot be started yet. Nothing was changed and nothing was charged.');
        }

        return $this->change($team, $key, function (?PlatformEntitlement $current) use ($team, $key, $actor, $offer) {
            if ($current !== null && $current->status === PlatformEntitlement::SUSPENDED) {
                throw new EntitlementRefused('This platform is suspended for the company. Only a platform administrator can restore it.');
            }

            if ($current !== null && $current->grants()) {
                return null; // already on: nothing to record
            }

            if ($current !== null && ! in_array($current->status, [PlatformEntitlement::CANCELLED, PlatformEntitlement::EXPIRED], true)) {
                throw new EntitlementRefused('This platform is waiting on a payment, so it cannot be switched on here.');
            }

            return [
                $this->write($current, $team, $key, [
                    'status' => PlatformEntitlement::ACTIVE, 'source' => $offer, 'started_at' => now(), 'cancelled_at' => null, 'ended_at' => null,
                    'trial_ends_at' => null, 'current_period_ends_at' => null, 'grace_until' => null, 'suspended_at' => null, 'granted_by' => $actor->id,
                ]),
                'activated', $actor, ['source' => $offer],
            ];
        });
    }

    /** Switch a platform off for a company. The entitlement and its history stay; the platform keeps the company's data. Owner only. */
    public function deactivate(Team $team, string $key, User $actor): PlatformEntitlement
    {
        Gate::forUser($actor)->authorize('cancelPlatforms', $team);

        return $this->change($team, $key, function (?PlatformEntitlement $current) use ($team, $key, $actor) {
            $current = $this->mustExist($current);

            if ($current->status === PlatformEntitlement::CANCELLED) {
                return null;
            }

            if (! in_array($current->source, PlatformEntitlement::SELF_SERVICE_SOURCES, true) || $current->status !== PlatformEntitlement::ACTIVE) {
                throw new EntitlementRefused('Only a platform the company switched on for free can be switched off here.');
            }

            return [$this->write($current, $team, $key, ['status' => PlatformEntitlement::CANCELLED, 'cancelled_at' => now(), 'ended_at' => now()]), 'deactivated', $actor, []];
        });
    }

    /** Give a company a platform at no charge, optionally until a date. Platform administrators only. */
    public function grantComplimentary(Team $team, string $key, User $administrator, ?CarbonInterface $until = null, ?string $note = null): PlatformEntitlement
    {
        $this->mustBeAdministrator($administrator);
        $this->mustBeActivatable($team, $key);

        return $this->change($team, $key, fn (?PlatformEntitlement $current) => [
            $this->write($current, $team, $key, [
                'status' => PlatformEntitlement::ACTIVE, 'source' => 'complimentary', 'started_at' => now(), 'ended_at' => $until, 'cancelled_at' => null,
                'suspended_at' => null, 'trial_ends_at' => null, 'grace_until' => null, 'granted_by' => $administrator->id, 'note' => $note === null ? null : Str::limit($note, 250, ''),
            ]),
            'complimentary_granted', $administrator, array_filter(['until' => $until?->toIso8601String(), 'note' => $note]),
        ]);
    }

    /** Stop a company's use of a platform until it is restored. Platform administrators only. */
    public function suspend(Team $team, string $key, User $administrator, ?string $note = null): PlatformEntitlement
    {
        $this->mustBeAdministrator($administrator);

        return $this->change($team, $key, function (?PlatformEntitlement $current) use ($team, $key, $administrator, $note) {
            $current = $this->mustExist($current);

            if ($current->status === PlatformEntitlement::SUSPENDED) {
                return null;
            }

            return [$this->write($current, $team, $key, ['status' => PlatformEntitlement::SUSPENDED, 'suspended_at' => now()]), 'suspended', $administrator, array_filter(['note' => $note])];
        });
    }

    /** Lift a suspension: the entitlement returns to the status it had when it was suspended. Platform administrators only. */
    public function restore(Team $team, string $key, User $administrator, ?string $note = null): PlatformEntitlement
    {
        $this->mustBeAdministrator($administrator);

        return $this->change($team, $key, function (?PlatformEntitlement $current) use ($team, $key, $administrator, $note) {
            $current = $this->mustExist($current);

            if ($current->status !== PlatformEntitlement::SUSPENDED) {
                return null;
            }

            $before = PlatformEntitlementEvent::where('team_id', $team->id)->where('platform_key', $key)->where('type', 'suspended')->latest('id')->value('from_status');

            return [
                $this->write($current, $team, $key, ['status' => in_array($before, PlatformEntitlement::STATUSES, true) ? $before : PlatformEntitlement::ACTIVE, 'suspended_at' => null]),
                'restored', $administrator, array_filter(['note' => $note]),
            ];
        });
    }

    /** Everyone in the company, or only the people chosen (owners and administrators always). Owner or administrator. */
    public function setAccessMode(Team $team, string $key, string $mode, User $actor): PlatformEntitlement
    {
        Gate::forUser($actor)->authorize('managePlatforms', $team);

        if (! in_array($mode, [PlatformEntitlement::EVERYONE, PlatformEntitlement::SELECTED], true)) {
            throw new EntitlementRefused('Access is either for everyone in the company or for selected people.');
        }

        return $this->change($team, $key, function (?PlatformEntitlement $current) use ($team, $key, $mode, $actor) {
            $current = $this->mustExist($current);

            if ($current->access_mode === $mode) {
                return null;
            }

            $from = $current->access_mode;

            return [$this->write($current, $team, $key, ['access_mode' => $mode]), 'access_mode_changed', $actor, ['from' => $from, 'to' => $mode]];
        });
    }

    /** Choose a member of the company to use a platform that is set to selected people. Owner or administrator. */
    public function grantUser(Team $team, string $key, User $person, User $actor, ?string $role = null): PlatformEntitlement
    {
        Gate::forUser($actor)->authorize('managePlatforms', $team);

        if (! $person->belongsToTeam($team)) {
            throw new EntitlementRefused('That person is not in this company.');
        }

        return $this->change($team, $key, function (?PlatformEntitlement $current) use ($team, $key, $person, $actor, $role) {
            $current = $this->mustExist($current);
            $row = PlatformUserAccess::firstOrNew(['team_id' => $team->id, 'platform_key' => $key, 'user_id' => $person->id]);

            if ($row->exists && $row->platform_role === $role) {
                return null;
            }

            $row->fill(['platform_role' => $role, 'granted_by' => $actor->id])->save();

            return [$current, 'user_granted', $actor, ['user_uuid' => $person->uuid, 'platform_role' => $role]];
        });
    }

    /** Take a person off a platform's selected people. Owner or administrator. */
    public function revokeUser(Team $team, string $key, User $person, User $actor): PlatformEntitlement
    {
        Gate::forUser($actor)->authorize('managePlatforms', $team);

        return $this->change($team, $key, function (?PlatformEntitlement $current) use ($team, $key, $person, $actor) {
            $current = $this->mustExist($current);

            if (PlatformUserAccess::where('team_id', $team->id)->where('platform_key', $key)->where('user_id', $person->id)->delete() === 0) {
                return null;
            }

            return [$current, 'user_revoked', $actor, ['user_uuid' => $person->uuid]];
        });
    }

    /**
     * One change, whole or not at all. `$work` gets the locked entitlement (or null) and returns null when there is
     * nothing to do, or [the entitlement, the event type, who did it, the details].
     *
     * @param  Closure(?PlatformEntitlement): (array{0: PlatformEntitlement, 1: string, 2: ?User, 3: array<string, mixed>}|null)  $work
     */
    private function change(Team $team, string $key, Closure $work): PlatformEntitlement
    {
        return DB::transaction(function () use ($team, $key, $work) {
            // The company row is the lock: it exists even before the first entitlement does.
            Team::whereKey($team->id)->lockForUpdate()->first();
            $current = PlatformEntitlement::where('team_id', $team->id)->where('platform_key', $key)->lockForUpdate()->first();
            $from = $current?->status;

            $result = $work($current);

            if ($result === null) {
                return $this->mustExist($current);
            }

            [$entitlement, $type, $actor, $details] = $result;

            $event = PlatformEntitlementEvent::create([
                'team_id' => $team->id, 'platform_key' => $key, 'actor_id' => $actor?->id, 'type' => $type,
                'from_status' => $from, 'to_status' => $entitlement->status, 'details' => $details === [] ? null : $details, 'created_at' => now(),
            ]);

            $this->outbox->enqueue($team, $entitlement, $type, (int) $event->id, $details);

            return $entitlement;
        });
    }

    /** @param  array<string, mixed>  $values */
    private function write(?PlatformEntitlement $current, Team $team, string $key, array $values): PlatformEntitlement
    {
        $entitlement = $current ?? new PlatformEntitlement(['team_id' => $team->id, 'platform_key' => $key, 'access_mode' => PlatformEntitlement::EVERYONE]);
        $entitlement->fill($values)->save();

        return $entitlement;
    }

    private function mustExist(?PlatformEntitlement $current): PlatformEntitlement
    {
        return $current ?? throw new EntitlementRefused('The company has not switched this platform on.');
    }

    private function mustBeAdministrator(User $user): void
    {
        if (! $user->isPlatformAdmin()) {
            throw new AuthorizationException('Only a platform administrator can do this.');
        }
    }

    /** A company (never a personal workspace) and a platform that is offered, not core, and enforced from here. */
    private function mustBeActivatable(Team $team, string $key): void
    {
        if ($team->personal_team) {
            throw new EntitlementRefused('A personal workspace has the free core only. Create a company to switch on more platforms.');
        }

        $entry = $this->access->governs($key) ? $this->access->entry($key) : null;

        if ($entry === null || ! $entry->discoverable || $entry->isNotLaunched()) {
            throw new EntitlementRefused('This platform is not available to switch on.');
        }

        if ($entry->is_core) {
            throw new EntitlementRefused('This platform is included for every company. There is nothing to switch on.');
        }

        if ($this->access->decideFor(null, $team, $key)->managedInPlatform()) {
            throw new EntitlementRefused('This platform has its own sign-in and is managed in the platform itself.');
        }
    }
}

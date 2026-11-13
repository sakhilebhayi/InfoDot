<?php

namespace App\Services\Platforms;

use App\Models\PlatformBundle;
use App\Models\PlatformEntitlement;
use App\Models\PlatformEntitlementEvent;
use App\Models\PlatformPaymentConfirmation;
use App\Models\PlatformPlan;
use App\Models\PlatformUserAccess;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LogicException;

/**
 * The only writer of a company's platform entitlements (R-50 section 6). Every change runs in one transaction: the
 * company row is locked (so two people pressing Activate at once make one entitlement, not an error), the status
 * changes, an event is appended to the history, and a message for the platform is put in the outbox. Nothing here
 * deletes an entitlement or its history: a platform that is switched off is marked cancelled and can come back.
 *
 * A company can itself switch on only what costs nothing: a platform with an approved free plan, or one with no
 * approved price yet ("free while pricing is being finalised"). A paid entitlement is created in one place only,
 * applyPaymentEvent(), from a confirmation that Dot.Finance signed and PaymentConfirmations stored. Company actions
 * are authorised through TeamPolicy; complimentary access, administrator trials, suspension and restoration belong
 * to platform administrators. What time ends (trials, dated grants, grace) is ended by runLifecycle().
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
     * Start a trial (R-50 section 6). A platform administrator may give one of any length up to the configured
     * ceiling, with or without a plan, and may extend a running one. A company's owner or administrator may start
     * one only on a sellable plan that offers trial days, and only once per platform. When the trial ends the
     * scheduler moves it to `payment_required` if a priced plan was chosen and to `expired` otherwise.
     */
    public function startTrial(Team $team, string $key, User $actor, ?PlatformPlan $plan = null, ?int $days = null, ?string $note = null): PlatformEntitlement
    {
        $administrator = $actor->isPlatformAdmin();

        if (! $administrator) {
            Gate::forUser($actor)->authorize('managePlatforms', $team);
            $days = null;
        }

        $this->mustBeActivatable($team, $key);

        if ($plan !== null && ($plan->platform_key !== $key || ! $plan->isSellable())) {
            throw new EntitlementRefused('That plan cannot be used for a trial of this platform.');
        }

        $days ??= $plan?->trial_days === null ? null : (int) $plan->trial_days;

        if ($days === null || $days < 1 || $days > (int) config('platform_subscriptions.max_trial_days')) {
            throw new EntitlementRefused($administrator ? 'A trial needs a length between 1 and '.config('platform_subscriptions.max_trial_days').' days.' : 'This platform has no trial to start.');
        }

        return $this->change($team, $key, function (?PlatformEntitlement $current) use ($team, $key, $actor, $administrator, $plan, $days, $note) {
            if ($current !== null && $current->status === PlatformEntitlement::SUSPENDED) {
                throw new EntitlementRefused('This platform is suspended for the company. Restore it first.');
            }

            if ($current !== null && $current->grants() && ! ($administrator && $current->status === PlatformEntitlement::TRIAL)) {
                throw new EntitlementRefused('The company already has this platform.');
            }

            if (! $administrator && PlatformEntitlementEvent::where('team_id', $team->id)->where('platform_key', $key)->where('type', 'trial_started')->exists()) {
                throw new EntitlementRefused('The company has already had its trial of this platform.');
            }

            $until = now()->addDays($days);

            return [
                $this->write($current, $team, $key, [
                    'status' => PlatformEntitlement::TRIAL, 'source' => 'trial', 'plan_id' => $plan?->id, 'started_at' => $current->started_at ?? now(),
                    'trial_ends_at' => $until, 'current_period_ends_at' => null, 'grace_until' => null, 'suspended_at' => null, 'cancelled_at' => null,
                    'ended_at' => null, 'granted_by' => $actor->id, 'note' => $note === null ? null : Str::limit($note, 250, ''),
                ]),
                'trial_started', $actor, array_filter(['until' => $until->toIso8601String(), 'days' => $days, 'plan' => $plan?->slug, 'note' => $note]),
            ];
        });
    }

    /**
     * The owner asks for a paid plan (R-50 section 6). Nothing is charged and nothing opens: a company that holds
     * nothing waits as `pending`, and one that already has the platform (a trial, a complimentary grant) keeps what
     * it has with the chosen plan noted. Only Dot.Finance's signed confirmation makes it paid.
     */
    public function subscribe(Team $team, string $key, PlatformPlan $plan, User $owner): PlatformEntitlement
    {
        Gate::forUser($owner)->authorize('cancelPlatforms', $team);
        $this->mustBeActivatable($team, $key);

        if ($plan->platform_key !== $key || ! $plan->isSellable() || $plan->isFree()) {
            throw new EntitlementRefused('That plan cannot be bought for this platform.');
        }

        return $this->change($team, $key, function (?PlatformEntitlement $current) use ($team, $key, $plan, $owner) {
            if ($current !== null && $current->status === PlatformEntitlement::SUSPENDED) {
                throw new EntitlementRefused('This platform is suspended for the company. Only a platform administrator can restore it.');
            }

            if ($current !== null && (int) $current->plan_id === (int) $plan->id && ($current->grants() || $current->status === PlatformEntitlement::PENDING)) {
                return null;
            }

            $values = $current !== null && $current->grants()
                ? ['plan_id' => $plan->id]
                : ['status' => PlatformEntitlement::PENDING, 'source' => 'paid', 'plan_id' => $plan->id, 'cancelled_at' => null, 'ended_at' => null, 'trial_ends_at' => null, 'grace_until' => null, 'granted_by' => $owner->id];

            return [$this->write($current, $team, $key, $values), 'subscription_requested', $owner, ['plan' => $plan->slug]];
        });
    }

    /**
     * Cancel a company's platform, whatever its source. A platform administrator or the company's owner. Time
     * already paid for is honoured to the end of the period; anything else ends now. Nothing is deleted.
     */
    public function cancel(Team $team, string $key, User $actor, ?string $note = null): PlatformEntitlement
    {
        if (! $actor->isPlatformAdmin()) {
            Gate::forUser($actor)->authorize('cancelPlatforms', $team);
        }

        return $this->change($team, $key, function (?PlatformEntitlement $current) use ($team, $key, $actor, $note) {
            $current = $this->mustExist($current);

            if ($current->status === PlatformEntitlement::CANCELLED) {
                return null;
            }

            if ($current->status === PlatformEntitlement::SUSPENDED && ! $actor->isPlatformAdmin()) {
                throw new EntitlementRefused('This platform is suspended for the company. Only a platform administrator can change it.');
            }

            $paidTimeLeft = $current->source === 'paid' && $current->current_period_ends_at !== null && $current->current_period_ends_at->isFuture()
                && in_array($current->status, [PlatformEntitlement::ACTIVE, PlatformEntitlement::PAYMENT_REQUIRED], true);

            return [
                $this->write($current, $team, $key, ['status' => PlatformEntitlement::CANCELLED, 'cancelled_at' => now(), 'ended_at' => $paidTimeLeft ? null : now(), 'suspended_at' => null, 'grace_until' => null]),
                'cancelled', $actor, array_filter(['note' => $note, 'access_until' => $paidTimeLeft ? $current->current_period_ends_at->toIso8601String() : null]),
            ];
        });
    }

    /** A platform administrator sets who in the company may use a platform, with the reason kept in the history. */
    public function administerAccessMode(Team $team, string $key, string $mode, User $administrator, ?string $note = null): PlatformEntitlement
    {
        $this->mustBeAdministrator($administrator);

        if (! in_array($mode, [PlatformEntitlement::EVERYONE, PlatformEntitlement::SELECTED], true)) {
            throw new EntitlementRefused('Access is either for everyone in the company or for selected people.');
        }

        return $this->change($team, $key, function (?PlatformEntitlement $current) use ($team, $key, $mode, $administrator, $note) {
            $current = $this->mustExist($current);

            if ($current->access_mode === $mode) {
                return null;
            }

            $from = $current->access_mode;

            return [$this->write($current, $team, $key, ['access_mode' => $mode]), 'access_mode_changed', $administrator, array_filter(['from' => $from, 'to' => $mode, 'note' => $note])];
        });
    }

    /**
     * Apply one payment event that Dot.Finance confirmed. The ONLY path by which an entitlement becomes `paid`: it
     * takes the stored confirmation (written by PaymentConfirmations after the signature was verified), never loose
     * values, and refuses one that was not stored. Returns false when the event changes nothing.
     *
     * A suspension outlasts a payment: the facts are recorded and the platform stays suspended until an
     * administrator restores it.
     */
    public function applyPaymentEvent(PlatformPaymentConfirmation $confirmation, Team $team, ?PlatformPlan $plan = null, ?PlatformBundle $bundle = null, ?CarbonInterface $graceUntil = null): bool
    {
        if (! $confirmation->exists || (int) $confirmation->team_id !== (int) $team->id || $confirmation->outcome !== PlatformPaymentConfirmation::APPLIED) {
            throw new LogicException('A payment event is applied only from its stored, verified confirmation.');
        }

        $key = (string) $confirmation->platform_key;
        $changed = false;

        DB::transaction(function () use ($confirmation, $team, $key, $plan, $bundle, $graceUntil, &$changed) {
            Team::whereKey($team->id)->lockForUpdate()->first();
            $current = PlatformEntitlement::where('team_id', $team->id)->where('platform_key', $key)->lockForUpdate()->first();
            $suspended = $current?->status === PlatformEntitlement::SUSPENDED;
            $keep = fn (string $status) => $suspended ? PlatformEntitlement::SUSPENDED : $status;
            $details = ['confirmation' => $confirmation->event_id];

            if ($confirmation->confirmsPayment()) {
                if ($plan === null && $bundle === null) {
                    throw new EntitlementRefused('A payment names the plan or the bundle that was paid for.');
                }

                $values = [
                    'status' => $keep(PlatformEntitlement::ACTIVE), 'source' => 'paid', 'plan_id' => $plan?->id, 'bundle_id' => $bundle?->id,
                    'reference' => $confirmation->reference, 'started_at' => $current->started_at ?? now(), 'current_period_ends_at' => $confirmation->period_ends_at,
                    'trial_ends_at' => null, 'grace_until' => null, 'cancelled_at' => null, 'ended_at' => null,
                ];
                $type = $confirmation->event === PlatformPaymentConfirmation::RENEWED ? 'renewal_confirmed' : 'payment_confirmed';
                $details += array_filter(['plan' => $plan?->slug, 'bundle' => $bundle?->slug, 'period_ends_at' => $confirmation->period_ends_at?->toIso8601String()]);

                if ($current !== null && $current->plan_id !== null && $plan !== null && (int) $current->plan_id !== (int) $plan->id) {
                    $details['plan_changed_from'] = PlatformPlan::whereKey($current->plan_id)->value('slug');
                }
            } else {
                // The three events that take something away mean nothing unless the company holds a paid entitlement.
                if ($current === null || $current->source !== 'paid') {
                    throw new EntitlementRefused('There is no paid entitlement for this event to act on.');
                }

                if ($confirmation->event === PlatformPaymentConfirmation::PAYMENT_FAILED) {
                    if (! in_array($current->status, [PlatformEntitlement::ACTIVE, PlatformEntitlement::SUSPENDED], true) || $current->grace_until !== null) {
                        return; // already waiting on a payment, or already over: a second failure does not move the grace date
                    }

                    $values = ['status' => $keep(PlatformEntitlement::PAYMENT_REQUIRED), 'grace_until' => $graceUntil ?? now()->addDays((int) config('platform_subscriptions.grace_days'))];
                    $type = 'payment_failed';
                    $details['grace_until'] = $values['grace_until']->toIso8601String();
                } elseif ($confirmation->event === PlatformPaymentConfirmation::REFUNDED) {
                    if ($current->ended_at !== null && in_array($current->status, [PlatformEntitlement::CANCELLED, PlatformEntitlement::EXPIRED], true)) {
                        return;
                    }

                    $values = ['status' => $keep(PlatformEntitlement::CANCELLED), 'cancelled_at' => now(), 'current_period_ends_at' => now(), 'ended_at' => now(), 'grace_until' => null];
                    $type = 'refunded';
                } else {
                    if ($current->status === PlatformEntitlement::CANCELLED || $current->status === PlatformEntitlement::EXPIRED) {
                        return;
                    }

                    $until = $confirmation->period_ends_at ?? $current->current_period_ends_at;
                    $values = ['status' => $keep(PlatformEntitlement::CANCELLED), 'cancelled_at' => now(), 'current_period_ends_at' => $until, 'ended_at' => $until !== null && $until->isFuture() ? null : now(), 'grace_until' => null];
                    $type = 'cancelled';
                    $details += array_filter(['access_until' => $until !== null && $until->isFuture() ? $until->toIso8601String() : null]);
                }
            }

            $from = $current?->status;
            $entitlement = $this->write($current, $team, $key, $values);

            $event = PlatformEntitlementEvent::create([
                'team_id' => $team->id, 'platform_key' => $key, 'actor_id' => null, 'type' => $type,
                'from_status' => $from, 'to_status' => $entitlement->status, 'details' => $details, 'created_at' => now(),
            ]);

            $this->outbox->enqueue($team, $entitlement, $type, (int) $event->id, $details);
            $changed = true;
        });

        return $changed;
    }

    /**
     * What time alone ends (R-50 section 6): a trial that ran out, a dated grant that reached its date, a grace
     * period that passed, and a cancelled subscription whose paid period finished. Each is re-read under the
     * company's lock before it is changed, so running this every minute, or twice at once, changes each one once.
     *
     * @return array{trials: int, grants: int, grace: int, periods: int}
     */
    public function runLifecycle(int $limit = 200): array
    {
        $done = ['trials' => 0, 'grants' => 0, 'grace' => 0, 'periods' => 0];
        $now = now();

        $due = PlatformEntitlement::query()->with('team')->where(function ($w) use ($now) {
            $w->where(fn ($q) => $q->where('status', PlatformEntitlement::TRIAL)->where('trial_ends_at', '<=', $now))
                ->orWhere(fn ($q) => $q->where('status', PlatformEntitlement::ACTIVE)->whereNotNull('ended_at')->where('ended_at', '<=', $now))
                ->orWhere(fn ($q) => $q->where('status', PlatformEntitlement::PAYMENT_REQUIRED)->whereNotNull('grace_until')->where('grace_until', '<=', $now))
                ->orWhere(fn ($q) => $q->where('status', PlatformEntitlement::CANCELLED)->whereNull('ended_at')->whereNotNull('current_period_ends_at')->where('current_period_ends_at', '<=', $now));
        })->orderBy('id')->limit($limit)->get();

        foreach ($due as $row) {
            if (! $row->team instanceof Team) {
                continue;
            }

            $this->change($row->team, (string) $row->platform_key, function (?PlatformEntitlement $current) use ($row, &$done) {
                $current = $this->mustExist($current);

                $team = $row->team;
                $key = (string) $row->platform_key;

                if ($current->status === PlatformEntitlement::TRIAL && $current->trial_ends_at !== null && ! $current->trial_ends_at->isFuture()) {
                    $plan = $current->plan_id === null ? null : PlatformPlan::find($current->plan_id);
                    $priced = $plan !== null && ! $plan->isFree() && (int) $plan->amount_minor > 0;
                    $done['trials']++;

                    return [
                        $this->write($current, $team, $key, $priced ? ['status' => PlatformEntitlement::PAYMENT_REQUIRED, 'grace_until' => null] : ['status' => PlatformEntitlement::EXPIRED, 'ended_at' => now()]),
                        'trial_ended', null, array_filter(['plan' => $plan?->slug]),
                    ];
                }

                if ($current->status === PlatformEntitlement::ACTIVE && $current->ended_at !== null && ! $current->ended_at->isFuture()) {
                    $done['grants']++;

                    return [$this->write($current, $team, $key, ['status' => PlatformEntitlement::EXPIRED]), $current->source === 'complimentary' ? 'complimentary_ended' : 'ended', null, []];
                }

                if ($current->status === PlatformEntitlement::PAYMENT_REQUIRED && $current->grace_until !== null && ! $current->grace_until->isFuture()) {
                    $done['grace']++;

                    return [$this->write($current, $team, $key, ['status' => PlatformEntitlement::EXPIRED, 'ended_at' => now()]), 'grace_ended', null, []];
                }

                if ($current->status === PlatformEntitlement::CANCELLED && $current->ended_at === null && $current->current_period_ends_at !== null && ! $current->current_period_ends_at->isFuture()) {
                    $done['periods']++;

                    return [$this->write($current, $team, $key, ['ended_at' => now()]), 'period_ended', null, []];
                }

                return null;
            });
        }

        return $done;
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

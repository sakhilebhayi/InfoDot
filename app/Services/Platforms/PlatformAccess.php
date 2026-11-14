<?php

namespace App\Services\Platforms;

use App\Models\PlatformCatalogue;
use App\Models\PlatformEntitlement;
use App\Models\PlatformEntitlementEvent;
use App\Models\PlatformPlan;
use App\Models\PlatformUserAccess;
use App\Models\Team;
use App\Models\User;
use App\Support\EcosystemHandoff;
use App\Support\Platforms;
use App\Support\TeamRoles;
use Illuminate\Support\Collection;

/**
 * The one place that decides whether a person may open a platform for a company (R-50 section 5). Every door asks
 * it: the launch page, the links that lead there, the code redemption, the OAuth authorisation and the read endpoint
 * for platforms. Before it existed, a platform being registered and switched on was the only thing checked.
 *
 * The order of the questions is the specification's: the person, the platform, the company, the personal workspace,
 * the company's entitlement, the person's own permission, and last whether the platform is working. The free core
 * needs no entitlement row. Nothing here calls another service: the records are InfoDot's own, so an outage
 * elsewhere never locks anyone out.
 */
class PlatformAccess
{
    /** The person's current company. */
    public function decide(?User $user, string $key): AccessDecision
    {
        $team = $user?->currentTeam;

        return $this->decideFor($user, $team instanceof Team ? $team : null, $key);
    }

    public function decideFor(?User $user, ?Team $team, string $key): AccessDecision
    {
        // Outside this system, as before R-50: InfoDot itself, the bridge services, and anything the registry does not hold.
        if (! $this->governs($key)) {
            return AccessDecision::allow($team, AccessDecision::NOT_GOVERNED);
        }

        // A platform with its own sign-in cannot be enforced from here: it is listed and opened by its plain link.
        if (EcosystemHandoff::hasNoReceiver($key)) {
            return AccessDecision::allow($team, AccessDecision::MANAGED_IN_PLATFORM);
        }

        if ($user === null || $user->email_verified_at === null || $user->isSuspended()) {
            return AccessDecision::refuse(AccessDecision::NOT_SIGNED_IN, $team);
        }

        $entry = $this->entry($key);

        // Registered but not in the catalogue, or hidden by an administrator: not offered to businesses.
        if ($entry === null || ! $entry->discoverable) {
            return AccessDecision::refuse(AccessDecision::NOT_LAUNCHED, $team);
        }

        if ($team !== null && ! $user->belongsToTeam($team)) {
            return AccessDecision::refuse(AccessDecision::NOT_A_MEMBER, $team);
        }

        if (! $entry->is_core) {
            // A personal workspace (or no company at all) holds the free core only.
            if ($team === null || $team->personal_team) {
                return AccessDecision::refuse(AccessDecision::NO_PERSONAL_ACCESS, $team);
            }

            $entitlement = $this->entitlement($team, $key);

            if ($entitlement === null) {
                return AccessDecision::refuse(AccessDecision::NOT_ACTIVATED, $team);
            }

            if (($refusal = $entitlement->refusal()) !== null) {
                return AccessDecision::refuse($refusal, $team);
            }

            if ($entitlement->access_mode === PlatformEntitlement::SELECTED && ! TeamRoles::manages($user, $team)
                && ! PlatformUserAccess::where('team_id', $team->id)->where('platform_key', $key)->where('user_id', $user->id)->exists()) {
                return AccessDecision::refuse(AccessDecision::NOT_PERMITTED_FOR_YOU, $team);
            }
        }

        if ($entry->isNotLaunched()) {
            return AccessDecision::refuse(AccessDecision::NOT_LAUNCHED, $team);
        }

        if (! $entry->isUsable()) {
            return AccessDecision::refuse(AccessDecision::TEMPORARILY_UNAVAILABLE, $team);
        }

        return AccessDecision::allow($team);
    }

    /** Whether this key is one the entitlement system answers for at all. */
    public function governs(string $key): bool
    {
        return $key !== 'infodot' && config('ecosystem.platforms.'.$key) !== null && ! Platforms::isInternal($key);
    }

    public function entry(string $key): ?PlatformCatalogue
    {
        return PlatformCatalogue::where('key', $key)->first();
    }

    /** @return Collection<string, PlatformCatalogue> */
    public function catalogue(): Collection
    {
        return PlatformCatalogue::query()->orderBy('sort')->orderBy('name')->get()->keyBy('key');
    }

    public function entitlement(Team $team, string $key): ?PlatformEntitlement
    {
        return PlatformEntitlement::where('team_id', $team->id)->where('platform_key', $key)->first();
    }

    /** Whether the company itself holds the platform now (core, or a granting entitlement), whoever asks. */
    public function companyHolds(Team $team, string $key): bool
    {
        $entry = $this->entry($key);

        if ($entry === null) {
            return false;
        }

        if ($entry->is_core) {
            return true;
        }

        return ! $team->personal_team && ($this->entitlement($team, $key)?->grants() ?? false);
    }

    /**
     * How a company that does not hold the platform could get it today: `free` (an approved plan that costs nothing),
     * `unpriced_free` (no approved price exists, so it is free while pricing is being finalised), or `paid` (an
     * approved price exists and there is no free plan: nothing in this step can activate it).
     */
    public function offer(string $key): string
    {
        $plans = PlatformPlan::where('platform_key', $key)->where('active', true)->get()->filter->isSellable();

        if ($plans->contains->isFree()) {
            return 'free';
        }

        return $plans->isEmpty() ? 'unpriced_free' : 'paid';
    }

    /**
     * The state shown to a business (R-50 section 7), derived from the records. Never a person, never a price.
     */
    public function businessState(Team $team, string $key): string
    {
        $entry = $this->entry($key);

        if ($entry === null || $entry->isNotLaunched()) {
            return 'not_launched';
        }

        if (EcosystemHandoff::hasNoReceiver($key)) {
            return 'managed_in_platform';
        }

        if (! $entry->isUsable()) {
            return 'temporarily_unavailable';
        }

        if ($entry->is_core) {
            return 'included';
        }

        if ($team->personal_team) {
            return 'company_required';
        }

        $entitlement = $this->entitlement($team, $key);
        $lapsed = 'subscription_expired';

        $held = match ($entitlement?->status) {
            PlatformEntitlement::PENDING => 'activation_pending',
            PlatformEntitlement::SUSPENDED => 'suspended',
            // A trial that ran out on a priced plan waits for a plan to be paid for; no payment was ever asked for or missed.
            PlatformEntitlement::PAYMENT_REQUIRED => $entitlement->source === 'trial' && ! $entitlement->grants() ? 'trial_ended' : 'payment_required',
            PlatformEntitlement::ACTIVE => $entitlement->grants() ? 'active' : $lapsed,
            PlatformEntitlement::TRIAL => $entitlement->grants() ? 'trial' : 'trial_ended',
            // Cancelled with paid time left is still in use; a free platform that was switched off can simply be switched on again.
            PlatformEntitlement::CANCELLED => $entitlement->grants() ? 'active' : null,
            PlatformEntitlement::EXPIRED => in_array($entitlement->source, PlatformEntitlement::SELF_SERVICE_SOURCES, true) ? null : $lapsed,
            default => null,
        };

        if ($held !== null) {
            return $held;
        }

        // Nothing held: what the company could do next.
        return match ($this->offer($key)) {
            'free' => 'free_to_activate',
            'unpriced_free' => 'free_while_pricing_finalised',
            default => $this->trialPlans($key)->isNotEmpty() && ! $this->hadTrial($team, $key) ? 'trial_available' : 'available_for_purchase',
        };
    }

    /**
     * The plans of a platform that a company can try before paying: on sale (active, approved, priced), not free,
     * and offering a number of trial days inside the configured ceiling. Cheapest first.
     *
     * @return Collection<int, PlatformPlan>
     */
    public function trialPlans(string $key): Collection
    {
        return PlatformPlan::where('platform_key', $key)->where('active', true)->whereNotNull('trial_days')->orderBy('amount_minor')->get()
            ->filter(self::offersTrial(...))->values();
    }

    public static function offersTrial(PlatformPlan $plan): bool
    {
        $days = (int) $plan->getAttribute('trial_days');

        return $plan->isSellable() && ! $plan->isFree() && $days >= 1 && $days <= (int) config('platform_subscriptions.max_trial_days');
    }

    /** Whether the company has ever had a trial of the platform, whoever started it. One trial per company and platform. */
    public function hadTrial(Team $team, string $key): bool
    {
        return $team->exists && PlatformEntitlementEvent::where('team_id', $team->id)->where('platform_key', $key)->where('type', 'trial_started')->exists();
    }
}

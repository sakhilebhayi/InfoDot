<?php

namespace App\Services\Platforms;

use App\Models\PlatformCatalogue;
use App\Models\PlatformEntitlement;
use App\Models\PlatformPlan;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * PlatformAccess for a page that asks about every platform at once (the Platforms page, Dot Apps, the Analytics
 * panel). It decides nothing itself: every answer is the parent's. It only reads the catalogue, a company's
 * entitlements and the plans once each and hands them to the parent's questions, so a list of twenty platforms costs
 * three queries instead of a hundred. Made fresh for each page; never bound in the container, so the doors that
 * decide access keep reading the records at the moment they are asked.
 */
class PreloadedAccess extends PlatformAccess
{
    /** @var Collection<string, PlatformCatalogue>|null */
    private ?Collection $entries = null;

    /** @var array<int, Collection<string, PlatformEntitlement>> */
    private array $entitlements = [];

    /** @var Collection<string, Collection<int, PlatformPlan>>|null */
    private ?Collection $plans = null;

    public function entry(string $key): ?PlatformCatalogue
    {
        return $this->catalogue()->get($key);
    }

    public function catalogue(): Collection
    {
        return $this->entries ??= parent::catalogue();
    }

    public function entitlement(Team $team, string $key): ?PlatformEntitlement
    {
        if (! $team->exists) {
            return null;
        }

        $this->entitlements[$team->id] ??= PlatformEntitlement::where('team_id', $team->id)->get()->keyBy('platform_key');

        return $this->entitlements[$team->id]->get($key);
    }

    public function offer(string $key): string
    {
        $plans = $this->sellablePlans($key);

        if ($plans->contains->isFree()) {
            return 'free';
        }

        return $plans->isEmpty() ? 'unpriced_free' : 'paid';
    }

    /**
     * The plans of a platform that may be shown with a price: active, approved by a platform administrator, with an
     * amount and a currency. Anything else is not a price.
     *
     * @return Collection<int, PlatformPlan>
     */
    public function sellablePlans(string $key): Collection
    {
        $this->plans ??= PlatformPlan::where('active', true)->orderBy('amount_minor')->get()->filter->isSellable()->groupBy('platform_key');

        return $this->plans->get($key, collect())->values();
    }
}

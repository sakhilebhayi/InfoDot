<?php

namespace App\Services\Platforms;

use App\Models\PlatformBundle;
use App\Models\PlatformCatalogue;
use App\Models\PlatformCatalogueEvent;
use App\Models\PlatformPlan;
use App\Models\User;
use App\Support\EcosystemHandoff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The only writer of the catalogue, the plans and the bundles once they exist (R-50 section 9). Platform
 * administrators only, and every change needs a written reason: what changed, who changed it and why are appended to
 * `platform_catalogue_events` in the same transaction.
 *
 * Prices. Nothing here supplies an amount or a currency: both come from what the administrator typed, and a plan or
 * bundle can be sold only after a separate, explicit approval of the price it then carries. Changing the amount, the
 * currency, the interval or the pricing model afterwards withdraws that approval, so a price nobody approved is
 * never on sale. None of this touches a company's entitlement: what a company already holds is changed only by
 * Entitlements.
 */
class CatalogueAdmin
{
    public const MODELS = ['free', 'included', 'subscription', 'per_user', 'usage', 'enterprise'];

    public const INTERVALS = ['month', 'year'];

    private const PLATFORM_FIELDS = ['operational_status', 'discoverable', 'tagline', 'description', 'benefit', 'audience'];

    private const PLAN_FIELDS = ['name', 'model', 'interval', 'amount_minor', 'currency', 'trial_days', 'active'];

    private const BUNDLE_FIELDS = ['name', 'description', 'interval', 'amount_minor', 'currency', 'active'];

    /** The fields that are the price: changing any of them withdraws the approval. */
    private const PRICE_FIELDS = ['model', 'interval', 'amount_minor', 'currency'];

    public function __construct(private readonly PlatformAccess $access) {}

    /** @param  array<string, mixed>  $values */
    public function updatePlatform(PlatformCatalogue $entry, array $values, User $actor, string $reason): PlatformCatalogue
    {
        $values = array_intersect_key($values, array_flip(self::PLATFORM_FIELDS));

        if (isset($values['operational_status']) && ! in_array($values['operational_status'], PlatformCatalogue::STATUSES, true)) {
            throw new EntitlementRefused('That is not an operational status.');
        }

        // The free core is the owner's decision and is how a company reaches its files, its documents and the place it pays.
        if ($entry->is_core && array_key_exists('discoverable', $values) && ! $values['discoverable']) {
            throw new EntitlementRefused('A core platform is included for every company and cannot be hidden.');
        }

        return $this->record($entry, $values, $actor, $reason, 'platform', $entry->key, 'platform_updated');
    }

    /** @param  array<string, mixed>  $values */
    public function createPlan(string $platformKey, array $values, User $actor, string $reason): PlatformPlan
    {
        $entry = $this->access->governs($platformKey) ? $this->access->entry($platformKey) : null;

        if ($entry === null || $entry->is_core || EcosystemHandoff::hasNoReceiver($platformKey)) {
            throw new EntitlementRefused('Plans belong to platforms a company switches on here. This one is core, has its own sign-in, or is not in the catalogue.');
        }

        $values = $this->priceRules(array_intersect_key($values, array_flip(self::PLAN_FIELDS)));
        $slug = $this->freeSlug((string) ($values['name'] ?? ''), fn (string $slug) => PlatformPlan::where('platform_key', $platformKey)->where('slug', $slug)->exists());

        // A new plan is never approved, whatever was sent.
        $plan = new PlatformPlan(['platform_key' => $platformKey, 'slug' => $slug, 'approved_at' => null, 'active' => false]);

        return $this->record($plan, $values, $actor, $reason, 'plan', $platformKey.'/'.$slug, 'plan_created');
    }

    /** @param  array<string, mixed>  $values */
    public function updatePlan(PlatformPlan $plan, array $values, User $actor, string $reason): PlatformPlan
    {
        $values = $this->priceRules(array_intersect_key($values, array_flip(self::PLAN_FIELDS)) + $plan->only(['model', 'amount_minor', 'currency', 'interval']));

        return $this->record($plan, $this->withdrawIfPriceChanged($plan, $values), $actor, $reason, 'plan', $plan->platform_key.'/'.$plan->slug, 'plan_updated');
    }

    /** The explicit step that puts a price on sale: the administrator approves the amount and currency the plan carries now. */
    public function approvePlan(PlatformPlan $plan, User $actor, string $reason): PlatformPlan
    {
        $this->mustCarryAPrice($plan->amount_minor, $plan->currency);

        if ($plan->approved_at !== null) {
            return $plan;
        }

        return $this->record($plan, ['approved_at' => now()], $actor, $reason, 'plan', $plan->platform_key.'/'.$plan->slug, 'price_approved');
    }

    public function withdrawPlanApproval(PlatformPlan $plan, User $actor, string $reason): PlatformPlan
    {
        return $plan->approved_at === null ? $plan : $this->record($plan, ['approved_at' => null], $actor, $reason, 'plan', $plan->platform_key.'/'.$plan->slug, 'price_approval_withdrawn');
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $platformKeys
     */
    public function createBundle(array $values, array $platformKeys, User $actor, string $reason): PlatformBundle
    {
        $values = $this->priceRules(array_intersect_key($values, array_flip(self::BUNDLE_FIELDS)), bundle: true);
        $keys = $this->bundleKeys($platformKeys);
        $slug = $this->freeSlug((string) ($values['name'] ?? ''), fn (string $slug) => PlatformBundle::where('slug', $slug)->exists());

        return DB::transaction(function () use ($values, $keys, $slug, $actor, $reason) {
            $bundle = $this->record(new PlatformBundle(['slug' => $slug, 'approved_at' => null, 'active' => false]), $values, $actor, $reason, 'bundle', $slug, 'bundle_created', ['platforms' => $keys]);
            $this->writeItems($bundle, $keys);

            return $bundle;
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $platformKeys
     */
    public function updateBundle(PlatformBundle $bundle, array $values, array $platformKeys, User $actor, string $reason): PlatformBundle
    {
        $values = $this->priceRules(array_intersect_key($values, array_flip(self::BUNDLE_FIELDS)) + $bundle->only(['amount_minor', 'currency', 'interval']), bundle: true);
        $keys = $this->bundleKeys($platformKeys);
        $before = $bundle->items()->orderBy('platform_key')->pluck('platform_key')->all();
        $membersChanged = $before !== $keys;
        $values = $this->withdrawIfPriceChanged($bundle, $values);

        // What a bundle contains is part of what its price buys, so changing the contents withdraws the approval too.
        if ($membersChanged && $bundle->approved_at !== null) {
            $values['approved_at'] = null;
        }

        return DB::transaction(function () use ($bundle, $values, $keys, $before, $membersChanged, $actor, $reason) {
            $bundle = $this->record($bundle, $values, $actor, $reason, 'bundle', $bundle->slug, 'bundle_updated', $membersChanged ? ['platforms' => $keys] : [], $membersChanged ? ['platforms' => $before] : []);

            if ($membersChanged) {
                $this->writeItems($bundle, $keys);
            }

            return $bundle;
        });
    }

    public function approveBundle(PlatformBundle $bundle, User $actor, string $reason): PlatformBundle
    {
        $this->mustCarryAPrice($bundle->amount_minor, $bundle->currency);

        if ($bundle->items()->count() === 0) {
            throw new EntitlementRefused('A bundle with no platforms in it cannot be approved.');
        }

        if ($bundle->approved_at !== null) {
            return $bundle;
        }

        return $this->record($bundle, ['approved_at' => now()], $actor, $reason, 'bundle', $bundle->slug, 'price_approved');
    }

    public function withdrawBundleApproval(PlatformBundle $bundle, User $actor, string $reason): PlatformBundle
    {
        return $bundle->approved_at === null ? $bundle : $this->record($bundle, ['approved_at' => null], $actor, $reason, 'bundle', $bundle->slug, 'price_approval_withdrawn');
    }

    /**
     * Fill, save and write the audit entry in one transaction. Nothing is written when nothing changed.
     *
     * @template T of Model
     *
     * @param  T  $subject
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $extraAfter
     * @param  array<string, mixed>  $extraBefore
     * @return T
     */
    private function record(Model $subject, array $values, User $actor, string $reason, string $type, string $key, string $event, array $extraAfter = [], array $extraBefore = []): Model
    {
        Gate::forUser($actor)->authorize('administer-platforms');

        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new EntitlementRefused('Write why the change is being made, in at least 10 characters. It is kept in the audit trail.');
        }

        return DB::transaction(function () use ($subject, $values, $actor, $reason, $type, $key, $event, $extraAfter, $extraBefore) {
            $subject->fill($values);
            $changed = array_keys($subject->getDirty());

            if ($subject->exists && $changed === [] && $extraAfter === []) {
                return $subject;
            }

            $before = $subject->exists ? $this->plain(array_intersect_key($subject->getRawOriginal(), array_flip($changed))) : [];
            $subject->save();

            PlatformCatalogueEvent::create([
                'actor_id' => $actor->id, 'subject_type' => $type, 'subject_key' => $key, 'type' => $event,
                'before' => ($before + $extraBefore) ?: null,
                'after' => $this->plain(array_intersect_key($subject->getAttributes(), array_flip($changed))) + $extraAfter,
                'reason' => Str::limit($reason, 250, ''), 'created_at' => now(),
            ]);

            return $subject;
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function plain(array $values): array
    {
        unset($values['updated_at'], $values['created_at']);

        return array_map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('c') : $value, $values);
    }

    /**
     * The rules a price obeys, whoever typed it. Never supplies a value.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function priceRules(array $values, bool $bundle = false): array
    {
        if (trim((string) ($values['name'] ?? 'x')) === '') {
            throw new EntitlementRefused('Give it a name.');
        }

        if (! $bundle && ! in_array($values['model'] ?? null, self::MODELS, true)) {
            throw new EntitlementRefused('Choose how the plan is priced.');
        }

        if (($values['interval'] ?? null) !== null && ! in_array($values['interval'], self::INTERVALS, true)) {
            throw new EntitlementRefused('A price repeats monthly or yearly, or not at all.');
        }

        $amount = $values['amount_minor'] ?? null;
        $currency = $values['currency'] ?? null;

        if ($amount !== null && (! is_int($amount) || $amount < 0)) {
            throw new EntitlementRefused('The amount is a whole number of minor units (cents), zero or more.');
        }

        if ($currency !== null && ! preg_match('/\A[A-Z]{3}\z/', (string) $currency)) {
            throw new EntitlementRefused('The currency is a three-letter code in capitals.');
        }

        if (($amount === null) !== ($currency === null)) {
            throw new EntitlementRefused('An amount and its currency go together: give both, or neither while the price is undecided.');
        }

        if (! $bundle && $amount !== null && ($amount === 0) !== in_array($values['model'], ['free', 'included'], true)) {
            throw new EntitlementRefused('A free or included plan costs 0, and every other kind of plan costs more than 0.');
        }

        if ($bundle && $amount === 0) {
            throw new EntitlementRefused('A bundle is sold at a price above 0.');
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withdrawIfPriceChanged(Model $subject, array $values): array
    {
        if ($subject->getAttribute('approved_at') === null) {
            return $values;
        }

        foreach (self::PRICE_FIELDS as $field) {
            $was = $subject->getAttribute($field);

            if (array_key_exists($field, $values) && (($values[$field] === null) !== ($was === null) || (string) $values[$field] !== (string) $was)) {
                return $values + ['approved_at' => null];
            }
        }

        return $values;
    }

    private function mustCarryAPrice(?int $amount, ?string $currency): void
    {
        if ($amount === null || $currency === null) {
            throw new EntitlementRefused('There is no price to approve yet. Enter the amount and the currency first.');
        }
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function bundleKeys(array $keys): array
    {
        $keys = array_values(array_unique(array_map('strval', $keys)));
        sort($keys);

        if (count($keys) < 2) {
            throw new EntitlementRefused('A bundle holds at least two platforms.');
        }

        foreach ($keys as $key) {
            $entry = $this->access->governs($key) ? $this->access->entry($key) : null;

            if ($entry === null || $entry->is_core || EcosystemHandoff::hasNoReceiver($key)) {
                throw new EntitlementRefused('A bundle holds platforms a company switches on here: not the free core, and not a platform with its own sign-in.');
            }
        }

        return $keys;
    }

    /** @param  list<string>  $keys */
    private function writeItems(PlatformBundle $bundle, array $keys): void
    {
        $bundle->items()->whereNotIn('platform_key', $keys)->delete();

        foreach ($keys as $key) {
            $bundle->items()->firstOrCreate(['platform_key' => $key]);
        }
    }

    /** @param  callable(string): bool  $taken */
    private function freeSlug(string $name, callable $taken): string
    {
        $base = Str::limit(Str::slug($name) ?: 'plan', 50, '');
        $slug = $base;

        for ($n = 2; $taken($slug); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }
}

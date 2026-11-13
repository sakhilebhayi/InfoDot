<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformBundle;
use App\Models\PlatformPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\Platforms\CatalogueAdmin;
use App\Support\Knowledge\Signature;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Shared ground for the R-50 admin centre suites. Plans and bundles are made the way an administrator makes them,
 * through CatalogueAdmin, so a test never depends on a price that the application itself supplies (it supplies none).
 */
abstract class AdminCentreTestCase extends PlatformTestCase
{
    protected const WHY = 'Agreed with the owner on the weekly call.';

    protected function moderator(): User
    {
        $user = $this->person();
        $user->forceFill(['is_moderator' => true])->save();

        return $user->fresh();
    }

    /** A plan created, and unless told otherwise approved and switched on, by an administrator. */
    protected function plan(string $key = 'hr', array $values = [], bool $approved = true, ?User $by = null): PlatformPlan
    {
        $by ??= $this->administrator();
        $catalogue = app(CatalogueAdmin::class);
        $plan = $catalogue->createPlan($key, $values + ['name' => 'Team', 'model' => 'subscription', 'interval' => 'month', 'amount_minor' => 24900, 'currency' => 'ZAR', 'active' => true], $by, self::WHY);

        return $approved ? $catalogue->approvePlan($plan, $by, self::WHY) : $plan;
    }

    protected function bundle(array $keys = ['hr', 'projects'], bool $approved = true, ?User $by = null): PlatformBundle
    {
        $by ??= $this->administrator();
        $catalogue = app(CatalogueAdmin::class);
        $bundle = $catalogue->createBundle(['name' => 'Office pair', 'interval' => 'month', 'amount_minor' => 39900, 'currency' => 'ZAR', 'active' => true], $keys, $by, self::WHY);

        return $approved ? $catalogue->approveBundle($bundle, $by, self::WHY) : $bundle;
    }

    /** Switch the confirmation endpoint on with a key made for this test, and return it. */
    protected function financeKey(): string
    {
        $key = Str::random(48);
        config(['platform_subscriptions.confirmation_secret' => $key]);

        return $key;
    }

    /** @param  array<string, mixed>  $overrides */
    protected function paymentEvent(Team $team, string $event = 'paid', array $overrides = []): array
    {
        return array_filter($overrides + [
            'event_id' => 'evt-'.Str::random(12),
            'event' => $event,
            'company_uuid' => (string) $team->uuid,
            'platform' => 'hr',
            'plan' => in_array($event, ['paid', 'renewed'], true) ? 'team' : null,
            'amount_minor' => in_array($event, ['paid', 'renewed', 'refunded'], true) ? 24900 : null,
            'currency' => in_array($event, ['paid', 'renewed', 'refunded'], true) ? 'ZAR' : null,
            'reference' => 'SUB-1001',
            'period_end' => in_array($event, ['paid', 'renewed'], true) ? now()->addMonth()->toIso8601String() : null,
            'occurred_at' => now()->toIso8601String(),
        ], fn ($value) => $value !== null);
    }

    /** POST a body to the confirmation endpoint, signed with $key (or with headers of the caller's choosing). */
    protected function deliver(array|string $body, string $key, ?int $timestamp = null, ?string $signature = null): TestResponse
    {
        $raw = is_string($body) ? $body : (string) json_encode($body);
        $timestamp ??= time();

        return $this->call('POST', '/api/finance/platform-subscription-events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_INFODOT_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_INFODOT_SIGNATURE' => $signature ?? Signature::sign($key, $timestamp, $raw),
        ], $raw);
    }
}

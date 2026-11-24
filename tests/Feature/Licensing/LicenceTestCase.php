<?php

namespace Tests\Feature\Licensing;

use App\Models\LicenceBatch;
use App\Models\Team;
use App\Models\User;
use App\Services\Licensing\LicenceAssignments;
use App\Services\Licensing\LicenceBatches;
use App\Support\Knowledge\Signature;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Platforms\AdminCentreTestCase;

/**
 * Shared ground for the R-53 phase 2 suites: a priced configuration (set here, never shipped), a stand-in for Dot.Finance that signs
 * confirmations the way CONTRACT.md says, and companies that already hold a paid batch. Finance itself is not involved: only its signed
 * messages are produced.
 */
abstract class LicenceTestCase extends AdminCentreTestCase
{
    protected string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->secret = Str::random(48);
        config([
            'platform_subscriptions.confirmation_secret' => $this->secret,
            'licensing.included_seats' => 1, 'licensing.grace_days' => 14, 'licensing.pending_invitations_reserve_a_seat' => true,
            'licensing.price.amount_minor_units' => 9900, 'licensing.price.annual_amount_minor_units' => 99000, 'licensing.price.currency' => 'ZAR',
            // The default bands are flat (R-59 1E); these tests exercise the band mechanism itself, so they set tiers explicitly.
            'licensing.price.bands' => [['up_to' => 5, 'percent' => 100], ['up_to' => 20, 'percent' => 90], ['up_to' => null, 'percent' => 80]],
        ]);
    }

    /** A Finance confirmation for a batch, as the contract defines it. @param  array<string, mixed>  $over */
    protected function licenceEvent(LicenceBatch $batch, string $type = 'paid', array $over = []): array
    {
        $received = in_array($type, ['paid', 'renewed'], true);

        return array_filter($over + [
            'kind' => 'licence_batch',
            'event_id' => 'lic-'.Str::random(14),
            'type' => $type,
            'batch_uuid' => $batch->uuid,
            'team_uuid' => (string) Team::findOrFail($batch->team_id)->uuid,
            'quantity' => $batch->quantity,
            'interval' => $batch->interval,
            'period_start' => $received ? now()->toIso8601String() : null,
            'period_end' => $received ? ($batch->interval === 'year' ? now()->addYear() : now()->addMonth())->toIso8601String() : null,
            'amount_minor' => $received ? $batch->total_minor : null,
            'currency' => $received ? $batch->currency : null,
            'paystack_reference' => 'PSK-'.Str::random(8),
        ], fn ($v) => $v !== null);
    }

    /** @param  array<string, mixed>|string  $body */
    protected function finance(array|string $body, ?string $key = null, ?int $timestamp = null): TestResponse
    {
        $raw = is_string($body) ? $body : (string) json_encode($body);
        $timestamp ??= time();

        return $this->call('POST', '/api/finance/platform-subscription-events', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_INFODOT_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_INFODOT_SIGNATURE' => Signature::sign($key ?? $this->secret, $timestamp, $raw),
        ], $raw);
    }

    /** A pending batch made the way the owner makes it. */
    protected function pending(Team $team, int $quantity = 2, string $interval = 'month'): LicenceBatch
    {
        return app(LicenceBatches::class)->createPending($team, $team->owner, $quantity, $interval);
    }

    /** A batch that is paid for: created by the owner and activated by a verified `paid` event, not by any shortcut. */
    protected function paid(Team $team, int $quantity = 2, string $interval = 'month', array $over = []): LicenceBatch
    {
        $batch = $this->pending($team, $quantity, $interval);
        $this->finance($this->licenceEvent($batch, 'paid', $over))->assertOk()->assertExactJson(['status' => 'applied']);

        return $batch->fresh();
    }

    /** A company with this many ordinary members (each added through the real action path, so the ledger sees them join). */
    protected function companyWithMembers(int $members = 0, string $role = 'member'): Team
    {
        $team = $this->company();

        for ($i = 0; $i < $members; $i++) {
            $this->join($team, $role);
        }

        return $team->fresh();
    }

    protected function join(Team $team, string $role = 'member'): User
    {
        $user = $this->person();
        $team->users()->attach($user, ['role' => $role]);
        app(LicenceAssignments::class)->recordJoin($team, $user);
        $team->unsetRelation('users');

        return $user->fresh();
    }
}

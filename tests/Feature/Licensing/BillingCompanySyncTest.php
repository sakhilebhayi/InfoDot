<?php

namespace Tests\Feature\Licensing;

use App\Models\Team;
use App\Models\User;
use App\Services\Licensing\BillingCompanies;
use App\Support\Knowledge\Signature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** InfoDot tells Dot.Billing when a company is created, renamed or deleted; Billing only registers and flags. A failure never reaches the person. */
class BillingCompanySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['licensing.billing_connected' => true, 'licensing.billing.url' => 'https://billing.test', 'licensing.billing.secret' => 'platform-secret', 'licensing.billing.platform' => 'infodot']);
        Http::fake(['billing.test/*' => Http::response(['status' => 'registered'])]);
    }

    private function fake(array $map): void
    {
        Http::swap(new Factory);
        Http::fake($map);
    }

    private function team(string $name = 'Thandi Foods', bool $personal = false): Team
    {
        $owner = User::factory()->create();

        return Team::factory()->create(['user_id' => $owner->id, 'personal_team' => $personal, 'name' => $name]);
    }

    private function bodies(): array
    {
        return Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/api/companies'))->map(fn ($pair) => json_decode($pair[0]->body(), true))->values()->all();
    }

    public function test_the_company_is_sent_with_its_uuid_and_name_in_a_signed_call(): void
    {
        $team = $this->team();

        $this->assertTrue(app(BillingCompanies::class)->sync($team));

        Http::assertSent(function (Request $r) use ($team) {
            return str_ends_with($r->url(), '/api/companies') && json_decode($r->body(), true) === ['company_uuid' => (string) $team->uuid, 'name' => 'Thandi Foods', 'deleted' => false]
                && $r->header('X-InfoDot-Platform')[0] === 'infodot'
                && Signature::valid('platform-secret', $r->header('X-InfoDot-Timestamp')[0], $r->header('X-InfoDot-Signature')[0], $r->body(), 300);
        });
    }

    public function test_a_deleted_company_is_sent_as_deleted(): void
    {
        $team = $this->team();

        app(BillingCompanies::class)->sync($team, true);

        $this->assertTrue($this->bodies()[0]['deleted']);
    }

    public function test_personal_workspaces_and_a_switched_off_link_send_nothing(): void
    {
        $this->assertFalse(app(BillingCompanies::class)->sync($this->team('Mine', true)));
        config(['licensing.billing_connected' => false]);
        $this->assertFalse(app(BillingCompanies::class)->sync($this->team()));

        Http::assertNothingSent();
    }

    public function test_creating_renaming_and_deleting_a_company_each_queue_one_call_after_the_response(): void
    {
        Bus::fake();

        $team = $this->team();
        Bus::assertDispatchedTimes(CallQueuedClosure::class, 1);

        $team->forceFill(['industry' => 'retail'])->save();
        Bus::assertDispatchedTimes(CallQueuedClosure::class, 1);   // only a new name matters to Billing

        $team->forceFill(['name' => 'Thandi Foods (Pty) Ltd'])->save();
        Bus::assertDispatchedTimes(CallQueuedClosure::class, 2);

        $team->delete();
        Bus::assertDispatchedTimes(CallQueuedClosure::class, 4);   // one for Billing, one for the existing membership webhook to the other platforms
    }

    public function test_a_personal_workspace_queues_nothing(): void
    {
        Bus::fake();

        User::factory()->withPersonalTeam()->create();

        Bus::assertNotDispatched(CallQueuedClosure::class);
    }

    public function test_a_billing_failure_never_reaches_the_person_and_is_logged(): void
    {
        $this->fake(['billing.test/*' => Http::response([], 500)]);
        Log::spy();
        $team = $this->team();

        $this->assertFalse(app(BillingCompanies::class)->sync($team));
        Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, 'Billing company sync'));

        $this->fake(['billing.test/*' => fn () => throw new ConnectionException('down')]);
        $this->assertFalse(app(BillingCompanies::class)->sync($team));
        $this->assertNotNull(Team::find($team->id));
    }

    public function test_the_backfill_command_sends_every_company_and_only_companies(): void
    {
        $this->team('A');
        $this->team('B');
        User::factory()->withPersonalTeam()->create();

        $this->artisan('licence:sync-companies', ['--dry-run' => true])->assertSuccessful()->expectsOutputToContain('Would send 2');
        Http::assertNothingSent();

        $this->artisan('licence:sync-companies')->assertSuccessful()->expectsOutputToContain('Sent 2 company record(s); 0 failed');
        $this->assertCount(2, $this->bodies());
        $this->artisan('licence:sync-companies')->assertSuccessful();   // safe to repeat
    }

    public function test_the_backfill_command_says_so_when_the_link_is_off(): void
    {
        config(['licensing.billing_connected' => false]);

        $this->artisan('licence:sync-companies')->assertSuccessful()->expectsOutputToContain('nothing sent');
    }

    public function test_the_backfill_command_reports_failures(): void
    {
        $this->team('A');
        $this->fake(['billing.test/*' => Http::response([], 500)]);

        $this->artisan('licence:sync-companies')->assertFailed()->expectsOutputToContain('1 failed');
    }
}

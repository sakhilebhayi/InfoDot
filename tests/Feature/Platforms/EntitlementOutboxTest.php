<?php

namespace Tests\Feature\Platforms;

use App\Services\Ops\OpsCheck;
use App\Services\Platforms\EntitlementOutbox;
use App\Support\MembershipWebhook;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Every entitlement change is told to its platform through a durable outbox (R-50): written with the change, signed
 * with that platform's secret, retried like the points outbox, and never retried for days against a platform that has
 * not built the receiver yet.
 */
class EntitlementOutboxTest extends PlatformTestCase
{
    private const SECRET = 'forms-service-secret-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config(['ecosystem.service_secrets' => ['forms' => self::SECRET, 'hr' => 'hr-service-secret-for-tests']]);
    }

    private function flush(): int
    {
        return app(EntitlementOutbox::class)->flush();
    }

    private function row(): object
    {
        return DB::table('entitlement_outbox')->orderBy('id')->first();
    }

    public function test_every_change_writes_one_outbox_row_in_the_same_transaction(): void
    {
        $team = $this->company();
        $member = $this->memberOf($team);

        $this->entitlements()->activate($team, 'forms', $team->owner);
        $this->entitlements()->setAccessMode($team, 'forms', 'selected', $team->owner);
        $this->entitlements()->grantUser($team, 'forms', $member, $team->owner);
        $this->entitlements()->revokeUser($team, 'forms', $member, $team->owner);
        $this->entitlements()->deactivate($team, 'forms', $team->owner);

        $payloads = DB::table('entitlement_outbox')->orderBy('id')->pluck('payload')->map(fn ($json) => json_decode($json, true));

        $this->assertSame(['activated', 'access_mode_changed', 'user_granted', 'user_revoked', 'deactivated'], $payloads->pluck('type')->all());
        $this->assertSame([true, true, true, true, false], $payloads->pluck('entitled')->all());
        $this->assertSame(5, $payloads->pluck('event_id')->unique()->count());

        $first = $payloads[0];
        $this->assertSame(['event_id', 'event', 'type', 'platform', 'team_uuid', 'status', 'entitled', 'source', 'access_mode', 'occurred_at'], array_keys($first));
        $this->assertSame(['entitlement.changed', 'forms', $team->uuid, 'active', 'unpriced_free'], [$first['event'], $first['platform'], $first['team_uuid'], $first['status'], $first['source']]);
        $this->assertSame($member->uuid, $payloads[2]['user_uuid']);
        $this->assertStringNotContainsString($member->email, DB::table('entitlement_outbox')->pluck('payload')->implode(''));
    }

    public function test_a_refused_change_leaves_no_outbox_row(): void
    {
        $team = $this->company();
        $member = $this->memberOf($team);

        try {
            $this->entitlements()->activate($team, 'forms', $member);
        } catch (\Throwable) {
            // refused
        }

        $this->assertSame(0, DB::table('entitlement_outbox')->count());
    }

    public function test_delivery_is_a_signed_post_to_the_platform_with_its_own_secret(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 202)]);
        $team = $this->company();
        $this->entitlements()->activate($team, 'forms', $team->owner);

        $this->assertSame(1, $this->flush());

        Http::assertSent(function (Request $request) {
            $timestamp = (int) $request->header(MembershipWebhook::HEADER_TIMESTAMP)[0];

            return $request->url() === 'https://forms.infodot.co.za/api/ecosystem/entitlement'
                && $request->method() === 'POST'
                && $request->header(MembershipWebhook::HEADER_SIGNATURE)[0] === MembershipWebhook::sign(self::SECRET, $timestamp, $request->body())
                && $request->header(MembershipWebhook::HEADER_SIGNATURE)[0] !== MembershipWebhook::sign('hr-service-secret-for-tests', $timestamp, $request->body())
                && abs(time() - $timestamp) < 5
                && json_decode($request->body(), true)['event'] === 'entitlement.changed';
        });

        $this->assertNotNull($this->row()->sent_at);
        $this->assertSame(0, $this->flush(), 'a delivered event is never sent again');
        Http::assertSentCount(1);
    }

    public function test_an_outage_is_retried_with_growing_delays_and_given_up_on_after_25_attempts(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        $team = $this->company();
        $this->entitlements()->activate($team, 'forms', $team->owner);

        $this->flush();
        $row = $this->row();
        $this->assertSame([1, 'HTTP 503'], [(int) $row->attempts, $row->last_error]);
        $this->assertEqualsWithDelta(now()->addSeconds(30)->timestamp, strtotime($row->next_attempt_at), 2);
        $this->assertSame(0, $this->flush(), 'not due yet');

        foreach ([120, 600, 1800, 3600, 21600] as $i => $delay) {
            $this->travelTo(Carbon::parse($this->row()->next_attempt_at)->addSecond());
            $this->flush();
            $this->assertEqualsWithDelta(now()->addSeconds($delay)->timestamp, strtotime($this->row()->next_attempt_at), 2, 'attempt '.($i + 2));
        }

        DB::table('entitlement_outbox')->update(['attempts' => 24, 'next_attempt_at' => now()->subSecond()]);
        $this->flush();

        $row = $this->row();
        $this->assertNotNull($row->failed_at);
        $this->assertNull($row->sent_at);
        $this->assertSame('gave up: HTTP 503', $row->last_error);
        $this->assertSame(1, app(OpsCheck::class)->run()['outbox_entitlements_parked']);
    }

    public function test_a_platform_that_has_not_built_the_receiver_is_recorded_as_delivered_not_implemented_after_three_attempts(): void
    {
        Http::fake(['*' => Http::response('Not Found', 404)]);
        $team = $this->company();
        $this->entitlements()->activate($team, 'forms', $team->owner);

        foreach ([1, 2] as $attempt) {
            $this->flush();
            $this->assertNull($this->row()->sent_at, "attempt $attempt is retried");
            $this->assertSame($attempt, (int) $this->row()->attempts);
            $this->travelTo(Carbon::parse($this->row()->next_attempt_at)->addSecond());
        }

        $this->flush();

        $row = $this->row();
        $this->assertNotNull($row->sent_at);
        $this->assertNull($row->failed_at);
        $this->assertSame(EntitlementOutbox::NOT_IMPLEMENTED, $row->last_error);
        $this->assertSame(3, (int) $row->attempts);

        $this->travel(2)->days();
        $this->assertSame(0, $this->flush());
        Http::assertSentCount(3);
        $this->assertSame(0, app(OpsCheck::class)->run()['outbox_entitlements_parked'], 'not a failure to alert about');
    }

    public function test_a_bad_event_is_parked_a_wrong_secret_is_retried_slowly_and_an_unreachable_platform_is_retried(): void
    {
        $team = $this->company();
        $this->entitlements()->activate($team, 'forms', $team->owner);

        Http::fake(['*' => Http::response('no', 401)]);
        $this->flush();
        $this->assertStringContainsString('check the shared secret', $this->row()->last_error);
        $this->assertEqualsWithDelta(now()->addHour()->timestamp, strtotime($this->row()->next_attempt_at), 2);

        Http::swap(new Factory);
        Http::fake(fn () => throw new ConnectionException('timed out'));
        DB::table('entitlement_outbox')->update(['next_attempt_at' => now()->subSecond()]);
        $this->flush();
        $this->assertSame('unreachable', $this->row()->last_error);
        $this->assertNull($this->row()->failed_at);

        Http::swap(new Factory);
        Http::fake(['*' => Http::response('bad', 422)]);
        DB::table('entitlement_outbox')->update(['next_attempt_at' => now()->subSecond()]);
        $this->flush();
        $this->assertNotNull($this->row()->failed_at);
        $this->assertSame('HTTP 422', $this->row()->last_error);
    }

    public function test_a_platform_without_a_secret_gets_nothing_sent_and_is_retried_slowly(): void
    {
        Http::fake();
        $team = $this->company();
        $this->entitlements()->activate($team, 'projects', $team->owner);

        $this->assertSame(0, $this->flush());

        Http::assertNothingSent();
        $this->assertNull($this->row()->sent_at);
        $this->assertEqualsWithDelta(now()->addHour()->timestamp, strtotime($this->row()->next_attempt_at), 2);
    }

    public function test_events_about_one_company_and_platform_are_delivered_in_order(): void
    {
        $team = $this->company();
        $this->entitlements()->activate($team, 'forms', $team->owner);
        $this->entitlements()->deactivate($team, 'forms', $team->owner);
        $this->entitlements()->activate($team, 'hr', $team->owner);

        Http::fake(['forms.infodot.co.za/*' => Http::response('down', 503), 'hr.infodot.co.za/*' => Http::response(['ok' => true])]);

        $this->assertSame(1, $this->flush(), 'another platform is not held up');

        $rows = DB::table('entitlement_outbox')->orderBy('id')->get();
        $this->assertSame([1, 0], [(int) $rows[0]->attempts, (int) $rows[1]->attempts], 'the deactivation waits behind the activation');
        $this->assertNotNull($rows[2]->sent_at);
    }

    public function test_the_same_event_is_never_queued_twice(): void
    {
        $team = $this->company();
        $entitlement = $this->entitlements()->activate($team, 'forms', $team->owner);

        app(EntitlementOutbox::class)->enqueue($team, $entitlement, 'activated', (int) DB::table('platform_entitlement_events')->value('id'));

        $this->assertSame(1, DB::table('entitlement_outbox')->count());
    }

    public function test_the_flush_command_is_scheduled_every_minute_and_reports_what_it_sent(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);
        $team = $this->company();
        $this->entitlements()->activate($team, 'forms', $team->owner);

        $this->artisan('platforms:flush-outbox')->expectsOutput('Delivered 1 event(s).')->assertSuccessful();

        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'platforms:flush-outbox'));
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }

    public function test_ops_check_reports_parked_and_stuck_entitlement_events(): void
    {
        $team = $this->company();
        $this->entitlements()->activate($team, 'forms', $team->owner);

        $found = app(OpsCheck::class)->run();
        $this->assertSame([0, 0], [$found['outbox_entitlements_parked'], $found['outbox_entitlements_backlog']]);

        DB::table('entitlement_outbox')->update(['created_at' => now()->subHours(7)]);
        $this->assertSame(1, app(OpsCheck::class)->run()['outbox_entitlements_backlog']);

        DB::table('entitlement_outbox')->update(['failed_at' => now()]);
        $this->assertSame(1, app(OpsCheck::class)->run()['outbox_entitlements_parked']);
        $this->artisan('ops:check')->expectsOutputToContain('outbox_entitlements_parked');
    }
}

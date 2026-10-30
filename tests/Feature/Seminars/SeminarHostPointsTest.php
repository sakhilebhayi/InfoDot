<?php

namespace Tests\Feature\Seminars;

use App\Models\Seminar;
use App\Models\SeminarFeedback;
use App\Models\SeminarRegistration;
use App\Models\Team;
use App\Models\User;
use App\Services\Points\RewardOutbox;
use App\Services\Seminars\SeminarHostPoints;
use App\Services\Seminars\SeminarService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** R-13: a host earns Dot Points once for a seminar that finished well attended and well rated by people outside their companies. */
class SeminarHostPointsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'shared-secret-for-tests';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.dopemine.url' => 'https://dopemine.test', 'services.dopemine.secret' => self::SECRET, 'seminars.points.enabled' => true]);
    }

    private function person(string $name = 'Person'): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => Carbon::now()]);
    }

    /** A published seminar that ended `$endedHoursAgo` hours ago (it ran for an hour). */
    private function seminar(User $host, float $endedHoursAgo = 30, array $over = []): Seminar
    {
        return Seminar::create($over + [
            'host_id' => $host->id, 'title' => 'Pricing your first product', 'summary' => 'A practical hour.',
            'starts_at' => now()->subMinutes((int) (($endedHoursAgo + 1) * 60)), 'duration_minutes' => 60,
            'join_url' => 'https://meet.example.test/r', 'status' => Seminar::PUBLISHED,
        ]);
    }

    /** $n people who attended and rated with the given ratings (a rating of null means they attended and did not rate). @return list<User> */
    private function audience(Seminar $s, array $ratings): array
    {
        $people = [];
        foreach ($ratings as $rating) {
            $p = $this->person('Attendee '.(count($people) + 1));
            SeminarRegistration::create(['seminar_id' => $s->id, 'user_id' => $p->id, 'status' => 'registered', 'attended_at' => now()->subDay()]);
            if ($rating !== null) {
                SeminarFeedback::create(['seminar_id' => $s->id, 'user_id' => $p->id, 'rating' => $rating]);
            }
            $people[] = $p;
        }

        return $people;
    }

    private function check(): array
    {
        return app(SeminarHostPoints::class)->run();
    }

    /** @return list<array<string, mixed>> */
    private function outbox(): array
    {
        return DB::table('reward_outbox')->orderBy('id')->get()->map(fn ($r) => json_decode($r->payload, true))->all();
    }

    // ---- the happy path and the payload ----------------------------------------------------------------------------------------

    public function test_a_qualifying_seminar_queues_one_event_with_exactly_the_agreed_fields(): void
    {
        $host = $this->person('Host Person');
        $s = $this->seminar($host);
        $this->audience($s, [5, 4, 4]);

        $this->assertSame(['awarded' => 1, 'reversed' => 0, 'checked' => 1], $this->check());

        [$event] = $this->outbox();
        $this->assertSame(['contribution_ref', 'event_id', 'facts', 'occurred_at', 'type', 'user_uuid', 'visibility'], collect($event)->keys()->sort()->values()->all());
        $this->assertSame('infodot.seminar.hosted', $event['type']);
        $this->assertSame($host->uuid, $event['user_uuid']);
        $this->assertSame('seminar:'.$s->id, $event['contribution_ref']);
        $this->assertSame('public', $event['visibility']);
        $this->assertSame(['attendee_count' => 3, 'rating_count' => 3, 'average_rating' => 4.3], $event['facts']);
        $this->assertIsInt($event['facts']['attendee_count']);
        $this->assertIsInt($event['facts']['rating_count']);
        $this->assertIsFloat($event['facts']['average_rating']);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $event['event_id']);
        $this->assertNotNull($s->fresh()->host_points_event_id);
        $this->assertSame($event['event_id'], $s->fresh()->host_points_event_id);
    }

    public function test_it_is_sent_signed_through_the_same_client_and_carries_no_attendee_identity(): void
    {
        $host = $this->person('Host Person');
        $s = $this->seminar($host);
        $people = $this->audience($s, [5, 5, 5, 4]);
        $this->check();
        Http::fake(['dopemine.test/*' => Http::response(['status' => 'awarded'], 202)]);

        $this->assertSame(1, app(RewardOutbox::class)->flush());

        Http::assertSent(function (Request $request) use ($people) {
            $body = $request->body();
            $timestamp = $request->header('X-InfoDot-Timestamp')[0];

            foreach ($people as $p) {
                foreach ([$p->name, $p->email, $p->uuid, (string) $p->id] as $identity) {
                    if ($identity !== (string) $p->id && str_contains($body, $identity)) {
                        return false;
                    }
                }
            }

            return $request->url() === 'https://dopemine.test/api/ingest/contributions'
                && hash_equals('sha256='.hash_hmac('sha256', $timestamp.'.'.$body, self::SECRET), $request->header('X-InfoDot-Signature')[0])
                && json_decode($body, true)['facts'] === ['attendee_count' => 4, 'rating_count' => 4, 'average_rating' => 4.8]
                && ! str_contains($body, 'actor_uuid') && ! str_contains($body, 'tenant_uuid');
        });
    }

    public function test_the_event_is_queued_once_however_often_it_is_checked(): void
    {
        $s = $this->seminar($this->person());
        $this->audience($s, [5, 5, 5]);

        $this->check();
        $second = $this->check();

        $this->assertSame(['awarded' => 0, 'reversed' => 0, 'checked' => 0], $second);
        $this->assertCount(1, $this->outbox());
        $this->assertFalse(app(SeminarHostPoints::class)->award($s->fresh()), 'calling it directly changes nothing either');
        $this->assertCount(1, $this->outbox());
    }

    public function test_the_event_id_is_derived_from_the_seminar_so_a_replay_cannot_make_a_second_ledger_event(): void
    {
        $s = $this->seminar($this->person());
        $this->audience($s, [5, 5, 5]);
        $this->check();
        $first = $this->outbox()[0]['event_id'];

        $s->forceFill(['host_points_event_id' => null])->save(); // as if the record of the award were lost
        $this->check();

        $this->assertCount(1, $this->outbox(), 'the outbox ignores the same event id');
        $this->assertSame($first, $s->fresh()->host_points_event_id);
    }

    // ---- thresholds ---------------------------------------------------------------------------------------------------------------

    public function test_two_attendees_are_not_enough(): void
    {
        $s = $this->seminar($this->person());
        $this->audience($s, [5, 5]);

        $this->assertSame(0, $this->check()['awarded']);
        $this->assertSame([], $this->outbox());
    }

    public function test_two_ratings_are_not_enough_however_many_attended(): void
    {
        $s = $this->seminar($this->person());
        $this->audience($s, [5, 5, null, null, null]);

        $this->assertSame(0, $this->check()['awarded']);
    }

    public function test_an_average_of_three_point_nine_is_refused_and_four_point_zero_is_accepted(): void
    {
        $low = $this->seminar($this->person());
        $this->audience($low, [4, 4, 4, 4, 4, 4, 4, 4, 4, 3]);   // 3.9
        $this->assertSame(0, $this->check()['awarded']);

        $edge = $this->seminar($this->person());
        $this->audience($edge, [4, 4, 4]);                       // 4.0
        $this->assertSame(1, $this->check()['awarded']);
        $this->assertEquals(4.0, $this->outbox()[0]['facts']['average_rating']);
    }

    public function test_just_below_four_does_not_round_up_into_qualifying(): void
    {
        $points = app(SeminarHostPoints::class);

        $this->assertFalse($points->meetsThresholds(['attendees' => 60, 'ratings' => 60, 'average' => 3.97]), '3.97 would print as 4.0 but is below it');
        $this->assertTrue($points->meetsThresholds(['attendees' => 3, 'ratings' => 3, 'average' => 4.0]));
    }

    public function test_the_host_attending_their_own_seminar_counts_for_nothing(): void
    {
        $host = $this->person();
        $s = $this->seminar($host);
        $this->audience($s, [5, 5]);
        SeminarRegistration::create(['seminar_id' => $s->id, 'user_id' => $host->id, 'status' => 'registered', 'attended_at' => now()]);
        SeminarFeedback::create(['seminar_id' => $s->id, 'user_id' => $host->id, 'rating' => 5]);

        $this->assertSame(0, $this->check()['awarded']);
        $this->assertSame(2, app(SeminarHostPoints::class)->figures($s)['attendees']);
    }

    public function test_people_who_registered_but_did_not_attend_are_not_counted(): void
    {
        $s = $this->seminar($this->person());
        $this->audience($s, [5, 5, 5]);
        $absent = $this->person();
        SeminarRegistration::create(['seminar_id' => $s->id, 'user_id' => $absent->id, 'status' => 'registered']);
        SeminarFeedback::create(['seminar_id' => $s->id, 'user_id' => $absent->id, 'rating' => 1]);

        $f = app(SeminarHostPoints::class)->figures($s);

        $this->assertSame([3, 3, 5.0], [$f['attendees'], $f['ratings'], $f['average']]);
    }

    public function test_paid_seminars_count_too(): void
    {
        $s = $this->seminar($this->person(), 30, ['price_cents' => 20000, 'currency' => 'ZAR']);
        $this->audience($s, [5, 4, 5]);

        $this->assertSame(1, $this->check()['awarded']);
    }

    // ---- the colleagues rule -------------------------------------------------------------------------------------------------------------

    private function company(User $host, User ...$members): Team
    {
        $team = Team::factory()->create(['user_id' => $host->id, 'personal_team' => false]);
        foreach ($members as $m) {
            $team->users()->attach($m->id, ['role' => 'member']);
        }

        return $team;
    }

    public function test_a_seminar_whose_only_attendees_are_the_hosts_teammates_does_not_qualify(): void
    {
        $host = $this->person('Host');
        $s = $this->seminar($host);
        $mates = $this->audience($s, [5, 5, 5, 5, 5]);
        $this->company($host, ...$mates);

        $f = app(SeminarHostPoints::class)->figures($s);

        $this->assertSame([0, 0, null, 5], [$f['attendees'], $f['ratings'], $f['average'], $f['colleagues']]);
        $this->assertSame(0, $this->check()['awarded']);
        $this->assertSame([], $this->outbox());
    }

    public function test_a_mixed_audience_counts_only_the_outsiders_and_their_ratings(): void
    {
        $host = $this->person('Host');
        $s = $this->seminar($host);
        $outsiders = $this->audience($s, [4, 4, 4]);
        $mates = $this->audience($s, [1, 1]);   // would drag the average down if counted
        $this->company($host, ...$mates);

        $this->assertSame(1, $this->check()['awarded']);
        $this->assertEquals(['attendee_count' => 3, 'rating_count' => 3, 'average_rating' => 4.0], $this->outbox()[0]['facts']);
        $this->assertCount(3, $outsiders);
    }

    public function test_colleagues_cannot_top_up_a_seminar_that_has_too_few_outsiders(): void
    {
        $host = $this->person('Host');
        $s = $this->seminar($host);
        $this->audience($s, [5, 5]);
        $mates = $this->audience($s, [5, 5, 5, 5]);
        $this->company($host, ...$mates);

        $this->assertSame(0, $this->check()['awarded']);
    }

    public function test_belonging_to_a_company_the_host_is_only_a_member_of_also_excludes_you(): void
    {
        $host = $this->person('Host');
        $boss = $this->person('Boss');
        $s = $this->seminar($host);
        $people = $this->audience($s, [5, 5, 5]);
        $this->company($boss, $host, $people[0], $people[1], $people[2]);   // the host belongs to somebody else's company

        $this->assertSame(0, app(SeminarHostPoints::class)->figures($s)['attendees']);

        $s2 = $this->seminar($host);
        $this->audience($s2, [5]);
        $this->audience($s2, [5, 5]);
        $this->assertSame(3, app(SeminarHostPoints::class)->figures($s2)['attendees'], 'people in no shared company still count');
    }

    public function test_a_company_the_host_has_nothing_to_do_with_does_not_exclude_anyone(): void
    {
        $host = $this->person('Host');
        $s = $this->seminar($host);
        $people = $this->audience($s, [5, 5, 5]);
        $this->company($people[0], $people[1], $people[2]);   // the attendees share a company with each other, not with the host

        $this->assertSame(3, app(SeminarHostPoints::class)->figures($s)['attendees']);
    }

    // ---- timing, status and the switch ---------------------------------------------------------------------------------------------------

    public function test_nothing_is_queued_until_a_day_after_the_seminar_ended(): void
    {
        $early = $this->seminar($this->person(), 23);
        $this->audience($early, [5, 5, 5]);
        $running = $this->seminar($this->person(), -1);   // still running
        $this->audience($running, [5, 5, 5]);

        $this->assertSame(0, $this->check()['awarded']);

        $this->travel(2)->hours();
        $this->assertSame(1, $this->check()['awarded'], 'the first one is old enough now; the second is not');
        $this->assertNull($running->fresh()->host_points_event_id);
    }

    public function test_a_late_rating_that_arrives_inside_the_wait_still_counts(): void
    {
        $s = $this->seminar($this->person(), 20);
        $people = $this->audience($s, [5, 5, null]);
        $this->assertSame(0, $this->check()['awarded']);

        SeminarFeedback::create(['seminar_id' => $s->id, 'user_id' => $people[2]->id, 'rating' => 4]);
        $this->travel(5)->hours();

        $this->assertSame(1, $this->check()['awarded']);
        $this->assertSame(3, $this->outbox()[0]['facts']['rating_count']);
    }

    public function test_cancelled_and_draft_seminars_never_earn(): void
    {
        $cancelled = $this->seminar($this->person(), 30, ['status' => Seminar::CANCELLED, 'cancelled_at' => now()->subDays(2)]);
        $this->audience($cancelled, [5, 5, 5]);
        $draft = $this->seminar($this->person(), 30, ['status' => Seminar::DRAFT]);
        $this->audience($draft, [5, 5, 5]);

        $this->assertSame(['awarded' => 0, 'reversed' => 0, 'checked' => 0], $this->check());
        $this->assertFalse(app(SeminarHostPoints::class)->award($cancelled));
        $this->assertSame([], $this->outbox());
    }

    public function test_the_switch_is_off_by_default_and_while_off_nothing_is_queued(): void
    {
        $s = $this->seminar($this->person());
        $this->audience($s, [5, 5, 5]);
        config(['seminars.points.enabled' => false]);

        $this->artisan('seminars:award-hosts')->expectsOutputToContain('switched off')->assertSuccessful();
        $this->assertFalse(app(SeminarHostPoints::class)->award($s));
        $this->assertSame([], $this->outbox());
        $this->assertNull($s->fresh()->host_points_event_id);
    }

    public function test_the_default_in_the_shipped_config_is_off(): void
    {
        $this->assertFalse((bool) (require base_path('config/seminars.php'))['points']['enabled']);
    }

    public function test_the_command_prints_counts_only(): void
    {
        $s = $this->seminar($this->person());
        $this->audience($s, [5, 5, 5]);

        $this->artisan('seminars:award-hosts')->expectsOutputToContain('Checked 1 seminars: 1 awards queued, 0 reversals queued.')->assertSuccessful();
        $this->artisan('seminars:award-hosts')->expectsOutputToContain('Checked 0 seminars: 0 awards queued')->assertSuccessful();
    }

    public function test_the_command_runs_every_thirty_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'seminars:award-hosts'));

        $this->assertNotNull($event);
        $this->assertSame('*/30 * * * *', $event->expression);
    }

    // ---- reversal -----------------------------------------------------------------------------------------------------------------------

    public function test_cancelling_after_an_award_was_queued_queues_the_existing_reversal_event_once(): void
    {
        $host = $this->person();
        $s = $this->seminar($host);
        $this->audience($s, [5, 5, 5]);
        $this->check();

        app(SeminarService::class)->cancel($s->fresh());
        app(SeminarService::class)->cancel($s->fresh());   // a second cancel does nothing
        $this->check();                                         // and neither does the scheduled check

        $events = $this->outbox();
        $this->assertCount(2, $events);
        $this->assertSame(['infodot.seminar.hosted', 'infodot.contribution.reward_reversed'], array_column($events, 'type'));
        $reversal = $events[1];
        $this->assertSame('seminar:'.$s->id, $reversal['contribution_ref']);
        $this->assertSame($host->uuid, $reversal['user_uuid']);
        $this->assertSame(['reason' => 'The seminar was cancelled.', 'rule_key' => 'seminar_hosted'], collect($reversal['facts'])->sortKeys()->all());
        $this->assertNotSame($events[0]['event_id'], $reversal['event_id']);
        $this->assertSame(['contribution_ref', 'event_id', 'facts', 'occurred_at', 'type', 'user_uuid', 'visibility'], collect($reversal)->keys()->sort()->values()->all());
        $this->assertNotNull($s->fresh()->host_points_reversed_at);
    }

    public function test_cancelling_a_seminar_that_never_earned_queues_nothing(): void
    {
        $s = $this->seminar($this->person(), -48);
        app(SeminarService::class)->cancel($s);

        $this->assertSame([], $this->outbox());
    }

    public function test_a_cancellation_by_another_route_is_caught_by_the_scheduled_check_and_a_cancelled_seminar_is_never_awarded_again(): void
    {
        $s = $this->seminar($this->person());
        $this->audience($s, [5, 5, 5]);
        $this->check();
        $s->forceFill(['status' => Seminar::CANCELLED, 'cancelled_at' => now()])->save();   // not through SeminarService

        $this->assertSame(1, $this->check()['reversed']);
        $this->assertSame(0, $this->check()['reversed']);
        $this->assertCount(2, $this->outbox());
    }

    public function test_the_reversal_waits_behind_the_award_in_the_outbox(): void
    {
        $s = $this->seminar($this->person());
        $this->audience($s, [5, 5, 5]);
        $this->check();
        app(SeminarService::class)->cancel($s->fresh());
        Http::fake(['dopemine.test/*' => Http::sequence()->push(['status' => 'awarded'], 202)->push(['status' => 'reversed'], 202)]);

        app(RewardOutbox::class)->flush();

        $types = [];
        Http::assertSentCount(2);
        Http::assertSent(function (Request $r) use (&$types) {
            $types[] = json_decode($r->body(), true)['type'];

            return true;
        });
        $this->assertSame(['infodot.seminar.hosted', 'infodot.contribution.reward_reversed'], $types);
    }

    // ---- what the host is told -----------------------------------------------------------------------------------------------------------------

    public function test_the_host_sees_a_plain_sentence_with_counts_and_nobody_else_does(): void
    {
        $host = $this->person('Host');
        $s = $this->seminar($host);
        $this->audience($s, [5, 5]);
        $mates = $this->audience($s, [5]);
        $this->company($host, ...$mates);

        $page = $this->actingAs($host)->get('/seminars/'.$s->id)->assertOk();
        $page->assertSee('This seminar did not qualify: 2 people from outside your companies attended and 2 of them rated it, averaging 5.0.', false);
        $page->assertSee('1 attendee from your own companies is not counted.', false);
        $page->assertDontSee('Attendee 3', false);   // the sentence names no one (the people table lists registrations for the host as before)
        $this->actingAs($this->person())->get('/seminars/'.$s->id)->assertOk()->assertDontSee('Dot Points for hosting');
    }

    public function test_the_sentence_says_why_not_yet_and_then_that_it_qualified(): void
    {
        $host = $this->person('Host');
        $points = app(SeminarHostPoints::class);

        $upcoming = $this->seminar($host, -48);
        $this->assertStringContainsString('We count after it has finished', $points->sentence($upcoming));

        $waiting = $this->seminar($host, 5);
        $this->audience($waiting, [5, 5, 5]);
        $this->assertStringStartsWith('Not counted yet: ratings stay open until', $points->sentence($waiting));
        $this->assertStringContainsString('That would qualify.', $points->sentence($waiting));

        $due = $this->seminar($host, 30);
        $this->audience($due, [5, 5, 5]);
        $this->assertStringContainsString('will be sent shortly', $points->sentence($due));
        $this->check();
        $this->assertStringContainsString('This seminar qualified: 3 people from outside your companies attended and 3 of them rated it, averaging 5.0. Your Dot Points for hosting it were sent.', $points->sentence($due->fresh()));

        $draft = $this->seminar($host, 30, ['status' => Seminar::DRAFT]);
        $this->assertNull($points->sentence($draft));
        $cancelled = $this->seminar($host, 30, ['status' => Seminar::CANCELLED]);
        $this->assertSame('Cancelled seminars do not earn Dot Points for hosting.', $points->sentence($cancelled));

        config(['seminars.points.enabled' => false]);
        $this->assertNull($points->sentence($due));
    }
}

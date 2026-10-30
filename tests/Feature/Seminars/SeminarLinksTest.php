<?php

namespace Tests\Feature\Seminars;

use App\Models\Seminar;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Seminars\MeetingLinkCheck;
use App\Services\Seminars\SeminarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** The meeting link a host types: checked before it is accepted, testable from the form, reportable by attendees; and a short InfoDot link that always opens. */
class SeminarLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['seminars.link_check.enabled' => true]);
        MeetingLinkCheck::$resolver = fn (string $host) => ['93.184.216.34'];
    }

    protected function tearDown(): void
    {
        MeetingLinkCheck::$resolver = null;
        parent::tearDown();
    }

    private function person(string $name = 'Person'): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => Carbon::now()]);
    }

    /** Http::fake keeps earlier fakes (the first match wins), so start from a clean client each time. */
    private function fake(array $map): void
    {
        Http::swap(new Factory);
        Http::fake($map);
    }

    private function check(string $url): array
    {
        return app(MeetingLinkCheck::class)->check($url);
    }

    private function seminar(string $url = 'https://meet.example.test/room', ?User $host = null): Seminar
    {
        return app(SeminarService::class)->save($host ?? $this->person('Host'), null, ['title' => 'Pricing', 'summary' => 'A practical hour.', 'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => $url], true);
    }

    public function test_a_link_must_be_a_plain_https_address(): void
    {
        $this->fake(['*' => Http::response('', 200)]);

        foreach (['', 'meet', 'http://meet.example.test/x', 'https://', 'https://localhost/x', 'https://192.168.1.5/x', 'https://user:pass@meet.example.test/x', 'https://meet.example.test:8443/x', 'ftp://x.example.test', 'not a link at all'] as $bad) {
            $this->assertSame('bad', $this->check($bad)['status'], "should refuse: $bad");
        }
        $this->assertSame('ok', $this->check('https://meet.example.test/room')['status']);
    }

    public function test_links_that_claim_to_be_a_known_service_must_have_that_services_shape(): void
    {
        $this->fake(['*' => Http::response('', 200)]);

        foreach (['https://meet.google.com/abc-defg-hij', 'https://meet.google.com/lookup/abc123', 'https://zoom.us/j/12345678901', 'https://us02web.zoom.us/j/123456789', 'https://teams.microsoft.com/l/meetup-join/19%3ameeting_abc/0', 'https://teams.live.com/meet/9312345678901', 'https://whereby.com/my-room', 'https://meet.jit.si/MyRoomName'] as $good) {
            $this->assertSame('ok', $this->check($good)['status'], "should accept: $good");
        }

        foreach (['https://meet.google.com/fake', 'https://meet.google.com/', 'https://meet.google.com/abc-def-ghi', 'https://zoom.us/j/abc', 'https://zoom.us/j/123', 'https://teams.microsoft.com/nothing', 'https://meet.jit.si/'] as $bad) {
            $r = $this->check($bad);
            $this->assertSame('bad', $r['status'], "should refuse: $bad");
            $this->assertStringContainsString('should look like', $r['message']);
        }
    }

    public function test_an_address_that_does_not_exist_or_is_private_is_refused_and_never_contacted(): void
    {
        $this->fake([]);
        MeetingLinkCheck::$resolver = fn () => [];
        $this->assertSame('bad', $this->check('https://no-such-site.example.test/room')['status']);

        Cache::flush();
        foreach (['127.0.0.1', '10.0.0.5', '192.168.1.10', '169.254.169.254', '::1'] as $ip) {
            MeetingLinkCheck::$resolver = fn () => [$ip];
            Cache::flush();
            $r = $this->check('https://sneaky.example.test/room');
            $this->assertSame('bad', $r['status'], $ip);
            $this->assertStringContainsString('private address', $r['message']);
        }
        Http::assertNothingSent();
    }

    public function test_what_the_page_answers_decides_ok_warn_or_bad(): void
    {
        foreach ([[200, 'ok'], [301, 'ok'], [404, 'bad'], [410, 'bad'], [403, 'warn'], [401, 'warn'], [429, 'warn'], [500, 'warn'], [503, 'warn']] as [$code, $expect]) {
            Cache::flush();
            $this->fake(['*' => Http::response('', $code === 301 ? 200 : $code)]);
            $this->assertSame($expect, $this->check('https://meet.example.test/'.$code)['status'], "HTTP $code");
        }
    }

    public function test_a_page_that_cannot_be_reached_is_a_warning_not_a_refusal(): void
    {
        $this->fake(['*' => fn () => throw new ConnectionException('timeout')]);

        $r = $this->check('https://meet.example.test/slow');
        $this->assertSame('warn', $r['status']);
        $this->assertStringContainsString('could not open', $r['message']);
    }

    public function test_redirects_are_followed_a_few_hops_and_never_into_private_space_or_off_https(): void
    {
        $this->fake(['a.example.test/*' => Http::response('', 302, ['Location' => 'https://b.example.test/x']), 'b.example.test/*' => Http::response('', 200)]);
        $this->assertSame('ok', $this->check('https://a.example.test/start')['status']);

        Cache::flush();
        $this->fake(['a.example.test/*' => Http::response('', 302, ['Location' => 'http://b.example.test/x'])]);
        $this->assertSame('warn', $this->check('https://a.example.test/start')['status'], 'will not follow onto plain http');

        Cache::flush();
        MeetingLinkCheck::$resolver = fn (string $h) => $h === 'inside.example.test' ? ['10.1.2.3'] : ['93.184.216.34'];
        $this->fake(['a.example.test/*' => Http::response('', 302, ['Location' => 'https://inside.example.test/x'])]);
        $this->assertSame('bad', $this->check('https://a.example.test/start')['status'], 'a redirect cannot lead into a private address');

        Cache::flush();
        MeetingLinkCheck::$resolver = fn () => ['93.184.216.34'];
        $this->fake(['*' => Http::response('', 302, ['Location' => '/again'])]);
        $this->assertSame('warn', $this->check('https://loop.example.test/start')['status'], 'a loop gives up');
    }

    public function test_the_request_is_pinned_to_the_address_that_was_checked(): void
    {
        $this->fake(['*' => Http::response('', 200)]);
        $this->check('https://meet.example.test/room');

        Http::assertSent(fn (Request $r) => $r->header('User-Agent')[0] === 'InfoDot-LinkCheck/1.0');
    }

    public function test_results_are_remembered_so_the_test_button_cannot_hammer_a_site(): void
    {
        $this->fake(['*' => Http::response('', 200)]);
        $this->check('https://meet.example.test/room');
        $this->check('https://meet.example.test/room');
        $this->check('https://meet.example.test/room');

        Http::assertSentCount(1);
    }

    public function test_a_clearly_broken_link_cannot_be_published_and_the_reason_is_plain(): void
    {
        $this->fake(['*' => Http::response('', 404)]);

        try {
            $this->seminar('https://meet.google.com/fakefake');
            $this->fail('a made-up Meet link must be refused');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('not a valid Google Meet link', $e->errors()['join_url'][0]);
        }
        try {
            $this->seminar('https://meet.example.test/gone');
            $this->fail('a 404 must be refused');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('does not exist', $e->errors()['join_url'][0]);
        }
        $this->assertSame(0, Seminar::count());
    }

    public function test_an_unconfirmed_link_is_published_with_its_status_recorded(): void
    {
        $this->fake(['*' => Http::response('', 403)]);
        $s = $this->seminar('https://meet.example.test/maybe');

        $this->assertSame('warn', $s->join_url_status);
        $this->assertNotNull($s->join_url_checked_at);

        Cache::flush();
        $this->fake(['*' => Http::response('', 200)]);
        $this->assertSame('ok', $this->seminar('https://meet.example.test/fine')->join_url_status);
    }

    public function test_the_link_is_only_rechecked_when_it_changes(): void
    {
        $this->fake(['*' => Http::response('', 200)]);
        $host = $this->person('Host');
        $s = $this->seminar('https://meet.example.test/room', $host);
        $sent = count(Http::recorded());

        app(SeminarService::class)->save($host, $s, ['title' => 'Renamed', 'summary' => 'A practical hour.', 'starts_at' => $s->starts_at->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/room'], true);

        $this->assertSame($sent, count(Http::recorded()));
    }

    public function test_the_form_button_calls_a_checker_that_needs_sign_in(): void
    {
        $this->fake(['*' => Http::response('', 404)]);
        $u = $this->person();

        $this->get('/seminars/link-check?url=https://x.example.test')->assertRedirect();
        $this->actingAs($u)->getJson('/seminars/link-check?url='.urlencode('https://meet.google.com/fake'))->assertOk()->assertJson(['status' => 'bad'])->assertJsonPath('service', 'meet');
        $this->actingAs($u)->getJson('/seminars/link-check?url='.urlencode('https://meet.example.test/ok'))->assertOk()->assertJsonStructure(['status', 'message', 'service']);
        $this->actingAs($u)->getJson('/seminars/link-check?url='.str_repeat('a', 600))->assertStatus(422);
        $this->actingAs($u)->get('/seminars/new')->assertOk()->assertSee('Test this link');
    }

    public function test_every_seminar_has_a_short_infodot_link_that_always_opens(): void
    {
        $this->fake(['*' => Http::response('', 200)]);
        $s = $this->seminar();
        $other = $this->seminar('https://meet.example.test/other');

        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $s->public_code);
        $this->assertNotSame($s->public_code, $other->public_code);
        $this->assertSame(url('/j/'.$s->public_code), $s->shareUrl());

        $this->get('/j/'.$s->public_code)->assertRedirect(route('seminars.show', $s));
        $this->get('/j/'.strtoupper($s->public_code))->assertRedirect(route('seminars.show', $s));
        $this->get('/j/zzzzzzzz')->assertNotFound();
        $this->get('/j/ab')->assertNotFound();

        $guestFollow = $this->followingRedirects()->get('/j/'.$s->public_code);
        $guestFollow->assertOk()->assertSee('Sign in', false);
    }

    public function test_a_draft_has_no_working_short_link(): void
    {
        $this->fake(['*' => Http::response('', 200)]);
        $draft = app(SeminarService::class)->save($this->person('Host'), null, ['title' => 'Draft', 'summary' => 'x', 'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/d'], false);

        $this->get('/j/'.$draft->public_code)->assertNotFound();
    }

    public function test_the_host_sees_the_share_link_and_attendees_do_not_see_the_meeting_link(): void
    {
        $this->fake(['*' => Http::response('', 200)]);
        $s = $this->seminar();

        $this->actingAs($s->host)->get('/seminars/'.$s->id)->assertOk()->assertSee($s->shareUrl(), false)->assertSee('Share this seminar');
        $page = $this->actingAs($this->person('Other'))->get('/seminars/'.$s->id)->assertOk()->getContent();
        $this->assertStringNotContainsString('meet.example.test', $page);
        $this->assertStringNotContainsString($s->shareUrl(), $page);
    }

    public function test_a_registered_person_can_report_a_link_that_did_not_open_and_the_host_is_told(): void
    {
        $this->fake(['*' => Http::response('', 200)]);
        $s = $this->seminar();
        $guest = $this->person('Guest');
        $stranger = $this->person('Stranger');
        app(SeminarService::class)->register($s, $guest);

        $this->actingAs($guest)->post('/seminars/'.$s->id.'/report-link')->assertSessionHasErrors('link');   // too early
        $this->travelTo($s->starts_at->copy()->subMinutes(5));
        $this->actingAs($stranger)->post('/seminars/'.$s->id.'/report-link')->assertSessionHasErrors('link');
        $this->actingAs($guest)->post('/seminars/'.$s->id.'/report-link')->assertSessionHas('seminar_notice');
        $this->actingAs($guest)->post('/seminars/'.$s->id.'/report-link');

        $this->assertSame(1, DB::table('seminar_link_reports')->count(), 'one report per person');
        $this->assertSame([UserNotice::SEMINAR_LINK_BROKEN], UserNotice::where('user_id', $s->host_id)->pluck('kind')->all(), 'the host is told once, not once per click');
        $this->actingAs($s->host)->get('/seminars/'.$s->id)->assertSee('1 person says your meeting link did not open');
        $this->actingAs($guest)->get('/seminars/'.$s->id)->assertSee('The link did not open');
    }

    public function test_fixing_the_link_clears_the_reports(): void
    {
        $this->fake(['*' => Http::response('', 200)]);
        $host = $this->person('Host');
        $s = $this->seminar('https://meet.example.test/old', $host);
        $guest = $this->person('Guest');
        app(SeminarService::class)->register($s, $guest);
        $this->travelTo($s->starts_at->copy()->subMinutes(5));
        $this->actingAs($guest)->post('/seminars/'.$s->id.'/report-link');
        $this->assertSame(1, app(SeminarService::class)->linkReports($s));

        $this->travelBack();
        app(SeminarService::class)->save($host, $s->fresh(), ['title' => $s->title, 'summary' => $s->summary, 'starts_at' => $s->starts_at->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/new'], true);

        $this->assertSame(0, app(SeminarService::class)->linkReports($s));
    }

    public function test_the_report_button_is_only_for_seminars_that_use_a_typed_link(): void
    {
        config(['seminars.video.enabled' => true, 'seminars.video.url' => 'wss://x.test', 'seminars.video.api_key' => 'k', 'seminars.video.api_secret' => 's']);
        $room = app(SeminarService::class)->save($this->person('Host'), null, ['title' => 'Room', 'summary' => 'x', 'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'delivery' => 'room'], true);
        $guest = $this->person('Guest');
        app(SeminarService::class)->register($room, $guest);
        $this->travelTo($room->starts_at->copy()->subMinutes(5));

        $this->actingAs($guest)->post('/seminars/'.$room->id.'/report-link')->assertSessionHasErrors('link');
    }

    public function test_a_link_seminar_can_be_published_from_the_form_while_small_rooms_are_on(): void
    {
        config(['seminars.p2p.enabled' => true]);
        $this->fake(['*' => Http::response('', 200)]);
        $host = $this->person('Host');

        // What the form posts for a link seminar when the small-room fields were never filled in: empty or zero values.
        $this->actingAs($host)->post('/seminars', [
            'title' => 'From the form', 'summary' => 'A practical hour.', 'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'duration_minutes' => 60,
            'delivery' => 'link', 'join_url' => 'https://meet.example.test/room', 'p2p_uplink_kbps' => '0', 'p2p_quality' => '', 'publish' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $s = Seminar::firstOrFail();
        $this->assertSame(['link', null, null], [$s->delivery, $s->p2p_quality, $s->p2p_uplink_kbps]);
    }
}

<?php

namespace Tests\Feature\Seminars;

use App\Models\Seminar;
use App\Models\SeminarRegistration;
use App\Models\User;
use App\Services\Seminars\LiveKit;
use App\Services\Seminars\SeminarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** The InfoDot seminar room against a LiveKit server: tokens, who may enter, the signed webhook, removing people. No live server is used. */
class SeminarVideoTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'APIkeyForTests';

    private const SECRET = 'a-livekit-secret-for-tests-0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        config(['seminars.video.enabled' => true, 'seminars.video.url' => 'wss://live.example.test', 'seminars.video.api_key' => self::KEY, 'seminars.video.api_secret' => self::SECRET]);
    }

    private function person(string $name = 'Person'): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => Carbon::now()]);
    }

    private function room(?User $host = null, array $over = []): Seminar
    {
        return app(SeminarService::class)->save($host ?? $this->person('Host'), null, array_merge([
            'title' => 'Pricing your first product', 'summary' => 'A practical hour.', 'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'delivery' => 'room',
        ], $over), true);
    }

    private function decode(string $jwt): array
    {
        [$h, $p, $s] = explode('.', $jwt);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $h.'.'.$p, self::SECRET, true)), '+/', '-_'), '=');
        $this->assertSame($expected, $s, 'signed with the LiveKit secret');

        return json_decode(base64_decode(strtr($p, '-_', '+/')), true);
    }

    private function webhook(array $body, ?string $secret = null, ?string $tamper = null): \Illuminate\Testing\TestResponse
    {
        $raw = json_encode($body);
        $b64 = fn ($x) => rtrim(strtr(base64_encode($x), '+/', '-_'), '=');
        $h = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $p = $b64(json_encode(['iss' => self::KEY, 'nbf' => time() - 5, 'exp' => time() + 300, 'sha256' => base64_encode(hash('sha256', $raw, true))]));
        $jwt = $h.'.'.$p.'.'.$b64(hash_hmac('sha256', $h.'.'.$p, $secret ?? self::SECRET, true));

        return $this->call('POST', '/api/seminars/livekit-webhook', [], [], [], ['CONTENT_TYPE' => 'application/webhook+json', 'HTTP_AUTHORIZATION' => $jwt], $tamper ?? $raw);
    }

    private function register(Seminar $s, User $u): SeminarRegistration
    {
        app(SeminarService::class)->register($s, $u);

        return SeminarRegistration::where('seminar_id', $s->id)->where('user_id', $u->id)->first();
    }

    public function test_a_room_needs_the_switch_and_a_host_without_a_link(): void
    {
        $s = $this->room();
        $this->assertSame(['room', null], [$s->delivery, $s->join_url]);

        config(['seminars.video.enabled' => false]);
        $this->expectException(ValidationException::class);
        $this->room();
    }

    public function test_a_link_seminar_still_needs_a_link(): void
    {
        $this->expectException(ValidationException::class);
        $this->room(null, ['delivery' => 'link']);
    }

    public function test_the_host_token_can_publish_and_an_attendee_token_cannot(): void
    {
        $host = $this->person('Host');
        $s = $this->room($host);
        $guest = $this->person('Guest');
        $svc = app(LiveKit::class);

        $h = $this->decode($svc->joinToken($s, $host)['token']);
        $g = $this->decode($svc->joinToken($s, $guest)['token']);

        $this->assertSame(['seminar-'.$s->id, true, true], [$h['video']['room'], $h['video']['canPublish'], $h['video']['roomJoin']]);
        $this->assertSame([false, true, true], [$g['video']['canPublish'], $g['video']['canPublishData'], $g['video']['canSubscribe']]);
        $this->assertSame('u'.$guest->id, $g['sub']);
        $this->assertSame(self::KEY, $g['iss']);
        $this->assertGreaterThan($s->endsAt()->timestamp, $g['exp'], 'valid until the seminar ends plus a little');
        $this->assertLessThanOrEqual($s->endsAt()->timestamp + 15 * 60, $g['exp']);
        $this->assertSame('wss://live.example.test', $svc->joinToken($s, $guest)['url']);
    }

    public function test_only_the_registered_inside_the_window_get_a_token_and_it_is_never_cached(): void
    {
        $s = $this->room();
        $guest = $this->person('Guest');
        $stranger = $this->person('Stranger');
        $this->register($s, $guest);

        $this->actingAs($guest)->post('/seminars/'.$s->id.'/room/token')->assertForbidden()->assertJsonFragment(['error' => 'The room opens 15 minutes before the start.']);

        $this->travelTo($s->starts_at->copy()->subMinutes(5));
        $ok = $this->actingAs($guest)->post('/seminars/'.$s->id.'/room/token')->assertOk();
        $this->assertStringContainsString('no-store', $ok->headers->get('Cache-Control'));
        $this->assertNotEmpty($ok->json('token'));
        $this->actingAs($stranger)->post('/seminars/'.$s->id.'/room/token')->assertForbidden();
        $this->actingAs($s->host)->post('/seminars/'.$s->id.'/room/token')->assertOk();

        $this->travelTo($s->endsAt()->addMinute());
        $this->actingAs($guest)->post('/seminars/'.$s->id.'/room/token')->assertForbidden();
    }

    public function test_the_room_page_and_join_route_follow_the_same_rules(): void
    {
        $s = $this->room();
        $guest = $this->person('Guest');
        $this->register($s, $guest);
        $this->travelTo($s->starts_at->copy()->subMinutes(5));

        $this->actingAs($guest)->get('/seminars/'.$s->id.'/join')->assertRedirect(route('seminars.room', $s));
        $this->actingAs($guest)->get('/seminars/'.$s->id.'/room')->assertOk()->assertSee('seminar-room', false)->assertSee('Live from the host');
        $this->actingAs($this->person('Stranger'))->get('/seminars/'.$s->id.'/room')->assertRedirect(route('seminars.show', $s));
        $this->actingAs($s->host)->get('/seminars/'.$s->id.'/room')->assertOk()->assertSee('You are the host');
    }

    public function test_it_is_a_404_while_video_is_off(): void
    {
        $s = $this->room();
        config(['seminars.video.enabled' => false]);

        $this->actingAs($s->host)->get('/seminars/'.$s->id.'/room')->assertNotFound();
        $this->actingAs($s->host)->post('/seminars/'.$s->id.'/room/token')->assertNotFound();
        $this->webhook(['event' => 'participant_joined'])->assertUnauthorized();
    }

    public function test_a_paid_seminar_room_is_only_for_people_who_have_paid(): void
    {
        $s = $this->room();
        $buyer = $this->person('Buyer');
        SeminarRegistration::create(['seminar_id' => $s->id, 'user_id' => $buyer->id, 'status' => SeminarRegistration::PENDING_PAYMENT, 'payment_status' => 'pending']);
        $this->travelTo($s->starts_at->copy()->subMinutes(5));

        $this->actingAs($buyer)->post('/seminars/'.$s->id.'/room/token')->assertForbidden();
    }

    public function test_a_signed_webhook_records_that_a_registered_person_really_joined(): void
    {
        $s = $this->room();
        $guest = $this->person('Guest');
        $reg = $this->register($s, $guest);
        $event = ['event' => 'participant_joined', 'room' => ['name' => 'seminar-'.$s->id], 'participant' => ['identity' => 'u'.$guest->id]];

        $this->webhook($event)->assertOk()->assertJson(['status' => 'recorded']);
        $first = $reg->fresh()->attended_at;
        $this->assertNotNull($first);
        $this->webhook($event)->assertOk()->assertJson(['status' => 'ignored']);
        $this->assertEquals($first, $reg->fresh()->attended_at, 'attendance is set once');
    }

    public function test_the_webhook_ignores_the_host_strangers_other_events_and_odd_names(): void
    {
        $s = $this->room();
        $stranger = $this->person('Stranger');
        $room = ['name' => 'seminar-'.$s->id];

        $this->webhook(['event' => 'participant_joined', 'room' => $room, 'participant' => ['identity' => 'u'.$s->host_id]])->assertJson(['status' => 'ignored']);
        $this->webhook(['event' => 'participant_joined', 'room' => $room, 'participant' => ['identity' => 'u'.$stranger->id]])->assertJson(['status' => 'ignored']);
        $this->webhook(['event' => 'participant_left', 'room' => $room, 'participant' => ['identity' => 'u1']])->assertJson(['status' => 'ignored']);
        $this->webhook(['event' => 'participant_joined', 'room' => ['name' => 'other'], 'participant' => ['identity' => 'x']])->assertJson(['status' => 'ignored']);
        $this->assertSame(0, SeminarRegistration::whereNotNull('attended_at')->count());
    }

    public function test_a_forged_or_altered_webhook_is_refused(): void
    {
        $s = $this->room();
        $guest = $this->person('Guest');
        $reg = $this->register($s, $guest);
        $event = ['event' => 'participant_joined', 'room' => ['name' => 'seminar-'.$s->id], 'participant' => ['identity' => 'u'.$guest->id]];

        $this->webhook($event, 'wrong-secret')->assertUnauthorized();
        $this->webhook($event, null, json_encode($event + ['extra' => 1]))->assertUnauthorized();   // body changed after signing
        $this->call('POST', '/api/seminars/livekit-webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($event))->assertUnauthorized();
        $this->assertNull($reg->fresh()->attended_at);
    }

    public function test_the_host_removes_a_person_through_the_room_server_and_nobody_else_can(): void
    {
        $s = $this->room();
        $guest = $this->person('Guest');
        $this->register($s, $guest);
        Http::fake(['live.example.test/*' => Http::response([])]);

        $this->actingAs($guest)->post('/seminars/'.$s->id.'/room/remove/'.$guest->id)->assertForbidden();
        $this->actingAs($s->host)->post('/seminars/'.$s->id.'/room/remove/'.$guest->id)->assertRedirect();

        Http::assertSent(function (Request $r) use ($s, $guest) {
            $claims = $this->decode(str_replace('Bearer ', '', $r->header('Authorization')[0]));

            return $r->url() === 'https://live.example.test/twirp/livekit.RoomService/RemoveParticipant'
                && $r['room'] === 'seminar-'.$s->id && $r['identity'] === 'u'.$guest->id && $claims['video']['roomAdmin'] === true && $claims['video']['room'] === 'seminar-'.$s->id;
        });
        $this->assertSame('registered', SeminarRegistration::where('user_id', $guest->id)->value('status'), 'they stay registered');
    }

    public function test_a_room_server_failure_is_reported_not_hidden(): void
    {
        $s = $this->room();
        $guest = $this->person('Guest');
        $this->register($s, $guest);
        Http::fake(['live.example.test/*' => Http::response([], 500)]);

        $this->actingAs($s->host)->post('/seminars/'.$s->id.'/room/remove/'.$guest->id)->assertSessionHasErrors('room');
    }

    public function test_the_seminar_page_offers_the_room_and_never_prints_a_token_or_the_secret(): void
    {
        $s = $this->room();
        $guest = $this->person('Guest');
        $this->register($s, $guest);
        $this->travelTo($s->starts_at->copy()->subMinutes(5));

        $page = $this->actingAs($guest)->get('/seminars/'.$s->id)->assertOk()->assertSee('Enter the room')->getContent();
        $this->assertStringNotContainsString(self::SECRET, $page);
        $room = $this->actingAs($guest)->get('/seminars/'.$s->id.'/room')->getContent();
        $this->assertStringNotContainsString(self::SECRET, $room);
        $this->assertStringNotContainsString('eyJ', $room, 'no token in the page; it is fetched when connecting');
    }

    public function test_the_form_offers_a_room_only_when_video_is_on(): void
    {
        $host = $this->person('Host');

        $this->actingAs($host)->get('/seminars/new')->assertOk()->assertSee('InfoDot room');
        config(['seminars.video.enabled' => false]);
        $this->actingAs($host)->get('/seminars/new')->assertOk()->assertDontSee('InfoDot room (video');
    }
}

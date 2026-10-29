<?php

namespace Tests\Feature\Seminars;

use App\Models\P2pPeer;
use App\Models\Seminar;
use App\Models\SeminarRegistration;
use App\Models\User;
use App\Services\Seminars\P2pCapacity;
use App\Services\Seminars\SeminarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Small peer-to-peer rooms: the capacity the host is told, who feeds whom, the connection messages, chat, and who may be in the room. */
class SeminarP2pTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['seminars.p2p.enabled' => true]);
    }

    private function person(string $name = 'Person'): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => Carbon::now()]);
    }

    private function room(array $over = [], ?User $host = null): Seminar
    {
        return app(SeminarService::class)->save($host ?? $this->person('Host'), null, array_merge([
            'title' => 'Pricing your first product', 'summary' => 'A practical hour.', 'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'duration_minutes' => 60,
            'delivery' => 'p2p', 'p2p_quality' => 'low', 'p2p_uplink_kbps' => 5000,
        ], $over), true);
    }

    /** A medium-quality room whose host can feed exactly one viewer, with plenty of places so the tree (not the cap) is what is tested. */
    private function treeRoom(): Seminar
    {
        config(['seminars.p2p.relay_slots' => 2]);
        $s = $this->room(['p2p_quality' => 'medium', 'p2p_uplink_kbps' => 2000]);
        $s->forceFill(['capacity' => 50])->save();
        $this->travelTo($s->starts_at->copy()->subMinutes(5));

        return $s;
    }

    private function viewer(Seminar $s, string $name = 'V'): User
    {
        $u = $this->person($name);
        app(SeminarService::class)->register($s, $u);

        return $u;
    }

    private function join(Seminar $s, User $u, bool $relay = false): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($u)->postJson('/seminars/'.$s->id.'/p2p/join', ['can_relay' => $relay]);
    }

    private function ready(Seminar $s, User $u): void
    {
        $this->actingAs($u)->postJson('/seminars/'.$s->id.'/p2p/ready')->assertOk();
    }

    public function test_the_capacity_the_host_is_told_follows_their_upload_and_quality(): void
    {
        $low = P2pCapacity::plan(5000, 'low');
        $this->assertSame([6, 47, 28, 4, true], [$low['direct'], $low['estimate'], $low['capacity'], $low['depth'], $low['usable']]);

        $audio = P2pCapacity::plan(5000, 'audio');
        $this->assertSame([30, 200, 200], [$audio['direct'], $audio['estimate'], $audio['capacity']], 'audio holds the most, up to the configured ceiling');

        $this->assertFalse(P2pCapacity::plan(300, 'low')['usable'], 'too slow for video');
        $this->assertTrue(P2pCapacity::plan(300, 'audio')['usable']);
        $this->assertSame(1, P2pCapacity::plan(5000, 'medium')['capacity']);
        $this->assertFalse(P2pCapacity::plan(5000, 'nonsense')['usable']);
        $this->assertGreaterThan(P2pCapacity::plan(2000, 'low')['capacity'], P2pCapacity::plan(8000, 'low')['capacity']);
    }

    public function test_the_seminar_gets_exactly_the_capacity_the_host_was_shown(): void
    {
        $s = $this->room();
        $this->assertSame([28, 'p2p', 'low', 5000], [$s->capacity, $s->delivery, $s->p2p_quality, $s->p2p_uplink_kbps]);
        $this->assertNull($s->join_url);

        $smaller = $this->room(['capacity' => 10]);
        $this->assertSame(10, $smaller->capacity, 'a host can only ask for fewer');
        $bigger = $this->room(['capacity' => 500]);
        $this->assertSame(28, $bigger->capacity, 'never more than the connection allows');
    }

    public function test_a_small_room_needs_a_connection_test_a_fast_enough_connection_and_the_switch(): void
    {
        foreach ([['p2p_uplink_kbps' => null], ['p2p_quality' => null], ['p2p_uplink_kbps' => 300]] as $bad) {
            try {
                $this->room($bad);
                $this->fail('should have been refused');
            } catch (ValidationException $e) {
                $this->assertTrue($e->errors() !== []);
            }
        }

        config(['seminars.p2p.enabled' => false]);
        $this->expectException(ValidationException::class);
        $this->room();
    }

    public function test_the_connection_test_and_plan_endpoints(): void
    {
        $u = $this->person();
        config(['seminars.p2p.speedtest_max_bytes' => 200_000]);

        $this->actingAs($u)->call('POST', '/seminars/p2p/speedtest', [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], str_repeat('x', 100_000))->assertOk()->assertJson(['bytes' => 100_000]);
        $this->actingAs($u)->call('POST', '/seminars/p2p/speedtest', [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], 'tiny')->assertStatus(422);
        $this->actingAs($u)->call('POST', '/seminars/p2p/speedtest', [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], str_repeat('x', 250_000))->assertStatus(422);

        $plans = $this->actingAs($u)->getJson('/seminars/p2p/plan?kbps=5000')->assertOk()->json();
        $this->assertSame(28, $plans['low']['capacity']);
        $this->actingAs($u)->getJson('/seminars/p2p/plan?kbps=5')->assertStatus(422);

        config(['seminars.p2p.enabled' => false]);
        $this->actingAs($u)->getJson('/seminars/p2p/plan?kbps=5000')->assertNotFound();
    }

    public function test_only_the_host_and_registered_people_inside_the_window_can_use_the_room(): void
    {
        $s = $this->room();
        $guest = $this->viewer($s, 'Guest');
        $stranger = $this->person('Stranger');

        $this->join($s, $guest)->assertForbidden();                                   // too early
        $this->travelTo($s->starts_at->copy()->subMinutes(5));
        $this->join($s, $guest)->assertOk();
        $this->join($s, $stranger)->assertForbidden();
        $this->join($s, $s->host)->assertOk()->assertJson(['role' => 'host']);
        $this->actingAs($stranger)->getJson('/seminars/'.$s->id.'/p2p/poll')->assertForbidden();

        $this->travelTo($s->endsAt()->addMinute());
        $this->actingAs($guest)->getJson('/seminars/'.$s->id.'/p2p/poll')->assertForbidden();
    }

    public function test_it_is_a_404_when_switched_off_and_a_403_for_other_kinds_of_seminar(): void
    {
        $s = $this->room();
        $this->travelTo($s->starts_at->copy()->subMinutes(5));
        $link = app(SeminarService::class)->save($s->host, null, ['title' => 'Link', 'summary' => 'x', 'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/x'], true);

        $this->join($link, $link->host)->assertForbidden();
        config(['seminars.p2p.enabled' => false]);
        $this->join($s, $s->host)->assertNotFound();
        $this->actingAs($s->host)->get('/seminars/'.$s->id.'/small-room')->assertNotFound();
    }

    public function test_the_host_feeds_the_first_viewers_and_viewers_who_can_relay_feed_the_next(): void
    {
        $s = $this->treeRoom();
        $host = $this->join($s, $s->host)->json();
        [$v1, $v2, $v3, $v4] = [$this->viewer($s, 'V1'), $this->viewer($s, 'V2'), $this->viewer($s, 'V3'), $this->viewer($s, 'V4')];

        $r1 = $this->join($s, $v1, true)->json();
        $this->assertSame($host['peer_id'], $r1['parent'], 'the host has one direct place and V1 takes it');
        $this->assertNull($this->join($s, $v2, true)->json()['parent'], 'V1 is not feeding anyone yet: only a viewer whose video is flowing can feed others');

        $this->ready($s, $v1);
        $this->assertSame($r1['peer_id'], $this->actingAs($v2)->getJson('/seminars/'.$s->id.'/p2p/poll')->json('parent'), 'V2 is given V1 at its next poll');
        $this->assertSame($r1['peer_id'], $this->join($s, $v3)->json('parent'));
        $this->assertNull($this->join($s, $v4)->json('parent'), 'V1 feeds two; nobody else can feed V4 yet');

        $this->ready($s, $v2);   // V2 asked to relay, so once its video flows V4 is given to it
        $this->assertSame(P2pPeer::where('user_id', $v2->id)->value('peer_id'), $this->actingAs($v4)->getJson('/seminars/'.$s->id.'/p2p/poll')->json('parent'));
        $this->assertSame(3, P2pPeer::where('user_id', $v4->id)->value('depth'), 'host (0) feeds V1 (1) feeds V2 (2) feeds V4 (3)');
    }

    public function test_depth_is_recorded_and_nobody_is_ever_their_own_feeder(): void
    {
        $s = $this->treeRoom();
        $this->join($s, $s->host);
        $v1 = $this->viewer($s);
        $v2 = $this->viewer($s);
        $this->join($s, $v1, true);
        $this->ready($s, $v1);
        $this->join($s, $v2, true);

        $this->assertSame([1, 2], [P2pPeer::where('user_id', $v1->id)->value('depth'), P2pPeer::where('user_id', $v2->id)->value('depth')]);
        $this->assertSame(0, P2pPeer::where('seminar_id', $s->id)->whereColumn('peer_id', 'parent_peer_id')->count());
    }

    public function test_when_a_feeder_leaves_the_people_it_fed_are_given_another(): void
    {
        $s = $this->treeRoom();
        $host = $this->join($s, $s->host)->json();
        $v1 = $this->viewer($s);
        $v2 = $this->viewer($s);
        $this->join($s, $v1, true);
        $this->ready($s, $v1);
        $r2 = $this->join($s, $v2)->json();
        $this->assertSame(P2pPeer::where('user_id', $v1->id)->value('peer_id'), $r2['parent']);

        $this->actingAs($v1)->postJson('/seminars/'.$s->id.'/p2p/leave')->assertOk();
        $poll = $this->actingAs($v2)->getJson('/seminars/'.$s->id.'/p2p/poll')->assertOk()->json();

        $this->assertSame($host['peer_id'], $poll['parent'], 'V1 left, the host has a free place again');
        $this->assertTrue($poll['reassigned']);
    }

    public function test_someone_who_stops_polling_is_dropped_and_their_viewers_move(): void
    {
        $s = $this->treeRoom();
        $host = $this->join($s, $s->host)->json();
        $v1 = $this->viewer($s);
        $v2 = $this->viewer($s);
        $this->join($s, $v1, true);
        $this->ready($s, $v1);
        $this->join($s, $v2);

        DB::table('p2p_peers')->where('user_id', $v1->id)->update(['last_seen' => now()->subMinute()]);
        $poll = $this->actingAs($v2)->getJson('/seminars/'.$s->id.'/p2p/poll')->json();

        $this->assertSame('gone', P2pPeer::where('user_id', $v1->id)->value('status'));
        $this->assertSame($host['peer_id'], $poll['parent']);
    }

    public function test_a_viewer_who_says_their_feed_is_failing_is_never_given_the_same_one_again(): void
    {
        $s = $this->treeRoom();
        $this->join($s, $s->host);
        $v1 = $this->viewer($s);
        $this->join($s, $v1);
        $hostPeer = P2pPeer::where('role', 'host')->value('peer_id');

        $r = $this->actingAs($v1)->postJson('/seminars/'.$s->id.'/p2p/report')->assertOk()->json();

        $this->assertNotSame($hostPeer, $r['parent']);
        $this->assertNull($r['parent'], 'no other feeder exists, so they wait rather than retry the failing one');
    }

    public function test_a_room_with_no_free_place_refuses_more_people_than_the_capacity(): void
    {
        $s = $this->room();
        $s->forceFill(['capacity' => 2])->save();
        $this->travelTo($s->starts_at->copy()->subMinutes(5));
        $this->join($s, $s->host);
        [$a, $b] = [$this->viewer($s, 'A'), $this->viewer($s, 'B')];
        $s->forceFill(['capacity' => 3])->save();
        $c = $this->viewer($s, 'C');
        $this->join($s, $a)->assertOk();
        $this->join($s, $b)->assertOk();
        $s->forceFill(['capacity' => 2])->save();

        $this->join($s, $c)->assertStatus(422)->assertJsonValidationErrors('room');
        $this->join($s, $a)->assertOk();   // someone already in can always reconnect
    }

    public function test_connection_messages_reach_only_their_addressee_once(): void
    {
        $s = $this->treeRoom();
        $host = $this->join($s, $s->host)->json();
        $v1 = $this->viewer($s);
        $r1 = $this->join($s, $v1)->json();
        $other = $this->room([], $this->person('Other host'));

        $this->actingAs($v1)->postJson('/seminars/'.$s->id.'/p2p/signal', ['to' => $host['peer_id'], 'type' => 'hello'])->assertOk();
        $this->actingAs($v1)->postJson('/seminars/'.$s->id.'/p2p/signal', ['to' => $host['peer_id'], 'type' => 'ice', 'payload' => '{"candidate":"x"}'])->assertOk();

        $hostPoll = $this->actingAs($s->host)->getJson('/seminars/'.$s->id.'/p2p/poll')->json();
        $this->assertSame(['hello', 'ice'], array_column($hostPoll['signals'], 'type'));
        $this->assertSame($r1['peer_id'], $hostPoll['signals'][0]['from']);
        $this->assertSame([], $this->actingAs($s->host)->getJson('/seminars/'.$s->id.'/p2p/poll')->json('signals'), 'delivered once');
        $this->assertSame([], $this->actingAs($v1)->getJson('/seminars/'.$s->id.'/p2p/poll')->json('signals'), 'not delivered to anyone else');
    }

    public function test_connection_messages_are_checked(): void
    {
        $s = $this->treeRoom();
        $host = $this->join($s, $s->host)->json();
        $v1 = $this->viewer($s);
        $r1 = $this->join($s, $v1)->json();
        $url = '/seminars/'.$s->id.'/p2p/signal';

        $this->actingAs($v1)->postJson($url, ['to' => $host['peer_id'], 'type' => 'exploit'])->assertStatus(422);
        $this->actingAs($v1)->postJson($url, ['to' => '00000000-0000-4000-8000-000000000000', 'type' => 'hello'])->assertStatus(422);
        $this->actingAs($v1)->postJson($url, ['to' => $r1['peer_id'], 'type' => 'hello'])->assertStatus(422);
        $this->actingAs($v1)->postJson($url, ['to' => $host['peer_id'], 'type' => 'offer', 'payload' => str_repeat('x', 20001)])->assertStatus(422);
        $this->actingAs($v1)->postJson($url, ['to' => 'not-a-uuid', 'type' => 'hello'])->assertStatus(422);
        $this->assertSame(0, DB::table('p2p_signals')->count());
    }

    public function test_the_host_removes_someone_who_is_then_disconnected_and_cannot_return(): void
    {
        $s = $this->treeRoom();
        $this->join($s, $s->host);
        $v1 = $this->viewer($s);
        $this->join($s, $v1);

        $this->actingAs($v1)->post('/seminars/'.$s->id.'/p2p/remove/'.$v1->id)->assertForbidden();
        $this->actingAs($s->host)->post('/seminars/'.$s->id.'/p2p/remove/'.$v1->id)->assertRedirect();

        $this->actingAs($v1)->getJson('/seminars/'.$s->id.'/p2p/poll')->assertOk()->assertJson(['removed' => true]);
        $this->join($s, $v1)->assertStatus(422);
        $this->assertSame('registered', SeminarRegistration::where('user_id', $v1->id)->value('status'), 'still registered');
    }

    public function test_chat_goes_to_everyone_in_the_room_and_is_limited(): void
    {
        $s = $this->treeRoom();
        $this->join($s, $s->host);
        $v1 = $this->viewer($s, 'Thandi');
        $this->join($s, $v1);

        $this->actingAs($v1)->postJson('/seminars/'.$s->id.'/p2p/chat', ['text' => 'Hello from Thandi'])->assertOk();
        $this->actingAs($v1)->postJson('/seminars/'.$s->id.'/p2p/chat', ['text' => str_repeat('a', 301)])->assertStatus(422);
        $this->actingAs($this->person('Stranger'))->postJson('/seminars/'.$s->id.'/p2p/chat', ['text' => 'hi'])->assertForbidden();

        $chat = $this->actingAs($s->host)->getJson('/seminars/'.$s->id.'/p2p/poll?chat=0')->json('chat');
        $this->assertSame([['Thandi', 'Hello from Thandi']], array_map(fn ($c) => [$c['name'], $c['body']], $chat));
        $this->assertSame([], $this->actingAs($s->host)->getJson('/seminars/'.$s->id.'/p2p/poll?chat='.$chat[0]['id'])->json('chat'));
    }

    public function test_connecting_counts_as_attending_and_the_host_is_not_counted(): void
    {
        $s = $this->treeRoom();
        $this->join($s, $s->host);
        $v1 = $this->viewer($s);

        $this->assertNull(SeminarRegistration::where('user_id', $v1->id)->value('attended_at'));
        $this->join($s, $v1);
        $this->assertNotNull(SeminarRegistration::where('user_id', $v1->id)->value('attended_at'));
        $this->assertSame(1, SeminarRegistration::whereNotNull('attended_at')->count());
    }

    public function test_the_host_is_told_the_number_everywhere_it_matters(): void
    {
        $s = $this->room();
        $this->actingAs($s->host)->get('/seminars/new')->assertOk()->assertSee('Small room')->assertSee('Test my connection');
        $this->actingAs($s->host)->get('/seminars/'.$s->id)->assertOk()->assertSee('Small room: up to 28 people');
        $this->actingAs($this->person())->get('/seminars')->assertOk()->assertSee('Small room · up to 28 people');
    }

    public function test_the_form_hides_the_option_while_it_is_off(): void
    {
        config(['seminars.p2p.enabled' => false]);
        $this->actingAs($this->person())->get('/seminars/new')->assertOk()->assertDontSee('Small room (video');
    }

    public function test_the_room_page_and_join_route(): void
    {
        $s = $this->treeRoom();
        $guest = $this->viewer($s, 'Guest');

        $this->actingAs($guest)->get('/seminars/'.$s->id.'/join')->assertRedirect(route('seminars.p2p', $s));
        $this->actingAs($guest)->get('/seminars/'.$s->id.'/small-room')->assertOk()->assertSee('p2p-room', false)->assertSee('Live from the host');
        $this->actingAs($s->host)->get('/seminars/'.$s->id.'/small-room')->assertOk()->assertSee('This room can hold up to');
        $this->actingAs($this->person())->get('/seminars/'.$s->id.'/small-room')->assertRedirect(route('seminars.show', $s));
    }

    public function test_connection_servers_come_from_config_and_a_relay_is_included_only_when_fully_set(): void
    {
        $s = $this->treeRoom();
        config(['seminars.p2p.stun_urls' => ['stun:stun.example.test:3478'], 'seminars.p2p.turn' => ['url' => 'turn:turn.example.test:3478', 'username' => null, 'credential' => 'secret']]);

        $ice = $this->join($s, $s->host)->json('ice');
        $this->assertSame([['urls' => ['stun:stun.example.test:3478']]], $ice, 'a half-set TURN server is left out');

        config(['seminars.p2p.turn' => ['url' => 'turn:turn.example.test:3478', 'username' => 'u', 'credential' => 'c']]);
        $this->assertCount(2, $this->actingAs($s->host)->postJson('/seminars/'.$s->id.'/p2p/join')->json('ice'));
    }
}

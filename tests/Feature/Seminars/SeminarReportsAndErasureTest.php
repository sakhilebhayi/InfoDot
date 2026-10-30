<?php

namespace Tests\Feature\Seminars;

use App\Actions\Jetstream\DeleteUser;
use App\Models\ModerationAction;
use App\Models\ReputationRingFlag;
use App\Models\ReputationSnapshot;
use App\Models\Seminar;
use App\Models\SeminarRegistration;
use App\Models\SeminarReport;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use App\Services\Seminars\SeminarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Reporting and removing a seminar, and what deleting an account does to everything the seminar features hold about the person. */
class SeminarReportsAndErasureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['licensing.billing.url' => 'https://billing.test', 'licensing.billing.secret' => 'platform-secret', 'seminars.paid.enabled' => true, 'seminars.paid.agreement_approved' => true, 'seminars.paid.callback_secret' => 'cb']);
        Http::fake(['billing.test/*' => Http::response(['status' => 'ok'])]);
    }

    private function person(string $name = 'Person'): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => Carbon::now()]);
    }

    private function moderator(): User
    {
        $m = $this->person('Moderator');
        $m->forceFill(['is_moderator' => true])->save();

        return $m;
    }

    private function seminar(User $host, int $days = 3): Seminar
    {
        return app(SeminarService::class)->save($host, null, ['title' => 'Pricing your first product', 'summary' => 'A practical hour.', 'description' => 'Bring your numbers.',
            'starts_at' => now()->addDays($days)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/room'], true);
    }

    private function paidTicket(Seminar $s, User $buyer): SeminarRegistration
    {
        return SeminarRegistration::create(['seminar_id' => $s->id, 'user_id' => $buyer->id, 'status' => 'registered', 'payment_status' => 'paid', 'ticket_ref' => 'tk_'.$buyer->id, 'amount_cents' => 20000, 'currency' => 'ZAR', 'paid_at' => now()]);
    }

    public function test_a_signed_in_person_can_report_once_and_the_host_cannot_report_their_own_seminar(): void
    {
        $host = $this->person('Host');
        $s = $this->seminar($host);
        $reader = $this->person('Reader');

        $this->actingAs($reader)->post(route('seminars.report', $s), ['reason' => 'spam', 'detail' => 'It sells something else.'])->assertRedirect();
        $this->actingAs($reader)->post(route('seminars.report', $s), ['reason' => 'harmful'])->assertRedirect();
        $this->assertSame(1, SeminarReport::where('seminar_id', $s->id)->count(), 'one report each');
        $this->assertSame('spam', SeminarReport::first()->reason);

        $this->actingAs($host)->post(route('seminars.report', $s), ['reason' => 'spam'])->assertSessionHasErrors('report');
        $this->actingAs($reader)->post(route('seminars.report', $s), ['reason' => 'nonsense'])->assertSessionHasErrors('reason');
        $this->post(route('logout'));
        $this->post(route('seminars.report', $s), ['reason' => 'spam'])->assertRedirect();
    }

    public function test_only_moderators_see_the_reports_or_can_remove(): void
    {
        $s = $this->seminar($this->person('Host'));
        $plain = $this->person('Plain');

        $this->actingAs($plain)->get('/moderation/seminars')->assertForbidden();
        $this->actingAs($plain)->post(route('moderation.seminars.remove', $s), ['reason' => 'Because I would like to.'])->assertForbidden();
        $this->assertNull($s->fresh()->removed_at);
        $this->actingAs($this->moderator())->get('/moderation/seminars')->assertOk()->assertSee('No open reports');
    }

    public function test_removing_cancels_refunds_tells_the_host_closes_reports_and_is_recorded(): void
    {
        $host = $this->person('Host');
        $s = $this->seminar($host);
        $guest = $this->person('Guest');
        app(SeminarService::class)->register($s, $guest);
        app(SeminarService::class)->report($s, $this->person('Reporter'), 'misleading', null);
        $mod = $this->moderator();

        $this->actingAs($mod)->get('/moderation/seminars')->assertOk()->assertSee('Pricing your first product')->assertSee('Misleading');
        $this->actingAs($mod)->post(route('moderation.seminars.remove', $s), ['reason' => 'Promises results it cannot deliver.'])->assertRedirect();
        $this->actingAs($mod)->post(route('moderation.seminars.remove', $s), ['reason' => 'short'])->assertSessionHasErrors('reason');

        $s->refresh();
        $this->assertSame('cancelled', $s->status);
        $this->assertNotNull($s->removed_at);
        $this->assertSame('actioned', SeminarReport::first()->status);
        $this->assertContains(UserNotice::SEMINAR_REMOVED, UserNotice::where('user_id', $host->id)->pluck('kind')->all());
        $this->assertContains(UserNotice::SEMINAR_CANCELLED, UserNotice::where('user_id', $guest->id)->pluck('kind')->all());
        $this->assertTrue(ModerationAction::where('action', ModerationAction::SEMINAR_REMOVED)->where('actor_id', $mod->id)->exists());
        $this->actingAs($host)->get(route('seminars.show', $s))->assertOk()->assertSee('A moderator removed this seminar')->assertSee('Promises results it cannot deliver.');
    }

    public function test_removal_refunds_a_paid_ticket_in_full(): void
    {
        $s = $this->seminar($this->person('Host'));
        $s->forceFill(['price_cents' => 20000, 'currency' => 'ZAR'])->save();
        $reg = $this->paidTicket($s, $this->person('Buyer'));

        app(SeminarService::class)->removeByModerator($s->fresh(), $this->moderator(), 'Breaks the community rules.');

        $this->assertNotNull($reg->fresh()->refund_requested_at);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'billing.test/api/seminar-tickets'));
    }

    public function test_a_moderator_can_dismiss_a_report_and_a_plain_member_cannot(): void
    {
        $s = $this->seminar($this->person('Host'));
        $r = app(SeminarService::class)->report($s, $this->person('Reporter'), 'other', 'Looks odd.');

        $this->actingAs($this->person('Plain'))->post(route('moderation.seminars.dismiss', $r), ['note' => 'Fine'])->assertForbidden();
        $this->actingAs($this->moderator())->post(route('moderation.seminars.dismiss', $r), ['note' => 'Nothing wrong with it.'])->assertRedirect();
        $this->assertSame('dismissed', $r->fresh()->status);
        $this->assertNull($s->fresh()->removed_at);
    }

    public function test_deleting_a_host_with_no_sales_removes_the_seminar_and_what_hangs_off_it(): void
    {
        $host = $this->person('Host');
        $s = $this->seminar($host);
        $guest = $this->person('Guest');
        app(SeminarService::class)->register($s, $guest);
        app(SeminarService::class)->report($s, $guest, 'other', null);
        DB::table('seminar_link_reports')->insert(['seminar_id' => $s->id, 'user_id' => $guest->id]);

        app(DeleteUser::class)->delete($host);

        $this->assertNull(User::find($host->id));
        $this->assertSame(0, Seminar::where('host_id', $host->id)->count());
        $this->assertSame(0, SeminarRegistration::where('seminar_id', $s->id)->count());
        $this->assertSame(0, SeminarReport::where('seminar_id', $s->id)->count());
        $this->assertSame(0, DB::table('seminar_link_reports')->where('seminar_id', $s->id)->count());
        $this->assertContains(UserNotice::SEMINAR_CANCELLED, UserNotice::where('user_id', $guest->id)->pluck('kind')->all(), 'the guest was told first');
    }

    public function test_deleting_a_host_who_sold_tickets_cancels_refunds_and_keeps_only_the_money_record(): void
    {
        $host = $this->person('Host Name');
        $s = $this->seminar($host);
        $s->forceFill(['price_cents' => 20000, 'currency' => 'ZAR'])->save();
        $buyer = $this->person('Buyer');
        $reg = $this->paidTicket($s, $buyer);
        DB::table('seminar_seller_profiles')->insert(['user_id' => $host->id, 'trading_name' => 'Host Trading', 'vat_number' => null, 'authorises_receipts' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('seminar_refund_lines')->insert(['registration_id' => $reg->id, 'seminar_id' => $s->id, 'host_id' => $host->id, 'amount_cents' => 20000, 'reason' => 'refund']);

        app(DeleteUser::class)->delete($host);

        $s->refresh();
        $this->assertSame('cancelled', $s->status);
        $this->assertNotNull($reg->fresh()->refund_requested_at, 'the ticket holder is refunded');
        $this->assertNull($s->description);
        $this->assertSame('', $s->join_url);
        $this->assertSame(1, DB::table('seminar_seller_profiles')->where('user_id', $host->id)->count(), 'kept because money moved');
        $this->actingAs($buyer)->get(route('seminars.show', $s))->assertOk()->assertSee('A former member')->assertDontSee('Host Name');
    }

    public function test_deleting_an_attendee_removes_their_free_registrations_notes_chat_and_reports_and_keeps_the_ticket(): void
    {
        $host = $this->person('Host');
        $s = $this->seminar($host);
        $me = $this->person('Me');
        app(SeminarService::class)->register($s, $me);
        DB::table('seminar_feedback')->insert(['seminar_id' => $s->id, 'user_id' => $me->id, 'rating' => 5, 'comment' => 'Loved it, call me on 0820000000']);
        DB::table('seminar_room_bans')->insert(['seminar_id' => $s->id, 'user_id' => $me->id]);
        DB::table('p2p_chat')->insert(['seminar_id' => $s->id, 'user_id' => $me->id, 'name' => 'Me', 'body' => 'hello']);
        $paid = $this->seminar($host, 5);
        $paid->forceFill(['price_cents' => 20000, 'currency' => 'ZAR'])->save();
        $this->paidTicket($paid, $me);

        app(DeleteUser::class)->delete($me);

        $this->assertSame(0, SeminarRegistration::where('seminar_id', $s->id)->where('user_id', $me->id)->count());
        $this->assertNull(DB::table('seminar_feedback')->where('user_id', $me->id)->value('comment'));
        $this->assertSame(0, DB::table('seminar_room_bans')->where('user_id', $me->id)->count());
        $this->assertSame(0, DB::table('p2p_chat')->where('user_id', $me->id)->count());
        $this->assertSame(1, SeminarRegistration::where('seminar_id', $paid->id)->count(), 'the paid ticket stays as a money record, refunded by the usual rule');
        $this->actingAs($host)->get(route('seminars.show', $s))->assertOk();
        $this->actingAs($host)->get(route('seminars.show', $paid))->assertOk();
    }

    public function test_deleting_a_person_removes_their_score_ring_flags_and_badges(): void
    {
        $me = $this->person('Me');
        $other = $this->person('Other');
        ReputationRingFlag::create(['kind' => 'cycle', 'member_ids' => [$me->id, $other->id], 'member_hash' => ReputationRingFlag::hashOf([$me->id, $other->id]), 'edge_count' => 2, 'internal_ratio' => 1.0, 'status' => 'open', 'detected_at' => now()]);
        ReputationSnapshot::create(['user_id' => $me->id, 'rules_version' => 1, 'total' => 10, 'tier' => 'Starter', 'dimensions' => [], 'computed_at' => now()]);

        app(DeleteUser::class)->delete($me);

        $this->assertSame(0, ReputationRingFlag::count());
        $this->assertSame(0, ReputationSnapshot::where('user_id', $me->id)->count());
    }

    public function test_a_notice_to_a_person_who_no_longer_exists_is_ignored(): void
    {
        $this->assertFalse(app(InAppNotices::class)->record(UserNotice::SEMINAR_REFUNDED, null, 'seminar', 1, 'x'));
    }
}

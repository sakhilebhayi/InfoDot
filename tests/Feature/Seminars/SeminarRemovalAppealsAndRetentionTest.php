<?php

namespace Tests\Feature\Seminars;

use App\Actions\Jetstream\DeleteUser;
use App\Models\ModerationAction;
use App\Models\Seminar;
use App\Models\SeminarRegistration;
use App\Models\SeminarRemovalAppeal;
use App\Models\SeminarReport;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Privacy\SeminarDataExporter;
use App\Services\Seminars\SeminarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** The refund choice when a moderator removes a seminar that has started, appeals and reinstating, reports about a person in a room, the data export, and the retention job. */
class SeminarRemovalAppealsAndRetentionTest extends TestCase
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

    private function moderator(string $name = 'Moderator'): User
    {
        $m = $this->person($name);
        $m->forceFill(['is_moderator' => true])->save();

        return $m;
    }

    private function seminar(User $host, int $days = 3): Seminar
    {
        $s = app(SeminarService::class)->save($host, null, ['title' => 'Pricing your first product', 'summary' => 'A practical hour.', 'description' => 'Bring your numbers.',
            'starts_at' => now()->addDays($days)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/room'], true);

        $s->forceFill(['price_cents' => 20000, 'currency' => 'ZAR'])->save();

        return $s->fresh();
    }

    private function started(Seminar $s): Seminar
    {
        $s->forceFill(['starts_at' => now()->subHours(3)])->save();

        return $s;
    }

    private function ticket(Seminar $s, User $buyer): SeminarRegistration
    {
        return SeminarRegistration::create(['seminar_id' => $s->id, 'user_id' => $buyer->id, 'status' => 'registered', 'payment_status' => 'paid', 'ticket_ref' => 'tk_'.$buyer->id, 'amount_cents' => 20000, 'currency' => 'ZAR', 'paid_at' => now()]);
    }

    // ---- who is refunded -------------------------------------------------------------------------------------------------

    public function test_before_the_start_everyone_is_refunded_whatever_the_form_says(): void
    {
        $s = $this->seminar($this->person('Host'));
        $reg = $this->ticket($s, $this->person('Buyer'));

        app(SeminarService::class)->removeByModerator($s, $this->moderator(), 'Promises results it cannot deliver.', 'none');

        $this->assertNotNull($reg->fresh()->refund_requested_at);
        $this->assertSame('all', $s->fresh()->removed_refunds);
    }

    /** @return array{0: Seminar, 1: SeminarRegistration, 2: SeminarRegistration} */
    private function startedWithTwoTickets(): array
    {
        $s = $this->seminar($this->person('Host'));
        $complainer = $this->person('Complainer');
        $quiet = $this->person('Quiet');
        $a = $this->ticket($s, $complainer);
        $b = $this->ticket($s, $quiet);
        app(SeminarService::class)->report($s, $complainer, 'misleading', null);
        $this->started($s);

        return [$s->fresh(), $a, $b];
    }

    public function test_after_the_start_the_moderator_can_refund_only_the_people_who_reported(): void
    {
        [$s, $a, $b] = $this->startedWithTwoTickets();

        app(SeminarService::class)->removeByModerator($s, $this->moderator(), 'Promises results it cannot deliver.', 'complainants');

        $this->assertNotNull($a->fresh()->refund_requested_at, 'the person who reported is refunded');
        $this->assertNull($b->fresh()->refund_requested_at, 'the person who did not is not');
        $this->assertSame('complainants', $s->fresh()->removed_refunds);
    }

    public function test_after_the_start_the_moderator_can_refund_no_one_or_everyone(): void
    {
        [$s, $a, $b] = $this->startedWithTwoTickets();
        app(SeminarService::class)->removeByModerator($s, $this->moderator(), 'Spam seminar, nothing was taught.', 'none');
        $this->assertNull($a->fresh()->refund_requested_at);
        $this->assertNull($b->fresh()->refund_requested_at);
        $this->assertSame('cancelled', $s->fresh()->status);

        [$s2, $c, $d] = $this->startedWithTwoTickets();
        app(SeminarService::class)->removeByModerator($s2, $this->moderator(), 'Spam seminar, nothing was taught.', 'all');
        $this->assertNotNull($c->fresh()->refund_requested_at);
        $this->assertNotNull($d->fresh()->refund_requested_at);
    }

    public function test_the_host_is_told_who_was_refunded(): void
    {
        [$s] = $this->startedWithTwoTickets();
        $host = User::find($s->host_id);
        app(SeminarService::class)->removeByModerator($s, $this->moderator(), 'Spam seminar, nothing was taught.', 'none');

        $page = $this->actingAs($host)->get('/notifications')->assertOk()->getContent();
        $this->assertStringContainsString('tickets were not refunded', $page);
        $this->actingAs($host)->get(route('seminars.show', $s))->assertOk()->assertSee('tickets were not refunded');
    }

    public function test_the_moderator_form_asks_who_is_refunded_only_once_it_has_started(): void
    {
        $s = $this->seminar($this->person('Host'));
        $mod = $this->moderator();
        $this->actingAs($mod)->get(route('seminars.show', $s))->assertOk()->assertDontSee('Who is refunded?');
        $this->started($s);
        $this->actingAs($mod)->get(route('seminars.show', $s))->assertOk()->assertSee('Who is refunded?');
    }

    // ---- appeals ---------------------------------------------------------------------------------------------------------

    private function removed(): array
    {
        $host = $this->person('Host');
        $s = $this->seminar($host);
        $guest = $this->person('Guest');
        app(SeminarService::class)->register($s->forceFill(['price_cents' => null, 'currency' => null]), $guest);
        $remover = $this->moderator('Remover');
        app(SeminarService::class)->removeByModerator($s->fresh(), $remover, 'Looks like a scam to us.');

        return [$s->fresh(), $host, $guest, $remover];
    }

    public function test_a_host_can_appeal_once_within_the_window_and_nobody_else_can(): void
    {
        [$s, $host] = $this->removed();

        $this->actingAs($this->person('Other'))->post(route('seminars.appeal', $s), ['message' => 'It is not my seminar to appeal.'])->assertSessionHasErrors('appeal');
        $this->actingAs($host)->post(route('seminars.appeal', $s), ['message' => 'This was a mistake: the topic is real and the link works.'])->assertSessionHasNoErrors();
        $this->actingAs($host)->post(route('seminars.appeal', $s), ['message' => 'Sending a second one anyway.'])->assertSessionHasErrors('appeal');
        $this->assertSame(1, SeminarRemovalAppeal::count());
        $this->actingAs($host)->get(route('seminars.show', $s))->assertOk()->assertSee('Your appeal was sent');
    }

    public function test_an_appeal_after_the_window_is_refused(): void
    {
        [$s, $host] = $this->removed();
        $s->forceFill(['removed_at' => now()->subDays(31)])->save();

        $this->actingAs($host)->post(route('seminars.appeal', $s), ['message' => 'It has been a while but I would like to appeal.'])->assertSessionHasErrors('appeal');
    }

    public function test_a_different_moderator_reinstates_before_the_start_and_everyone_is_told(): void
    {
        [$s, $host, $guest, $remover] = $this->removed();
        $appeal = app(SeminarService::class)->appealRemoval($s, $host, 'The topic is real and the link works.');
        $second = $this->moderator('Second');

        $this->actingAs($remover)->post(route('moderation.seminars.appeal', $appeal), ['decision' => 'reinstate', 'note' => 'I will not decide my own removal.'])->assertSessionHasErrors('appeal');
        $this->actingAs($second)->post(route('moderation.seminars.appeal', $appeal), ['decision' => 'reinstate', 'note' => 'The host showed the course is real.'])->assertSessionHasNoErrors();

        $s->refresh();
        $this->assertSame('published', $s->status);
        $this->assertNull($s->removed_at);
        $this->assertNull($s->removed_refunds);
        $this->assertSame('reinstated', $appeal->fresh()->status);
        $this->assertContains(UserNotice::SEMINAR_REINSTATED, UserNotice::where('user_id', $guest->id)->pluck('kind')->all());
        $this->assertContains(UserNotice::SEMINAR_APPEAL_DECIDED, UserNotice::where('user_id', $host->id)->pluck('kind')->all());
        $this->assertTrue(ModerationAction::where('action', ModerationAction::SEMINAR_REINSTATED)->where('actor_id', $second->id)->exists());
        $this->actingAs($host)->get(route('seminars.show', $s))->assertOk()->assertSee('put this seminar back on');
    }

    public function test_a_lone_moderator_may_decide_their_own_removal(): void
    {
        [$s, $host, , $remover] = $this->removed();
        $appeal = app(SeminarService::class)->appealRemoval($s, $host, 'The topic is real and the link works.');

        app(SeminarService::class)->decideRemovalAppeal($appeal, $remover, false, 'Stays removed, the reason stands.');

        $this->assertSame('denied', $appeal->fresh()->status);
        $this->assertNotNull($s->fresh()->removed_at);
        $this->assertTrue(ModerationAction::where('action', ModerationAction::SEMINAR_APPEAL_DENIED)->exists());
    }

    public function test_a_seminar_that_has_started_cannot_be_put_back_on(): void
    {
        [$s, $host] = $this->removed();
        $appeal = app(SeminarService::class)->appealRemoval($s, $host, 'The topic is real and the link works.');
        $this->started($s);

        $this->expectException(ValidationException::class);
        app(SeminarService::class)->decideRemovalAppeal($appeal, $this->moderator('Second'), true, 'It looked fine on a second read.');
    }

    // ---- a person in a room ----------------------------------------------------------------------------------------------

    public function test_a_registered_person_and_the_host_can_report_someone_but_a_stranger_cannot(): void
    {
        $host = $this->person('Host');
        $s = $this->seminar($host);
        $s->forceFill(['price_cents' => null, 'currency' => null])->save();
        $guest = $this->person('Guest');
        app(SeminarService::class)->register($s->fresh(), $guest);

        $this->actingAs($guest)->post(route('seminars.report', $s), ['reason' => 'harmful', 'about_name' => 'Troll Name', 'detail' => 'Abusive in chat'])->assertSessionHasNoErrors();
        $this->actingAs($host)->post(route('seminars.report', $s), ['reason' => 'harmful', 'about_name' => 'Troll Name'])->assertSessionHasNoErrors();
        $this->actingAs($guest)->post(route('seminars.report', $s), ['reason' => 'spam'])->assertSessionHasNoErrors();   // a report about the seminar itself still works
        $this->actingAs($this->person('Stranger'))->post(route('seminars.report', $s), ['reason' => 'harmful', 'about_name' => 'Troll Name'])->assertSessionHasErrors('report');

        $this->assertSame(3, SeminarReport::where('seminar_id', $s->id)->count());
        $this->assertSame(2, SeminarReport::where('seminar_id', $s->id)->whereNotNull('about_name')->count());
    }

    public function test_a_moderator_can_bar_a_named_registrant_from_the_room(): void
    {
        $s = $this->seminar($this->person('Host'));
        $s->forceFill(['price_cents' => null, 'currency' => null])->save();
        $troll = $this->person('Troll Name');
        $guest = $this->person('Guest');
        app(SeminarService::class)->register($s->fresh(), $troll);
        app(SeminarService::class)->register($s->fresh(), $guest);
        app(SeminarService::class)->report($s->fresh(), $guest, 'harmful', 'Abusive in chat', 'troll name');
        $mod = $this->moderator();

        $this->actingAs($mod)->get('/moderation/seminars')->assertOk()->assertSee('About a person: troll name')->assertSee('Bar Troll Name');
        $this->actingAs($mod)->post(route('moderation.seminars.bar', [$s, $troll->id]), ['note' => 'Abusive in chat, confirmed.'])->assertSessionHasNoErrors();

        $this->assertNotNull(app(SeminarService::class)->roomRefusal($s->fresh(), $troll));
        $this->assertTrue(ModerationAction::where('action', ModerationAction::SEMINAR_PERSON_BARRED)->exists());
        $this->actingAs($mod)->post(route('moderation.seminars.bar', [$s, $this->person('Not registered')->id]), ['note' => 'Should not work.'])->assertNotFound();
    }

    // ---- export ----------------------------------------------------------------------------------------------------------

    public function test_the_download_holds_the_persons_own_seminar_data_and_no_one_elses_names(): void
    {
        $host = $this->person('Host Hannah');
        $s = $this->seminar($host);
        $me = $this->person('Me Myself');
        $this->ticket($s, $me);
        DB::table('seminar_feedback')->insert(['seminar_id' => $s->id, 'user_id' => $me->id, 'rating' => 4, 'comment' => 'Useful hour']);
        DB::table('p2p_chat')->insert(['seminar_id' => $s->id, 'user_id' => $me->id, 'name' => 'Me Myself', 'body' => 'hello room']);
        DB::table('seminar_payout_accounts')->insert(['user_id' => $host->id, 'billing_account_id' => 9, 'bank_code' => '058', 'account_last4' => '4321', 'status' => 'active']);

        $mine = app(SeminarDataExporter::class)->export($me);
        $this->assertSame('Pricing your first product', $mine['registrations'][0]['seminar']);
        $this->assertSame('Useful hour', $mine['ratings_given'][0]['note']);
        $this->assertSame('hello room', $mine['room_chat_messages'][0]['message']);
        $this->assertStringNotContainsString('Host Hannah', json_encode($mine));

        $theirs = app(SeminarDataExporter::class)->export($host);
        $this->assertSame('4321', $theirs['payout_account'][0]->account_last4);
        $this->assertSame(4, $theirs['hosted_seminars'][0]['ratings_received'][0]['rating']);
        $this->assertStringNotContainsString('Me Myself', json_encode($theirs), 'a host gets ratings without who gave them');

        $this->actingAs($me)->get(route('seminars.export'))->assertOk()->assertHeader('content-disposition', 'attachment; filename=my-seminar-data.json');
        $this->get('/logout');
    }

    // ---- retention -------------------------------------------------------------------------------------------------------

    private function prune(bool $dry = false): array
    {
        return app(\App\Services\Privacy\SeminarRecordPruner::class)->run($dry);
    }

    public function test_room_data_goes_thirty_days_after_the_seminar_ended_and_not_before(): void
    {
        $old = $this->seminar($this->person('H1'));
        $recent = $this->seminar($this->person('H2'));
        $old->forceFill(['starts_at' => now()->subDays(40)])->save();
        $recent->forceFill(['starts_at' => now()->subDays(10)])->save();
        foreach ([$old, $recent] as $i => $sem) {
            $u = $this->person('V'.$i);
            DB::table('p2p_peers')->insert(['seminar_id' => $sem->id, 'user_id' => $u->id, 'peer_id' => 'peer-'.$i, 'role' => 'viewer', 'status' => 'gone']);
            DB::table('p2p_signals')->insert(['seminar_id' => $sem->id, 'from_peer' => 'peer-'.$i, 'to_peer' => 'other', 'type' => 'ice', 'payload' => '{}']);
            DB::table('p2p_chat')->insert(['seminar_id' => $sem->id, 'user_id' => $u->id, 'name' => 'V', 'body' => 'hi']);
            DB::table('seminar_room_bans')->insert(['seminar_id' => $sem->id, 'user_id' => $u->id]);
        }

        $this->assertSame(1, $this->prune(true)['room_peers'], 'a dry run only counts');
        $this->assertSame(2, DB::table('p2p_peers')->count());

        $this->prune();

        $this->assertSame([$recent->id], DB::table('p2p_peers')->pluck('seminar_id')->all());
        $this->assertSame(1, DB::table('p2p_chat')->count());
        $this->assertSame(1, DB::table('p2p_signals')->count());
        $this->assertSame(1, DB::table('seminar_room_bans')->count());
    }

    public function test_sent_points_rows_and_closed_reports_are_pruned_and_open_ones_are_not(): void
    {
        $row = fn (string $id, $sent) => DB::table('reward_outbox')->insert(['event_id' => $id, 'type' => 't', 'payload' => '{}', 'sent_at' => $sent, 'created_at' => now(), 'updated_at' => now()]);
        $row('00000000-0000-0000-0000-000000000001', now()->subDays(45));
        $row('00000000-0000-0000-0000-000000000002', now()->subDays(5));
        $row('00000000-0000-0000-0000-000000000003', null);
        $s = $this->seminar($this->person('H'));
        $mk = fn (string $status, $at) => SeminarReport::create(['seminar_id' => $s->id, 'reporter_id' => $this->person()->id, 'reason' => 'spam', 'status' => $status, 'resolved_at' => $at]);
        $mk('dismissed', now()->subMonths(13));
        $mk('dismissed', now()->subMonths(2));
        $mk('open', null);

        $this->prune();

        $this->assertSame(2, DB::table('reward_outbox')->count());
        $this->assertSame(2, SeminarReport::count());
        $this->assertSame(1, SeminarReport::where('status', 'open')->count());
    }

    public function test_money_records_of_deleted_accounts_go_after_five_years_but_not_for_people_who_still_have_one_or_when_a_dispute_is_open(): void
    {
        $host = $this->person('Host');
        $s = $this->seminar($host);
        $gone = $this->person('Gone');
        $stays = $this->person('Stays');
        $old = now()->subYears(6);
        $mk = fn (User $u, string $ref, array $extra = []) => SeminarRegistration::create(array_merge(['seminar_id' => $s->id, 'user_id' => $u->id, 'status' => 'cancelled', 'payment_status' => 'refunded', 'ticket_ref' => $ref, 'amount_cents' => 100, 'paid_at' => $old], $extra));
        $goneReg = $mk($gone, 'old_gone');
        $mk($stays, 'old_stays');
        $disputed = $mk($this->person('Disputer'), 'old_disputed', ['dispute_status' => 'open']);
        $recent = $mk($this->person('Recent'), 'recent', ['paid_at' => now()->subYear()]);
        DB::table('seminar_ticket_events')->insert(['event' => 'ticket.paid', 'ticket_ref' => 'old_gone', 'outcome' => 'ok', 'ref_key' => '']);
        DB::table('seminar_refund_lines')->insert(['registration_id' => $goneReg->id, 'seminar_id' => $s->id, 'host_id' => $host->id, 'amount_cents' => 100, 'reason' => 'refund']);
        foreach (['Gone', 'Disputer', 'Recent'] as $n) {
            DB::table('users')->where('name', $n)->delete();
        }

        $out = $this->prune();

        $refs = SeminarRegistration::pluck('ticket_ref')->sort()->values()->all();
        $this->assertSame(['old_disputed', 'old_stays', 'recent'], $refs, 'only the old ticket of a deleted account went');
        $this->assertSame(0, DB::table('seminar_ticket_events')->where('ticket_ref', 'old_gone')->count());
        $this->assertSame(0, DB::table('seminar_refund_lines')->where('registration_id', $goneReg->id)->count());
        $this->assertSame(1, $out['tickets']);
        $this->assertNotNull($disputed->fresh());
        $this->assertNotNull($recent->fresh());
    }

    public function test_old_invoices_and_leftover_host_records_of_a_deleted_host_go_when_nothing_else_needs_them(): void
    {
        $gone = 4242;
        $old = now()->subYears(6);
        DB::table('seminar_commission_invoices')->insert(['host_id' => $gone, 'period' => '2020-01', 'year' => 2020, 'seq' => 1, 'number' => 'INV-2020-0001', 'kind' => 'invoice', 'fee_cents' => 10, 'vat_cents' => 1, 'excl_vat_cents' => 9, 'lines' => '[]', 'issuer' => '[]', 'issued_at' => $old, 'created_at' => $old, 'updated_at' => $old]);
        DB::table('seminar_seller_profiles')->insert(['user_id' => $gone, 'trading_name' => 'Old Trading', 'created_at' => $old, 'updated_at' => $old]);
        DB::table('seminar_host_agreements')->insert(['user_id' => $gone, 'version' => 'v1', 'accepted_at' => $old]);

        $this->prune();

        $this->assertSame(0, DB::table('seminar_commission_invoices')->count());
        $this->assertSame(0, DB::table('seminar_seller_profiles')->count());
        $this->assertSame(0, DB::table('seminar_host_agreements')->count());
    }

    public function test_a_host_with_an_open_debt_keeps_the_records_and_the_job_can_be_switched_off(): void
    {
        $gone = 4343;
        $old = now()->subYears(6);
        DB::table('seminar_seller_profiles')->insert(['user_id' => $gone, 'trading_name' => 'Old Trading', 'created_at' => $old, 'updated_at' => $old]);
        DB::table('seminar_host_debts')->insert(['user_id' => $gone, 'billing_debt_id' => 1, 'amount_cents' => 100, 'remaining_cents' => 100, 'status' => 'open', 'opened_at' => $old, 'last_event_at' => $old, 'created_at' => $old, 'updated_at' => $old]);

        $this->prune();
        $this->assertSame(1, DB::table('seminar_seller_profiles')->count());

        config(['seminars.retention.money_years' => 0]);
        $this->assertArrayNotHasKey('tickets', $this->prune());
    }

    public function test_the_command_prints_counts_only_and_is_scheduled_daily(): void
    {
        $this->artisan('seminars:prune --dry')->assertSuccessful();
        $this->assertTrue(collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->contains(fn ($e) => str_contains($e->command, 'seminars:prune') && $e->expression === '50 3 * * *'));
    }

    public function test_deleting_a_host_deletes_their_removal_appeal(): void
    {
        [$s, $host] = $this->removed();
        app(SeminarService::class)->appealRemoval($s, $host, 'The topic is real and the link works.');
        DB::table('seminar_payout_accounts')->insert(['user_id' => $host->id, 'billing_account_id' => 9, 'bank_code' => '058', 'account_last4' => '4321', 'status' => 'active']);

        app(DeleteUser::class)->delete($host);

        $this->assertSame(0, SeminarRemovalAppeal::count());
    }
}

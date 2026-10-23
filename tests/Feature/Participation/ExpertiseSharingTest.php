<?php

namespace Tests\Feature\Participation;

use App\Models\Answer;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use App\Services\Participation\ExpertiseSharing;
use App\Services\Participation\ParticipationOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\Feature\Knowledge\Concerns\MakesTopics;
use Tests\TestCase;

/**
 * The switch on the Dot Points page: default off, honest about what happened, own row only, erasable.
 */
class ExpertiseSharingTest extends TestCase
{
    use FakesMemory;
    use MakesCommunity;
    use MakesTopics;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.dopemine.secret' => null]); // the points ledger is not part of this
        $this->fakeMemory();
    }

    /** @return array{0: Answer, 1: User, 2: User} an answer by $helper accepted by $asker on a public question filed under the topics */
    private function acceptedAnswer(User $helper, string ...$slugs): array
    {
        $asker = $this->person('Asker');
        $question = $this->file($this->question($asker, 'public', 'How do I connect my bank feed?'), ...($slugs ?: ['integrations']));
        $answer = app(AnswerService::class)->accept($this->answer($question, $helper), $asker);

        return [$answer, $asker, $helper];
    }

    private function page(User $as): string
    {
        return $this->actingAs($as)->get('/points')->assertOk()->getContent();
    }

    private function section(string $html): string
    {
        $this->assertSame(1, preg_match('#<section[^>]*id="memory-sharing".*?</section>#s', $html, $m));

        return $m[0];
    }

    // ---------------------------------------------------------------- the page

    public function test_new_people_are_not_sharing(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->fresh()->sharesExpertise());
        $this->assertSame(0, (int) DB::table('users')->where('id', $user->id)->value('share_expertise'));
    }

    public function test_the_page_offers_the_switch_with_the_exact_wording_and_the_honest_limits(): void
    {
        $user = $this->person();

        $section = $this->section($this->page($user));

        $this->assertStringContainsString('Let Dot.Memory remember the topics I help with', $section);
        $this->assertStringContainsString('Topic and count only, no content. Turn it off any time and it is erased.', $section);
        $this->assertMatchesRegularExpression('/<label[^>]*for="pts-share-expertise"/', $section);
        $this->assertDoesNotMatchRegularExpression('/<input type="checkbox"[^>]*id="pts-share-expertise"[^>]*checked/', $section);
        $this->assertStringContainsString('action="'.route('points.share-expertise').'"', $section);
        $this->assertStringContainsString('Right now this is <strong>off</strong>', $section);
        // No pretending Memory can take one entry back.
        $this->assertStringContainsString('Memory is not told to take that one back', $section);
        $this->assertStringContainsString('retention period', $section);
        // Nothing to show or erase while it is off.
        $this->assertStringNotContainsString('What Memory remembers about me', $section);
        $this->assertSame([], $this->memoryRequests, 'Viewing the page while sharing is off must not call Memory.');
    }

    public function test_when_memory_is_not_connected_there_is_nothing_to_switch_on_and_it_says_so(): void
    {
        $this->fakeMemory(enabled: false);
        $user = $this->person();

        $section = $this->section($this->page($user));

        $this->assertStringContainsString('not connected to InfoDot yet', $section);
        $this->assertStringNotContainsString('name="share_expertise" value="1"', $section);
        $this->assertSame([], $this->memoryRequests);
    }

    // ---------------------------------------------------------------- turning it on

    public function test_turning_it_on_records_consent_first_then_sets_the_switch(): void
    {
        $user = $this->person();

        $this->actingAs($user)->post('/points/share-expertise', ['share_expertise' => '1'])->assertRedirect(route('points').'#memory-sharing');

        $this->assertTrue($user->fresh()->sharesExpertise());
        $this->assertTrue($this->memoryConsent[$user->uuid]);
        [$put] = $this->memorySent('PUT');
        $this->assertSame('{"granted":true}', $put->body());
    }

    public function test_the_notice_after_turning_it_on_and_the_empty_state(): void
    {
        $user = $this->person();

        $html = $this->actingAs($user)->followingRedirects()->post('/points/share-expertise', ['share_expertise' => '1'])->getContent();
        $section = $this->section($html);

        $this->assertStringContainsString('Sharing is on. Dot.Memory will remember the topics you help with, as a topic and a count only.', $section);
        $this->assertMatchesRegularExpression('/<input type="checkbox"[^>]*id="pts-share-expertise"[^>]*checked/', $section);
        $this->assertStringContainsString('What Memory remembers about me', $section);
        $this->assertStringContainsString('Nothing yet.', $section);
        $this->assertStringContainsString('Turn off and erase everything Memory holds about me', $section);
    }

    public static function failures(): array
    {
        return [
            'memory down' => [fn () => Http::response(['message' => 'x'], 503)],
            'connection error' => [fn () => fn () => throw new ConnectionException('down')],
            'secret mismatch' => [fn () => Http::response(['code' => 'invalid_signature', 'message' => 'x'], 401)],
            'answers 200 but not with the decision we asked for' => [fn () => Http::response(['data' => ['consent' => ['granted' => false]]])],
            'answers 200 with nothing we recognise' => [fn () => Http::response('<html>maintenance</html>', 200)],
        ];
    }

    #[DataProvider('failures')]
    public function test_when_memory_does_not_confirm_nothing_changes_and_we_never_say_it_was_recorded(callable $answer): void
    {
        $user = $this->person();
        [$answered] = $this->acceptedAnswer($user);
        $this->memoryOverride['PUT /api/participation/{uuid}/consent'] = $answer();

        $html = $this->actingAs($user)->followingRedirects()->post('/points/share-expertise', ['share_expertise' => '1'])->getContent();

        $this->assertFalse($user->fresh()->sharesExpertise());
        $this->assertSame(0, DB::table('participation_outbox')->count(), 'no backfill without consent');
        $section = $this->section($html);
        $this->assertStringContainsString('was not recorded. Sharing is still off.', $section);
        $this->assertStringNotContainsString('Sharing is on', $section);
        $this->assertDoesNotMatchRegularExpression('/<input type="checkbox"[^>]*id="pts-share-expertise"[^>]*checked/', $section);
        $this->assertCount(1, $this->memoryRequests, 'only the consent call was made');
        $this->assertSame([], $this->memoryEvents);
    }

    public function test_when_memory_is_not_configured_turning_it_on_changes_nothing_and_says_so(): void
    {
        $user = $this->person();
        $this->fakeMemory(enabled: false);
        $this->memoryRequests = [];

        $html = $this->actingAs($user)->followingRedirects()->post('/points/share-expertise', ['share_expertise' => '1'])->getContent();

        $this->assertFalse($user->fresh()->sharesExpertise());
        $this->assertStringContainsString('not connected to InfoDot yet, so your choice was not recorded', $html);
        $this->assertSame([], $this->memoryRequests);
    }

    public function test_turning_it_on_queues_and_sends_only_the_eligible_past_contributions(): void
    {
        $user = $this->person();
        [$answer] = $this->acceptedAnswer($user, 'integrations', 'billing-and-payments');
        $moderator = $this->moderator();
        $solution = $this->file($this->solution($user), 'data-and-reports');
        app(ModerationService::class)->verify($solution, $moderator, 'Followed every step.');
        // Not eligible: a company-only solution, an answer nobody accepted, an answer on a question with no topic.
        $private = $this->file($this->solution($user, 'company'), 'integrations');
        $private->forceFill(['verified_at' => now(), 'verified_by' => $moderator->id])->save();
        $this->answer($this->file($this->question($this->person('Other asker'), 'public', 'An open question'), 'integrations'), $user);
        $untagged = $this->question($this->person('Another asker'), 'public', 'No topic yet');
        app(AnswerService::class)->accept($this->answer($untagged, $user), $untagged->user);

        $html = $this->actingAs($user)->followingRedirects()->post('/points/share-expertise', ['share_expertise' => '1'])->getContent();

        $this->assertStringContainsString('3 of your earlier contributions are being sent now.', $html);
        $sent = collect($this->memoryEvents)->map(fn ($e) => [$e['contribution_ref'], $e['topic'], $e['kind']])->sort()->values()->all();
        $this->assertSame([
            ['infodot:answer:'.$answer->id, 'billing-and-payments', 'accepted'],
            ['infodot:answer:'.$answer->id, 'integrations', 'accepted'],
            ['infodot:solution:'.$solution->id, 'data-and-reports', 'verified'],
        ], $sent);
    }

    // ---------------------------------------------------------------- turning it off

    public function test_turning_it_off_revokes_consent_then_erases_and_clears_the_switch_and_the_queue(): void
    {
        $user = $this->person();
        $this->acceptedAnswer($user);
        app(ExpertiseSharing::class)->enable($user);
        app(ParticipationOutbox::class)->flush();
        $this->assertNotEmpty($this->memoryEvents);
        $this->memoryRequests = [];

        $html = $this->actingAs($user->fresh())->followingRedirects()->post('/points/share-expertise', ['share_expertise' => '0'])->getContent();

        $this->assertFalse($user->fresh()->sharesExpertise());
        $this->assertSame([], $this->memoryEvents);
        $this->assertArrayNotHasKey($user->uuid, $this->memoryConsent, 'the consent record is erased too');
        $this->assertSame(0, DB::table('participation_outbox')->count());
        $this->assertSame(['PUT', 'DELETE'], array_map(fn (Request $r) => $r->method(), $this->memoryRequests));
        $this->assertSame('{"granted":false}', $this->memoryRequests[0]->body());
        $this->assertStringContainsString('Sharing is off, and everything Dot.Memory held about you has been erased.', $this->section($html));
        $this->assertStringNotContainsString('What Memory remembers about me', $this->section($html));
    }

    public function test_the_erase_button_does_the_same_thing(): void
    {
        $user = $this->person();
        $this->acceptedAnswer($user);
        app(ExpertiseSharing::class)->enable($user);
        app(ParticipationOutbox::class)->flush();

        $this->actingAs($user->fresh())->post('/points/share-expertise/erase')->assertRedirect(route('points').'#memory-sharing');

        $this->assertFalse($user->fresh()->sharesExpertise());
        $this->assertSame([], $this->memoryEvents);
    }

    public function test_if_memory_cannot_be_reached_turning_it_off_changes_nothing_and_says_so(): void
    {
        $user = $this->person();
        $this->acceptedAnswer($user);
        app(ExpertiseSharing::class)->enable($user);
        $queued = DB::table('participation_outbox')->count();
        $this->memoryOverride['PUT /api/participation/{uuid}/consent'] = fn () => throw new ConnectionException('down');

        $html = $this->actingAs($user->fresh())->followingRedirects()->post('/points/share-expertise', ['share_expertise' => '0'])->getContent();

        $this->assertTrue($user->fresh()->sharesExpertise(), 'the switch stays as it was');
        $this->assertSame($queued, DB::table('participation_outbox')->count());
        $this->assertStringContainsString('could not reach Dot.Memory, so nothing was changed', $html);
        $this->assertStringNotContainsString('has been erased', $html);
    }

    public function test_if_the_revoke_went_through_but_the_final_erase_did_not_sharing_is_off_and_the_person_is_told_what_is_left(): void
    {
        $user = $this->person();
        $this->acceptedAnswer($user);
        app(ExpertiseSharing::class)->enable($user);
        app(ParticipationOutbox::class)->flush();
        $this->memoryOverride['DELETE /api/participation/{uuid}'] = Http::response(['message' => 'x'], 503);

        $html = $this->actingAs($user->fresh())->followingRedirects()->post('/points/share-expertise', ['share_expertise' => '0'])->getContent();

        $this->assertFalse($user->fresh()->sharesExpertise());
        $this->assertSame([], $this->memoryEvents, 'the revoke already purged the topic counts');
        $this->assertStringContainsString('Sharing is off and Dot.Memory has removed the topic counts it held', $html);
        $this->assertStringContainsString('did not finish', $html);
        $this->assertStringNotContainsString('everything Dot.Memory held about you has been erased', $html);
    }

    public function test_turning_it_off_when_it_was_off_calls_nothing(): void
    {
        $user = $this->person();

        $this->actingAs($user)->post('/points/share-expertise', ['share_expertise' => '0']);

        $this->assertSame([], $this->memoryRequests);
    }

    public function test_turning_it_on_twice_does_not_ask_memory_twice(): void
    {
        $user = $this->person();
        app(ExpertiseSharing::class)->enable($user);
        $this->memoryRequests = [];

        $this->actingAs($user->fresh())->post('/points/share-expertise', ['share_expertise' => '1']);

        $this->assertSame([], $this->memoryRequests);
    }

    public function test_after_off_and_on_again_everything_is_sent_afresh(): void
    {
        $user = $this->person();
        $this->acceptedAnswer($user);
        $sharing = app(ExpertiseSharing::class);
        $sharing->enable($user);
        app(ParticipationOutbox::class)->flush();
        $sharing->disable($user->fresh());
        $this->assertSame([], $this->memoryEvents);

        $sharing->enable($user->fresh());
        app(ParticipationOutbox::class)->flush();

        $this->assertCount(1, $this->memoryEvents, 'the earlier event was not skipped as already sent');
    }

    // ---------------------------------------------------------------- what Memory remembers about me

    public function test_it_lists_the_topics_and_counts_memory_holds_with_topic_names(): void
    {
        $user = $this->person();
        app(ExpertiseSharing::class)->enable($user);
        $this->acceptedAnswer($user, 'integrations');
        $this->acceptedAnswer($user, 'integrations', 'billing-and-payments');
        $solution = $this->file($this->solution($user), 'integrations');
        app(ModerationService::class)->verify($solution, $this->moderator(), 'Followed every step.');
        app(ParticipationOutbox::class)->flush();

        $section = $this->section($this->page($user->fresh()));

        $this->assertStringContainsString('What Memory remembers about me', $section);
        $this->assertMatchesRegularExpression('#<span class="ix-topic">Integrations</span>\s*<span>3 times</span>#', $section);
        $this->assertMatchesRegularExpression('#<span class="ix-topic">Billing &amp; payments</span>\s*<span>1 time</span>#', $section);
        $this->assertStringContainsString('2 accepted, 1 verified', preg_replace('/\s+/', ' ', $section));
        // Topic and count only: nothing about the questions or answers behind them.
        $this->assertStringNotContainsString('How do I connect my bank feed', $section);
        $this->assertStringNotContainsString('CIPC', $section);
    }

    public function test_it_is_not_read_from_memory_when_sharing_is_off(): void
    {
        $this->page($this->person());

        $this->assertSame([], $this->memorySent('GET'));
    }

    public function test_when_memory_is_unreachable_the_page_still_loads_and_says_so(): void
    {
        $user = $this->person();
        app(ExpertiseSharing::class)->enable($user);
        $this->memoryOverride['GET /api/participation/{uuid}/context'] = fn () => throw new ConnectionException('down');

        $section = $this->section($this->page($user->fresh()));

        $this->assertStringContainsString('Dot.Memory could not be reached, so we cannot show what it holds right now.', $section);
        $this->assertStringContainsString('Turn off and erase everything Memory holds about me', $section);
    }

    public function test_when_memory_holds_no_consent_the_page_says_it_keeps_nothing(): void
    {
        $user = $this->person();
        app(ExpertiseSharing::class)->enable($user);
        unset($this->memoryConsent[$user->uuid]);

        $section = $this->section($this->page($user->fresh()));

        $this->assertStringContainsString('Dot.Memory does not hold your agreement, so it is keeping nothing about you.', $section);
    }

    public function test_it_says_how_many_entries_are_still_on_the_way(): void
    {
        $user = $this->person();
        $this->acceptedAnswer($user, 'integrations', 'billing-and-payments');
        app(ExpertiseSharing::class)->enable($user); // queued, not flushed yet

        $section = $this->section($this->page($user->fresh()));

        $this->assertStringContainsString('2 entries are still on the way to Memory.', $section);
    }

    // ---------------------------------------------------------------- own row, validation, guests, CSRF

    public function test_it_only_changes_the_signed_in_persons_own_setting(): void
    {
        $me = $this->person();
        $other = $this->person('Other');

        $this->actingAs($me)->post('/points/share-expertise', ['share_expertise' => '1', 'user_id' => $other->id, 'id' => $other->id, 'uuid' => $other->uuid]);

        $this->assertTrue($me->fresh()->sharesExpertise());
        $this->assertFalse($other->fresh()->sharesExpertise());
        $this->assertArrayNotHasKey($other->uuid, $this->memoryConsent);
        $this->assertStringNotContainsString($other->uuid, implode(' ', array_map(fn (Request $r) => $r->url().$r->body(), $this->memoryRequests)));
    }

    public function test_anything_but_0_or_1_is_refused_and_asks_memory_nothing(): void
    {
        $user = $this->person();

        foreach (['', 'yes', '2', null, ['1']] as $value) {
            $this->actingAs($user)->from('/points')->post('/points/share-expertise', ['share_expertise' => $value])
                ->assertRedirect('/points')->assertSessionHasErrors('share_expertise');
        }

        $this->assertFalse($user->fresh()->sharesExpertise());
        $this->assertSame([], $this->memoryRequests);
    }

    public function test_guests_are_sent_to_sign_in_and_nothing_happens(): void
    {
        $user = User::factory()->create();

        $this->post('/points/share-expertise', ['share_expertise' => '1'])->assertRedirect(route('login'));
        $this->post('/points/share-expertise/erase')->assertRedirect(route('login'));

        $this->assertFalse($user->fresh()->sharesExpertise());
        $this->assertSame([], $this->memoryRequests);
    }

    public function test_the_posts_require_a_csrf_token(): void
    {
        $this->app['env'] = 'production'; // CSRF is skipped while unit tests run; production mode switches it on.
        $user = $this->person();

        $this->actingAs($user)->post('/points/share-expertise', ['share_expertise' => '1'])->assertStatus(419);
        $this->actingAs($user)->post('/points/share-expertise/erase')->assertStatus(419);

        $this->assertFalse($user->fresh()->sharesExpertise());
        $this->assertSame([], $this->memoryRequests);
    }

    // ---------------------------------------------------------------- privacy of everything that leaves

    public function test_nothing_but_ids_topics_and_kinds_ever_leaves_for_memory(): void
    {
        $helper = $this->person('Thandi Nkosi');
        $helper->forceFill(['email' => 'thandi.private@example.org'])->save();
        app(ExpertiseSharing::class)->enable($helper);
        [$answer] = $this->acceptedAnswer($helper, 'integrations', 'billing-and-payments');
        $solution = $this->file($this->solution($helper, 'public', 'Register a company with CIPC'), 'getting-started');
        app(ModerationService::class)->verify($solution, $this->moderator('Mod Person'), 'Checked it on a real filing.');
        app(ParticipationOutbox::class)->flush();
        $this->actingAs($helper->fresh())->get('/points');
        app(ExpertiseSharing::class)->disable($helper->fresh());

        $this->assertNotEmpty($this->memoryRequests);
        foreach ($this->memoryRequests as $request) {
            $wire = $request->url().' '.$request->body();
            foreach (['thandi', 'Nkosi', '@', 'Mod Person', 'CIPC', 'bank feed', 'Checked it', 'First register', 'company', 'team'] as $private) {
                $this->assertStringNotContainsStringIgnoringCase($private, $wire, 'Memory must never be sent "'.$private.'".');
            }
            if ($request->method() === 'POST') {
                $this->assertSame(['event_id', 'user_uuid', 'occurred_at', 'topic', 'kind', 'contribution_ref'], array_keys(json_decode($request->body(), true)));
            }
            if ($request->method() === 'PUT') {
                $this->assertSame(['granted'], array_keys(json_decode($request->body(), true)));
            }
            $this->assertArrayNotHasKey('Authorization', $request->headers());
            $this->assertSame([], array_diff(array_keys($request->headers()), ['User-Agent', 'X-InfoDot-Timestamp', 'X-InfoDot-Signature', 'Accept', 'Content-Type', 'Content-Length', 'Host']));
        }
    }
}

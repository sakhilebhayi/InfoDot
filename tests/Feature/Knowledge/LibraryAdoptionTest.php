<?php

namespace Tests\Feature\Knowledge;

use App\Models\Answer;
use App\Models\LibraryEntry;
use App\Models\LibraryOutcome;
use App\Models\ModerationAction;
use App\Models\Solutions;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use App\Services\Library\LibraryService;
use App\Services\Points\RewardOutbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/** What it takes to put something in the Verified library, take it out again, and what the points ledger is told. */
class LibraryAdoptionTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    private const DOPEMINE_SECRET = 'dopemine-secret-for-tests';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.dopemine.url' => 'https://dopemine.test', 'services.dopemine.secret' => self::DOPEMINE_SECRET,
            'knowledge.memory_url' => 'https://memory.test', 'knowledge.infodot_secret' => 'infodot-secret-for-tests',
        ]);
    }

    private function library(): LibraryService
    {
        return app(LibraryService::class);
    }

    /** @return array{0: Answer, 1: User} an answer accepted by its asker, and its author */
    private function acceptedAnswer(string $visibility = 'public'): array
    {
        $asker = $this->person('Asker');
        $author = $this->person('Answerer');
        if ($visibility === 'company') {
            $asker->currentTeam->users()->attach($author->id, ['role' => 'editor']);
        }
        $answer = app(AnswerService::class)->accept($this->answer($this->question($asker, $visibility), $author), $asker);

        return [$answer->fresh(), $author];
    }

    private function verifiedSolution(?User $author = null): Solutions
    {
        $solution = $this->solution($author ?? $this->person('Writer'));
        app(ModerationService::class)->verify($solution, $this->moderator('Checker'));

        return $solution->fresh();
    }

    /** @return list<array<string, mixed>> the ledger events of one type, oldest first */
    private function events(string $type): array
    {
        return DB::table('reward_outbox')->where('type', $type)->orderBy('id')->get()->map(fn ($r) => json_decode($r->payload, true))->all();
    }

    private function actions(string $action): int
    {
        return ModerationAction::where('action', $action)->count();
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Adopting
    // ---------------------------------------------------------------------------------------------------------------

    public function test_a_moderator_adopts_an_accepted_answer_and_exactly_one_ledger_event_is_queued(): void
    {
        [$answer, $author] = $this->acceptedAnswer();
        $mod = $this->moderator('Curator');

        ['entry' => $entry, 'added' => $added] = $this->library()->adopt($answer, $mod);

        $this->assertTrue($added);
        $this->assertSame('infodot:answer:'.$answer->id, $entry->contribution_ref);
        $this->assertSame('answer', $entry->kind);
        $this->assertSame($answer->id, $entry->answer_id);
        $this->assertNull($entry->solution_id);
        $this->assertSame($mod->id, $entry->adopted_by);
        $this->assertNotNull($entry->adopted_at);
        $this->assertNull($entry->recommendation_event_id);
        $this->assertNull($entry->withdrawn_at);

        $audit = ModerationAction::where('action', 'library_adopted')->sole();
        $this->assertSame($mod->id, $audit->actor_id);
        $this->assertSame($mod->uuid, $audit->actor_uuid);
        $this->assertSame($answer->id, (int) $audit->subject_id);
        $this->assertSame(Answer::class, $audit->subject_type);

        [$event] = $this->events('infodot.knowledge.adopted');
        $this->assertCount(1, $this->events('infodot.knowledge.adopted'));
        $this->assertSame($author->uuid, $event['user_uuid'], 'the author is paid');
        $this->assertSame($mod->uuid, $event['actor_uuid'], 'the moderator is the approver');
        $this->assertSame('infodot:answer:'.$answer->id, $event['contribution_ref']);
        $this->assertSame('library:'.$entry->id, $event['facts']['pack_ref']);
        $this->assertSame('public', $event['visibility']);
        $this->assertArrayNotHasKey('body', $event['facts']);
    }

    public function test_a_moderator_adopts_a_verified_solution(): void
    {
        $author = $this->person('Writer');
        $solution = $this->verifiedSolution($author);

        ['entry' => $entry] = $this->library()->adopt($solution, $this->moderator('Curator'));

        $this->assertSame('infodot:solution:'.$solution->id, $entry->contribution_ref);
        $this->assertSame($solution->id, $entry->solution_id);
        $this->assertNull($entry->answer_id);
        [$event] = $this->events('infodot.knowledge.adopted');
        $this->assertSame($author->uuid, $event['user_uuid']);
        $this->assertSame('infodot:solution:'.$solution->id, $event['contribution_ref']);
    }

    public function test_adopting_twice_changes_nothing_the_second_time(): void
    {
        [$answer] = $this->acceptedAnswer();
        $first = $this->moderator('First');
        $second = $this->moderator('Second');

        $this->library()->adopt($answer, $first);
        $again = $this->library()->adopt($answer, $second);

        $this->assertFalse($again['added']);
        $this->assertSame(1, LibraryEntry::count());
        $this->assertSame($first->id, LibraryEntry::first()->adopted_by, 'the first adopter stands');
        $this->assertSame(1, $this->actions('library_adopted'));
        $this->assertCount(1, $this->events('infodot.knowledge.adopted'));
    }

    public function test_the_ledger_receives_the_adoption_once_signed_and_a_replay_adds_nothing(): void
    {
        Http::fake(['dopemine.test/*' => Http::response(['status' => 'awarded'], 202)]);
        [$answer, $author] = $this->acceptedAnswer();
        $mod = $this->moderator('Curator');

        $this->library()->adopt($answer, $mod);
        $this->library()->adopt($answer, $mod);
        app(RewardOutbox::class)->flush(50);
        app(RewardOutbox::class)->flush(50);

        $sent = [];
        Http::assertSent(function (Request $request) use (&$sent) {
            $body = json_decode($request->body(), true);
            if (($body['type'] ?? null) === 'infodot.knowledge.adopted') {
                $sent[] = $body;
                $this->assertTrue($request->hasHeader('X-InfoDot-Signature'));
            }

            return true;
        });
        $this->assertCount(1, $sent);
        $this->assertSame($mod->uuid, $sent[0]['actor_uuid']);
        $this->assertSame($author->uuid, $sent[0]['user_uuid']);
    }

    public function test_a_moderator_cannot_adopt_their_own_content(): void
    {
        $mod = $this->moderator('Author-moderator');
        $asker = $this->person('Asker');
        $answer = app(AnswerService::class)->accept($this->answer($this->question($asker), $mod), $asker);
        $solution = $this->solution($mod);
        DB::table('solutions')->where('id', $solution->id)->update(['verified_at' => now(), 'verified_by' => $this->moderator('Other')->id]);

        foreach ([$answer->fresh(), $solution->fresh()] as $own) {
            try {
                $this->library()->adopt($own, $mod);
                $this->fail('self-adoption was allowed');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $this->assertSame(0, LibraryEntry::count());
        $this->assertSame(0, ModerationAction::count() - $this->actions('verified'));
        $this->assertSame([], $this->events('infodot.knowledge.adopted'));
    }

    public function test_only_moderators_can_adopt_decline_or_withdraw(): void
    {
        [$answer] = $this->acceptedAnswer();
        $member = $this->person('Member');

        foreach ([
            fn () => $this->library()->adopt($answer, $member),
            fn () => $this->library()->decline('decision-1', 'infodot:answer:'.$answer->id, $member),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('a member was allowed');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $entry = $this->library()->adopt($answer, $this->moderator('Curator'))['entry'];
        try {
            $this->library()->withdraw($entry, $member, 'Not a moderator at all.');
            $this->fail('a member withdrew');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertNull($entry->fresh()->withdrawn_at);
        $this->assertSame(0, LibraryOutcome::count());
    }

    /** @return iterable<string, array{0: callable(self): Model, 1: string}> */
    public static function ineligibleItems(): iterable
    {
        yield 'an answer nobody accepted' => [fn (self $t) => $t->answer($t->question($t->person()), $t->person()), 'accepted'];
        yield 'an answer accepted only by its own author (forced)' => [function (self $t) {
            $author = $t->person();
            $answer = $t->answer($t->question($t->person()), $author);
            DB::table('answers')->where('id', $answer->id)->update(['accepted_at' => now(), 'accepted_by' => $author->id]);

            return $answer->fresh();
        }, 'accepted'];
        yield 'a hidden answer' => [function (self $t) {
            [$answer] = $t->acceptedAnswerFor();
            DB::table('answers')->where('id', $answer->id)->update(['hidden_at' => now()]);

            return $answer->fresh();
        }, 'hidden'];
        yield 'an answer under a hidden question' => [function (self $t) {
            [$answer] = $t->acceptedAnswerFor();
            DB::table('questions')->where('id', $answer->question_id)->update(['hidden_at' => now()]);

            return $answer->fresh();
        }, 'hidden'];
        yield 'a deleted answer' => [function (self $t) {
            [$answer] = $t->acceptedAnswerFor();
            $answer->delete();

            return Answer::withoutGlobalScopes()->find($answer->id);
        }, 'deleted'];
        yield 'a removed answer' => [function (self $t) {
            [$answer] = $t->acceptedAnswerFor();
            DB::table('answers')->where('id', $answer->id)->update(['status' => 'removed']);

            return $answer->fresh();
        }, 'not published'];
        yield 'a company-only answer' => [fn (self $t) => $t->acceptedAnswerFor('company')[0], 'company'];
        yield 'a solution nobody verified' => [fn (self $t) => $t->solution($t->person()), 'verified'];
        yield 'a solution verified by its own author (forced)' => [function (self $t) {
            $mod = $t->moderator();
            $solution = $t->solution($mod);
            DB::table('solutions')->where('id', $solution->id)->update(['verified_at' => now(), 'verified_by' => $mod->id]);

            return $solution->fresh();
        }, 'verified'];
        yield 'a hidden solution' => [function (self $t) {
            $solution = $t->verifiedSolutionFor();
            DB::table('solutions')->where('id', $solution->id)->update(['hidden_at' => now()]);

            return $solution->fresh();
        }, 'hidden'];
        yield 'a company-only solution' => [function (self $t) {
            $author = $t->person();
            $solution = $t->solution($author, 'company');
            DB::table('solutions')->where('id', $solution->id)->update(['verified_at' => now(), 'verified_by' => $t->moderator()->id]);

            return $solution->fresh();
        }, 'company'];
    }

    /** Public wrappers so the data provider's closures can reach the private helpers. @return array{0: Answer, 1: \App\Models\User} */
    public function acceptedAnswerFor(string $visibility = 'public'): array
    {
        return $this->acceptedAnswer($visibility);
    }

    public function verifiedSolutionFor(): Solutions
    {
        return $this->verifiedSolution();
    }

    #[DataProvider('ineligibleItems')]
    public function test_content_that_is_not_public_live_and_corroborated_cannot_be_adopted(callable $make, string $why): void
    {
        $item = $make($this);

        try {
            $this->library()->adopt($item, $this->moderator('Curator'));
            $this->fail('an ineligible item was adopted');
        } catch (ValidationException $e) {
            $this->assertStringContainsStringIgnoringCase($why, $e->errors()['adopt'][0]);
        }

        $this->assertSame(0, LibraryEntry::count());
        $this->assertSame(0, $this->actions('library_adopted'));
        $this->assertSame([], $this->events('infodot.knowledge.adopted'));
    }

    public function test_a_question_is_not_a_library_candidate(): void
    {
        $this->assertNull($this->library()->find('infodot:question:'.$this->question($this->person())->id));
        $this->assertNull($this->library()->find('infodot:answer:0'));
        $this->assertNull($this->library()->find('answer 12'));
        $this->assertNull($this->library()->find('infodot:answer:99999'));
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Withdrawing
    // ---------------------------------------------------------------------------------------------------------------

    public function test_withdrawing_hides_the_entry_audits_it_and_reverses_only_the_adopted_award_once(): void
    {
        [$answer, $author] = $this->acceptedAnswer();
        $entry = $this->library()->adopt($answer, $this->moderator('Curator'))['entry'];
        $mod = $this->moderator('Withdrawer');

        $this->assertTrue($this->library()->withdraw($entry, $mod, 'It is out of date now.'));
        $this->assertFalse($this->library()->withdraw($entry->fresh(), $this->moderator('Another'), 'A second reason, ignored.'));

        $this->assertNotNull($entry->fresh()->withdrawn_at);
        $audit = ModerationAction::where('action', 'library_withdrawn')->sole();
        $this->assertSame($mod->id, $audit->actor_id);
        $this->assertSame('It is out of date now.', $audit->reason);

        $reversals = $this->events('infodot.contribution.reward_reversed');
        $this->assertCount(1, $reversals);
        $this->assertSame('infodot.knowledge.adopted', $reversals[0]['facts']['rule_key']);
        $this->assertSame($author->uuid, $reversals[0]['user_uuid']);
        $this->assertSame($mod->uuid, $reversals[0]['actor_uuid']);
        $this->assertSame('infodot:answer:'.$answer->id, $reversals[0]['contribution_ref']);
    }

    public function test_a_withdrawal_needs_a_reason(): void
    {
        [$answer] = $this->acceptedAnswer();
        $entry = $this->library()->adopt($answer, $this->moderator('Curator'))['entry'];

        try {
            $this->library()->withdraw($entry, $this->moderator('Withdrawer'), 'no');
            $this->fail('a one-word reason was accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('withdraw', $e->errors());
        }
        $this->assertNull($entry->fresh()->withdrawn_at);
        $this->assertSame(0, $this->actions('library_withdrawn'));
    }

    public function test_a_moderator_cannot_withdraw_their_own_content(): void
    {
        $authorMod = $this->moderator('Author-moderator');
        $asker = $this->person('Asker');
        $answer = app(AnswerService::class)->accept($this->answer($this->question($asker), $authorMod), $asker);
        $entry = LibraryEntry::create([
            'contribution_ref' => 'infodot:answer:'.$answer->id, 'kind' => 'answer', 'answer_id' => $answer->id, 'adopted_by' => $this->moderator('Curator')->id, 'adopted_at' => now(),
        ]);

        try {
            $this->library()->withdraw($entry, $authorMod, 'I want mine out.');
            $this->fail('own content was withdrawn by its author');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertNull($entry->fresh()->withdrawn_at);
    }

    public function test_a_withdrawn_entry_can_be_adopted_again_as_a_new_decision(): void
    {
        [$answer] = $this->acceptedAnswer();
        $mod = $this->moderator('Curator');
        $entry = $this->library()->adopt($answer, $mod)['entry'];
        $this->library()->withdraw($entry, $mod2 = $this->moderator('Withdrawer'), 'Needs another look first.');

        $again = $this->library()->adopt($answer, $mod2);

        $this->assertTrue($again['added']);
        $this->assertSame($entry->id, $again['entry']->id, 'the same row comes back');
        $this->assertNull($again['entry']->fresh()->withdrawn_at);
        $this->assertSame(2, $this->actions('library_adopted'));
        $events = $this->events('infodot.knowledge.adopted');
        $this->assertCount(2, $events);
        $this->assertNotSame($events[0]['event_id'], $events[1]['event_id']);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Outcomes for Dot.Memory (what is queued here; delivery is tested with the client)
    // ---------------------------------------------------------------------------------------------------------------

    public function test_adopting_a_recommendation_records_the_decision_for_memory_once(): void
    {
        [$answer] = $this->acceptedAnswer();
        $mod = $this->moderator('Curator');

        $entry = $this->library()->adopt($answer, $mod, 'decision-abc')['entry'];
        $this->library()->adopt($answer, $mod, 'decision-abc');

        $this->assertSame('decision-abc', $entry->recommendation_event_id);
        $outcome = LibraryOutcome::sole();
        $this->assertSame('decision-abc', $outcome->decision_event_id);
        $this->assertSame('adopted', $outcome->outcome);
        $this->assertSame($mod->uuid, $outcome->actor_ref, 'Memory is told the moderator\'s uuid');
        $this->assertSame('infodot:answer:'.$answer->id, $outcome->contribution_ref);
        $this->assertNotNull($outcome->next_attempt_at);
        $this->assertNull($outcome->sent_at);
        $this->assertCount(1, $this->events('infodot.knowledge.adopted'));
    }

    public function test_declining_records_who_decided_asks_for_no_reason_and_is_idempotent(): void
    {
        [$answer, $author] = $this->acceptedAnswer();
        $mod = $this->moderator('Curator');

        $this->assertTrue($this->library()->decline('decision-xyz', 'infodot:answer:'.$answer->id, $mod));
        $this->assertFalse($this->library()->decline('decision-xyz', 'infodot:answer:'.$answer->id, $this->moderator('Other')));

        $outcome = LibraryOutcome::sole();
        $this->assertSame('declined', $outcome->outcome);
        $this->assertSame($mod->uuid, $outcome->actor_ref);
        $audit = ModerationAction::where('action', 'library_declined')->sole();
        $this->assertSame($mod->id, $audit->actor_id);
        $this->assertSame($answer->id, (int) $audit->subject_id);
        $this->assertSame(0, LibraryEntry::count());
        $this->assertSame([], $this->events('infodot.knowledge.adopted'), 'a decline pays and reverses nothing');
        $this->assertSame([], $this->events('infodot.contribution.reward_reversed'));
    }

    public function test_a_declined_recommendation_cannot_then_be_adopted_and_an_adopted_one_cannot_be_declined(): void
    {
        [$answer] = $this->acceptedAnswer();
        $mod = $this->moderator('Curator');
        $this->library()->decline('decision-1', 'infodot:answer:'.$answer->id, $mod);

        try {
            $this->library()->adopt($answer, $mod, 'decision-1');
            $this->fail('a declined recommendation was adopted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already declined', $e->errors()['adopt'][0]);
        }
        $this->assertSame(0, LibraryEntry::count());

        [$other] = $this->acceptedAnswer();
        $this->library()->adopt($other, $mod, 'decision-2');
        $this->assertFalse($this->library()->decline('decision-2', 'infodot:answer:'.$other->id, $mod));
        $this->assertSame('adopted', LibraryOutcome::where('decision_event_id', 'decision-2')->value('outcome'));
    }

    public function test_a_moderator_cannot_decline_a_recommendation_about_their_own_content(): void
    {
        $authorMod = $this->moderator('Author-moderator');
        $asker = $this->person('Asker');
        $answer = app(AnswerService::class)->accept($this->answer($this->question($asker), $authorMod), $asker);

        try {
            $this->library()->decline('decision-own', 'infodot:answer:'.$answer->id, $authorMod);
            $this->fail('own content was declined by its author');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame(0, LibraryOutcome::count());
    }

    public function test_declining_a_recommendation_whose_item_is_gone_still_records_the_outcome(): void
    {
        $this->assertTrue($this->library()->decline('decision-gone', 'infodot:answer:424242', $this->moderator('Curator')));

        $this->assertSame('declined', LibraryOutcome::sole()->outcome);
        $this->assertSame(0, $this->actions('library_declined'), 'there is nothing to attach the audit row to');
    }

    public function test_the_new_audit_actions_have_labels(): void
    {
        foreach (['library_adopted', 'library_declined', 'library_withdrawn'] as $action) {
            $this->assertContains($action, ModerationAction::ACTIONS);
            $this->assertNotSame($action, (new ModerationAction(['action' => $action]))->forceFill(['action' => $action])->label());
        }
    }
}

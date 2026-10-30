<?php

namespace Tests\Feature\Knowledge;

use App\Models\Answer;
use App\Models\Solutions;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\LikeToggle;
use App\Services\Knowledge\ModerationService;
use App\Services\Knowledge\TopicService;
use App\Support\Knowledge\Signature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\Feature\Knowledge\Concerns\MakesTopics;
use Tests\TestCase;

/** GET /api/knowledge/candidates: what Dot.Brain may pull, and only that. */
class KnowledgeCandidatesTest extends TestCase
{
    use MakesCommunity;
    use MakesTopics;
    use RefreshDatabase;

    private const SECRET = 'brain-secret-for-tests';

    protected function setUp(): void
    {
        parent::setUp();
        config(['knowledge.brain_secret' => self::SECRET]);
        Carbon::setTestNow('2026-10-27 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function pull(string $query = '', ?array $headers = null): array
    {
        return $this->pullResponse($query, $headers)->assertOk()->json();
    }

    private function pullResponse(string $query = '', ?array $headers = null)
    {
        // A plain GET with no body (getJson() would send "[]" as the body, which a real client does not).
        return $this->withHeaders(($headers ?? Signature::headers(self::SECRET)) + ['Accept' => 'application/json'])
            ->get('/api/knowledge/candidates'.($query === '' ? '' : '?'.$query));
    }

    /** An answer accepted by its asker (someone other than the author). */
    private function acceptedAnswer(string $visibility = 'public', ?string $at = null): Answer
    {
        if ($at !== null) {
            Carbon::setTestNow($at);
        }

        $asker = $this->person('Asker');
        $answerer = $this->person('Answerer');
        if ($visibility === 'company') {
            $asker->currentTeam->users()->attach($answerer->id, ['role' => 'editor']);
        }
        $answer = $this->answer($this->question($asker, $visibility), $answerer);

        return app(AnswerService::class)->accept($answer, $asker);
    }

    private function verifiedSolution(string $visibility = 'public', ?string $at = null): Solutions
    {
        if ($at !== null) {
            Carbon::setTestNow($at);
        }

        $writer = $this->person('Writer');
        $checker = $this->moderator('Checker');
        if ($visibility === 'company') {
            $writer->currentTeam->users()->attach($checker->id, ['role' => 'editor']);
        }
        $solution = $this->solution($writer, $visibility);
        app(ModerationService::class)->verify($solution, $checker);

        return $solution->fresh();
    }

    private function refs(array $body): array
    {
        return array_column($body['items'], 'contribution_ref');
    }

    private function item(array $body, string $ref): array
    {
        foreach ($body['items'] as $item) {
            if ($item['contribution_ref'] === $ref) {
                return $item;
            }
        }

        $this->fail("No item $ref in ".json_encode($this->refs($body)));
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Authentication
    // ---------------------------------------------------------------------------------------------------------------

    public function test_a_request_without_a_signature_is_refused_with_a_code(): void
    {
        $this->pullResponse('', [])->assertStatus(401)->assertExactJson([
            'code' => 'invalid_signature',
            'message' => 'The request is not signed correctly, or its timestamp is too old.',
        ]);
    }

    public function test_it_fails_closed_while_the_secret_is_not_set(): void
    {
        foreach ([null, ''] as $unset) {
            config(['knowledge.brain_secret' => $unset]);

            // Even a signature made with an empty key must not get in.
            $this->pullResponse('', Signature::headers((string) $unset))->assertStatus(401)->assertJson(['code' => 'invalid_signature']);
        }
    }

    public function test_a_wrong_secret_a_tampered_signature_and_a_missing_prefix_are_refused(): void
    {
        $good = Signature::headers(self::SECRET);

        $this->pullResponse('', Signature::headers('some-other-secret'))->assertStatus(401);
        $this->pullResponse('', [Signature::TIMESTAMP_HEADER => $good[Signature::TIMESTAMP_HEADER], Signature::SIGNATURE_HEADER => substr($good[Signature::SIGNATURE_HEADER], 7)])->assertStatus(401);
        $this->pullResponse('', [Signature::TIMESTAMP_HEADER => $good[Signature::TIMESTAMP_HEADER], Signature::SIGNATURE_HEADER => 'sha256='.str_repeat('0', 64)])->assertStatus(401);
        $this->pullResponse('', [Signature::TIMESTAMP_HEADER => 'abc', Signature::SIGNATURE_HEADER => $good[Signature::SIGNATURE_HEADER]])->assertStatus(401);
    }

    public function test_the_timestamp_must_be_within_300_seconds_either_way(): void
    {
        Carbon::setTestNow(); // the window compares with the real clock

        $this->pullResponse('', Signature::headers(self::SECRET, '', time() - 290))->assertOk();
        $this->pullResponse('', Signature::headers(self::SECRET, '', time() + 290))->assertOk();
        $this->pullResponse('', Signature::headers(self::SECRET, '', time() - 310))->assertStatus(401)->assertJson(['code' => 'invalid_signature']);
        $this->pullResponse('', Signature::headers(self::SECRET, '', time() + 310))->assertStatus(401);
    }

    public function test_it_is_rate_limited_per_address(): void
    {
        config(['knowledge.candidates.per_minute' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->pullResponse()->assertOk();
        }

        $this->pullResponse()->assertStatus(429);
    }

    public function test_the_signature_is_checked_even_when_the_request_is_otherwise_empty_of_data(): void
    {
        // Nothing to return, still no answer for an unsigned caller.
        $this->assertSame(0, Answer::count());
        $this->pullResponse('', [])->assertStatus(401);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // What is a candidate
    // ---------------------------------------------------------------------------------------------------------------

    public function test_an_answer_accepted_by_someone_else_is_a_candidate_with_exactly_the_contract_shape(): void
    {
        Carbon::setTestNow('2026-10-27 09:00:00');
        $asker = $this->person('Asker');
        $author = $this->person('Answerer');
        $question = $this->file($this->question($asker), 'integrations', 'getting-started');
        $answer = $this->answer($question, $author);
        Carbon::setTestNow('2026-10-27 09:30:00');
        app(AnswerService::class)->accept($answer, $asker);

        $body = $this->pull();

        $this->assertSame(['items', 'next_since'], array_keys($body));
        $this->assertCount(1, $body['items']);
        $item = $body['items'][0];
        $this->assertSame([
            'contribution_ref', 'kind', 'topics', 'url', 'author_uuid', 'created_at', 'edited_at', 'accepted_by_independent', 'accepted_at',
            'verified_by_independent', 'verified_at', 'helpful_count', 'tried_count', 'reuse_count', 'hidden', 'reversed',
        ], array_keys($item));
        $this->assertSame('infodot:answer:'.$answer->id, $item['contribution_ref']);
        $this->assertSame('answer', $item['kind']);
        $this->assertEqualsCanonicalizing(['integrations', 'getting-started'], $item['topics']);
        $this->assertSame(url('/question/view/'.$question->id).'#answer-'.$answer->id, $item['url']);
        $this->assertStringStartsWith('http', $item['url']);
        $this->assertSame($author->uuid, $item['author_uuid']);
        $this->assertSame('2026-10-27T09:00:00Z', $item['created_at']);
        $this->assertNull($item['edited_at']);
        $this->assertTrue($item['accepted_by_independent']);
        $this->assertSame('2026-10-27T09:30:00Z', $item['accepted_at']);
        $this->assertFalse($item['verified_by_independent']);
        $this->assertNull($item['verified_at']);
        $this->assertSame(0, $item['helpful_count']);
        $this->assertSame(0, $item['reuse_count']);
        $this->assertFalse($item['hidden']);
        $this->assertFalse($item['reversed']);
        $this->assertSame('2026-10-27T09:30:00Z', $body['next_since']);
    }

    public function test_a_solution_verified_by_a_moderator_other_than_the_author_is_a_candidate(): void
    {
        $author = $this->person('Writer');
        $solution = $this->solution($author);
        $this->file($solution, 'billing-and-payments');
        Carbon::setTestNow('2026-10-27 10:00:00');
        app(ModerationService::class)->verify($solution, $this->moderator('Checker'));

        $item = $this->pull()['items'][0];

        $this->assertSame('infodot:solution:'.$solution->id, $item['contribution_ref']);
        $this->assertSame('solution', $item['kind']);
        $this->assertSame(['billing-and-payments'], $item['topics']);
        $this->assertSame(url('/solution/view/'.$solution->id), $item['url']);
        $this->assertSame($author->uuid, $item['author_uuid']);
        $this->assertTrue($item['verified_by_independent']);
        $this->assertSame('2026-10-27T10:00:00Z', $item['verified_at']);
        $this->assertFalse($item['accepted_by_independent']);
        $this->assertNull($item['accepted_at']);
        $this->assertFalse($item['hidden']);
        $this->assertFalse($item['reversed']);
    }

    public function test_corroboration_by_the_author_themselves_does_not_count(): void
    {
        $answerer = $this->person('Answerer');
        $answer = $this->answer($this->question($this->person()), $answerer);
        $mod = $this->moderator('Writer-moderator');
        $own = $this->solution($mod);

        // Written straight into the table (no screen allows it), without touching updated_at: the author as corroborator.
        DB::table('answers')->where('id', $answer->id)->update(['accepted_at' => now(), 'accepted_by' => $answerer->id]);
        DB::table('solutions')->where('id', $own->id)->update(['verified_at' => now(), 'verified_by' => $mod->id]);

        $this->assertSame([], $this->pull()['items']);
    }

    public function test_uncorroborated_content_is_not_a_candidate(): void
    {
        $this->answer($this->question($this->person()), $this->person());
        $this->solution($this->person());

        $this->assertSame(['items' => [], 'next_since' => null], $this->pull());
    }

    public function test_company_content_never_appears_even_when_corroborated(): void
    {
        $this->acceptedAnswer('company');
        $this->verifiedSolution('company');

        $this->assertSame([], $this->pull()['items']);
    }

    public function test_company_content_stays_out_even_as_a_withdrawal_row(): void
    {
        $answer = $this->acceptedAnswer('company');
        $hider = $this->moderator('Hider');
        $answer->question->user->currentTeam->users()->attach($hider->id, ['role' => 'editor']);
        app(ModerationService::class)->hide($answer->fresh(), $hider, 'Not appropriate for anyone.');

        $this->assertSame([], $this->pull()['items']);
    }

    public function test_the_response_carries_no_content_title_email_or_name(): void
    {
        $asker = $this->person('Unique Asker Name');
        $author = $this->person('Unique Author Name');
        $answer = $this->answer($this->question($asker, 'public', 'A very distinctive question title?'), $author, 'A very distinctive answer body that must never travel to Brain.');
        app(AnswerService::class)->accept($answer, $asker);
        $solution = $this->solution($author, 'public', 'A distinctive solution title');
        app(ModerationService::class)->verify($solution, $this->moderator('Unique Checker Name'));

        $raw = $this->pullResponse()->assertOk()->getContent();

        foreach (['distinctive', 'Unique', $asker->email, $author->email, 'order of the steps matters', 'cipc'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $raw);
        }
        foreach (json_decode($raw, true)['items'] as $item) {
            foreach (['title', 'body', 'excerpt', 'email', 'name', 'question', 'description'] as $key) {
                $this->assertArrayNotHasKey($key, $item);
            }
        }
    }

    public function test_helpful_count_is_the_likes_on_a_solution_from_people_other_than_its_author(): void
    {
        $author = $this->person('Writer');
        $solution = $this->solution($author);
        app(ModerationService::class)->verify($solution, $this->moderator());
        $likes = app(LikeToggle::class);
        $likes->toggle($solution, $this->person('Fan 1'));
        $likes->toggle($solution, $this->person('Fan 2'));
        $likes->toggle($solution, $author);
        $gone = $this->person('Fan 3');
        $likes->toggle($solution, $gone);
        $likes->toggle($solution, $gone); // taken back

        $this->assertSame(2, $this->pull()['items'][0]['helpful_count']);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Updates: hidden and reversed rows, and the since cursor
    // ---------------------------------------------------------------------------------------------------------------

    public function test_a_hidden_answer_comes_back_once_as_a_hidden_and_reversed_row(): void
    {
        $answer = $this->acceptedAnswer();
        $first = $this->pull();
        $this->assertFalse($first['items'][0]['hidden']);

        Carbon::setTestNow('2026-10-27 12:00:00');
        app(ModerationService::class)->hide($answer->fresh(), $this->moderator('Hider'), 'This is not appropriate.');

        $body = $this->pull('since='.urlencode($first['next_since']));
        $item = $this->item($body, 'infodot:answer:'.$answer->id);

        $this->assertTrue($item['hidden']);
        $this->assertTrue($item['reversed'], 'hiding an accepted answer removes its acceptance');
        $this->assertFalse($item['accepted_by_independent']);
        $this->assertNull($item['accepted_at']);
        $this->assertSame('2026-10-27T12:00:00Z', $body['next_since']);
    }

    public function test_an_unaccepted_answer_comes_back_as_reversed_but_not_hidden(): void
    {
        $answer = $this->acceptedAnswer();
        $since = $this->pull()['next_since'];

        Carbon::setTestNow('2026-10-27 13:00:00');
        app(AnswerService::class)->revokeAcceptance($answer->fresh(), $answer->question->user);

        $item = $this->item($this->pull('since='.urlencode($since)), 'infodot:answer:'.$answer->id);

        $this->assertTrue($item['reversed']);
        $this->assertFalse($item['hidden']);
        $this->assertFalse($item['accepted_by_independent']);
    }

    public function test_a_hidden_question_hides_its_answers_in_the_feed(): void
    {
        $answer = $this->acceptedAnswer();
        $since = $this->pull()['next_since'];

        Carbon::setTestNow('2026-10-27 14:00:00');
        app(ModerationService::class)->hide($answer->question()->first(), $this->moderator('Hider'), 'The question is not appropriate.');

        $item = $this->item($this->pull('since='.urlencode($since)), 'infodot:answer:'.$answer->id);

        $this->assertTrue($item['hidden']);
        $this->assertTrue($item['accepted_by_independent'], 'the acceptance itself stands; the question is what is hidden');
        $this->assertFalse($item['reversed']);
    }

    public function test_a_deleted_answer_is_reported_as_hidden(): void
    {
        $answer = $this->acceptedAnswer();
        $since = $this->pull()['next_since'];

        Carbon::setTestNow('2026-10-27 14:30:00');
        $answer->fresh()->delete();

        $this->assertTrue($this->item($this->pull('since='.urlencode($since)), 'infodot:answer:'.$answer->id)['hidden']);
    }

    public function test_a_hidden_solution_stays_verified_but_is_hidden_and_an_unverified_one_is_reversed(): void
    {
        $hidden = $this->verifiedSolution();
        $unverified = $this->verifiedSolution();
        $since = $this->pull()['next_since'];

        Carbon::setTestNow('2026-10-27 15:00:00');
        $mod = $this->moderator('Other checker');
        app(ModerationService::class)->hide($hidden, $mod, 'This is not appropriate.');
        app(ModerationService::class)->unverify($unverified, $mod, 'It no longer works.');

        $body = $this->pull('since='.urlencode($since));
        $h = $this->item($body, 'infodot:solution:'.$hidden->id);
        $u = $this->item($body, 'infodot:solution:'.$unverified->id);

        $this->assertTrue($h['hidden']);
        $this->assertFalse($h['reversed']);
        $this->assertTrue($h['verified_by_independent']);
        $this->assertFalse($u['hidden']);
        $this->assertTrue($u['reversed']);
        $this->assertFalse($u['verified_by_independent']);
        $this->assertNull($u['verified_at']);
    }

    public function test_only_what_changed_after_since_is_returned_and_since_is_exclusive(): void
    {
        $a = $this->acceptedAnswer('public', '2026-10-27 09:00:00');
        $b = $this->acceptedAnswer('public', '2026-10-27 10:00:00');
        $c = $this->verifiedSolution('public', '2026-10-27 11:00:00');

        $this->assertCount(3, $this->pull()['items']);
        $this->assertSame(['infodot:answer:'.$b->id, 'infodot:solution:'.$c->id], $this->refs($this->pull('since='.urlencode('2026-10-27T09:00:00Z'))));
        $this->assertSame(['infodot:solution:'.$c->id], $this->refs($this->pull('since='.urlencode('2026-10-27T10:00:00Z'))));
        $this->assertSame(['items' => [], 'next_since' => null], $this->pull('since='.urlencode('2026-10-27T11:00:00Z')));
        $this->assertContains('infodot:answer:'.$a->id, $this->refs($this->pull('since='.urlencode('2026-10-27T08:59:59Z'))));
    }

    public function test_since_accepts_an_offset_and_fractions(): void
    {
        $a = $this->acceptedAnswer('public', '2026-10-27 09:00:00');
        $b = $this->acceptedAnswer('public', '2026-10-27 10:00:00');

        // 11:00 at +02:00 is 09:00 UTC.
        $this->assertSame(['infodot:answer:'.$b->id], $this->refs($this->pull('since='.urlencode('2026-10-27T11:00:00+02:00'))));
        $this->assertSame(['infodot:answer:'.$b->id], $this->refs($this->pull('since='.urlencode('2026-10-27T09:00:00.500Z'))));
        $this->assertNotContains('infodot:answer:'.$a->id, $this->refs($this->pull('since='.urlencode('2026-10-27T09:00:00Z'))));
    }

    public function test_changing_the_topics_of_an_item_moves_it_in_the_feed(): void
    {
        $solution = $this->verifiedSolution('public', '2026-10-27 09:00:00');
        $since = $this->pull()['next_since'];

        Carbon::setTestNow('2026-10-27 16:00:00');
        app(TopicService::class)->correct($solution, $this->moderator('Corrector'), [$this->topic('billing-and-payments')->id]);

        $item = $this->item($this->pull('since='.urlencode($since)), 'infodot:solution:'.$solution->id);
        $this->assertSame(['billing-and-payments'], $item['topics']);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Order, limit and the cursor
    // ---------------------------------------------------------------------------------------------------------------

    public function test_items_come_oldest_change_first_then_answers_before_solutions_then_id(): void
    {
        $late = $this->acceptedAnswer('public', '2026-10-27 12:00:00');
        $solution = $this->verifiedSolution('public', '2026-10-27 09:00:00');
        $tieAnswer = $this->acceptedAnswer('public', '2026-10-27 09:00:00');
        $tieSolution = $this->verifiedSolution('public', '2026-10-27 09:00:00');

        $this->assertSame([
            'infodot:answer:'.$tieAnswer->id,
            'infodot:solution:'.$solution->id,
            'infodot:solution:'.$tieSolution->id,
            'infodot:answer:'.$late->id,
        ], $this->refs($this->pull()));
        $this->assertSame($this->pull(), $this->pull(), 'the same question gets the same answer');
    }

    public function test_paging_with_next_since_reads_every_item_once(): void
    {
        $expected = [];
        foreach (range(1, 5) as $i) {
            $expected[] = 'infodot:answer:'.$this->acceptedAnswer('public', sprintf('2026-10-27 09:%02d:00', $i))->id;
        }

        $seen = [];
        $since = null;
        for ($guard = 0; $guard < 10; $guard++) {
            $body = $this->pull('limit=2'.($since ? '&since='.urlencode($since) : ''));
            if ($body['items'] === []) {
                $this->assertNull($body['next_since']);
                break;
            }
            $this->assertLessThanOrEqual(2, count($body['items']));
            array_push($seen, ...$this->refs($body));
            $since = $body['next_since'];
        }

        $this->assertSame($expected, $seen);
    }

    public function test_rows_that_share_the_last_moment_are_never_skipped_by_the_cursor(): void
    {
        $tied = [];
        foreach (range(1, 3) as $i) {
            $tied[] = 'infodot:answer:'.$this->acceptedAnswer('public', '2026-10-27 09:00:00')->id;
        }
        $later = $this->acceptedAnswer('public', '2026-10-27 10:00:00');

        $first = $this->pull('limit=2');

        $this->assertSame($tied, $this->refs($first), 'all three share the cut moment, so the page grows rather than losing one');
        $this->assertSame('2026-10-27T09:00:00Z', $first['next_since']);
        $this->assertSame(['infodot:answer:'.$later->id], $this->refs($this->pull('limit=2&since='.urlencode($first['next_since']))));
    }

    public function test_the_default_limit_is_100_and_the_maximum_200(): void
    {
        $this->assertSame(100, config('knowledge.candidates.default_limit'));
        $this->assertSame(200, config('knowledge.candidates.max_limit'));

        $this->pullResponse('limit=200')->assertOk();
        $this->pullResponse('limit=1')->assertOk();
    }

    public function test_bad_limit_and_bad_since_are_refused_with_a_code(): void
    {
        foreach (['limit=0', 'limit=201', 'limit=-1', 'limit=abc', 'limit=1.5', 'limit[]=1'] as $query) {
            $this->pullResponse($query)->assertStatus(422)->assertJson(['code' => 'invalid_request'])->assertJsonStructure(['code', 'message']);
        }
        foreach (['since=yesterday', 'since=2026-10-27', 'since=2026-10-27T08:00:00', 'since=2026-13-45T00:00:00Z', 'since[]=x'] as $query) {
            $this->pullResponse($query)->assertStatus(422)->assertJson(['code' => 'invalid_request']);
        }
    }

    public function test_an_empty_since_means_from_the_beginning(): void
    {
        $answer = $this->acceptedAnswer();

        $this->assertSame(['infodot:answer:'.$answer->id], $this->refs($this->pull('since=')));
    }
}

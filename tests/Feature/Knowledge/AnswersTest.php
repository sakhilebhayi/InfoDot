<?php

namespace Tests\Feature\Knowledge;

use App\Events\Knowledge\AnswerAcceptanceRevoked;
use App\Events\Knowledge\AnswerAccepted;
use App\Events\Knowledge\AnswerPublished;
use App\Events\Knowledge\ContentReported;
use App\Events\Knowledge\QuestionSubmitted;
use App\Livewire\Answers;
use App\Livewire\Question as QuestionComponent;
use App\Models\Answer;
use App\Models\ContentReport;
use App\Models\Questions;
use App\Models\Team;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\LikeToggle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AnswersTest extends TestCase
{
    use RefreshDatabase;

    private const GOOD = 'First register the business with CIPC, then open a bank account, then ask your supplier for terms in writing.';

    private function person(string $name = 'Person'): User
    {
        $user = User::factory()->create(['name' => $name, 'email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    private function question(User $asker, string $visibility = 'public'): Questions
    {
        return Questions::create([
            'user_id' => $asker->id, 'team_id' => $asker->current_team_id, 'visibility' => $visibility,
            'question' => 'How do I register a clothing brand?', 'description' => 'I have never done this before and want the order of steps.',
        ]);
    }

    public function test_every_person_gets_a_stable_uuid(): void
    {
        $user = User::factory()->create();

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $user->uuid);
        $this->assertSame($user->uuid, $user->fresh()->uuid);
    }

    public function test_a_moderator_flag_cannot_be_set_by_mass_assignment(): void
    {
        $user = User::create(['name' => 'X', 'email' => 'x@example.test', 'password' => 'secret-password', 'is_moderator' => true]);

        $this->assertFalse($user->fresh()->isModerator());
    }

    public function test_a_person_can_answer_a_public_question_and_one_event_is_raised(): void
    {
        Event::fake([AnswerPublished::class]);
        $asker = $this->person('Asker');
        $answerer = $this->person('Answerer');
        $question = $this->question($asker);

        $answer = app(AnswerService::class)->publish($question, $answerer, self::GOOD);

        $this->assertFalse($answer->is_self_answer);
        $this->assertSame('infodot:answer:'.$answer->id, $answer->reference());
        Event::assertDispatchedTimes(AnswerPublished::class, 1);
        Event::assertDispatched(AnswerPublished::class, fn ($e) => $e->facts['author_uuid'] === $answerer->uuid && ! isset($e->facts['body']));
    }

    public function test_answering_your_own_question_is_marked_so_it_can_never_earn_acceptance_points(): void
    {
        $asker = $this->person();
        $answer = app(AnswerService::class)->publish($this->question($asker), $asker, self::GOOD);

        $this->assertTrue($answer->is_self_answer);
        $this->assertFalse(app(AnswerService::class)->canAccept($answer, $asker), 'an answerer never accepts their own answer');
    }

    public function test_short_duplicate_and_excessive_answers_are_refused(): void
    {
        $asker = $this->person();
        $answerer = $this->person();
        $question = $this->question($asker);
        $service = app(AnswerService::class);

        try {
            $service->publish($question, $answerer, 'too short');
            $this->fail('short answer accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('body', $e->errors());
        }

        $service->publish($question, $answerer, self::GOOD);

        try {
            $service->publish($question, $answerer, '  '.strtoupper(self::GOOD).'  ');
            $this->fail('the same answer in capitals was accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already posted', $e->errors()['body'][0]);
        }

        $service->publish($question, $answerer, self::GOOD.' Then keep receipts for every expense.');
        $service->publish($question, $answerer, self::GOOD.' Open a separate savings account for tax.');

        $this->expectException(ValidationException::class);
        $service->publish($question, $answerer, self::GOOD.' And a fourth different answer.');
    }

    public function test_answers_are_rate_limited_per_person(): void
    {
        $asker = $this->person();
        $answerer = $this->person();
        RateLimiter::clear('answers:'.$answerer->id);
        $service = app(AnswerService::class);

        for ($i = 0; $i < AnswerService::PER_MINUTE; $i++) {
            RateLimiter::hit('answers:'.$answerer->id, 60);
        }

        $this->expectException(ValidationException::class);
        $service->publish($this->question($asker), $answerer, self::GOOD);
    }

    public function test_a_company_only_question_cannot_be_answered_from_outside_the_company(): void
    {
        $asker = $this->person();
        $outsider = $this->person();
        $question = $this->question($asker, 'company');

        $this->expectException(HttpException::class);
        app(AnswerService::class)->publish($question, $outsider, self::GOOD);
    }

    public function test_the_asker_can_accept_another_persons_answer_and_the_event_and_status_follow(): void
    {
        Event::fake([AnswerAccepted::class]);
        $asker = $this->person();
        $answerer = $this->person();
        $question = $this->question($asker);
        $answer = app(AnswerService::class)->publish($question, $answerer, self::GOOD);

        $accepted = app(AnswerService::class)->accept($answer, $asker);

        $this->assertTrue($accepted->isAccepted());
        $this->assertSame($asker->id, $accepted->accepted_by);
        $this->assertSame(1, (int) $question->fresh()->status);
        Event::assertDispatchedTimes(AnswerAccepted::class, 1);
        Event::assertDispatched(AnswerAccepted::class, fn ($e) => $e->facts['author_uuid'] === $answerer->uuid && $e->facts['accepted_by_uuid'] === $asker->uuid && $e->facts['by_moderator'] === false);

        app(AnswerService::class)->accept($accepted, $asker);
        Event::assertDispatchedTimes(AnswerAccepted::class, 1);
    }

    public function test_a_moderator_can_accept_but_a_stranger_and_the_answerer_cannot(): void
    {
        $asker = $this->person();
        $answerer = $this->person();
        $stranger = $this->person();
        $moderator = $this->person();
        $moderator->forceFill(['is_moderator' => true])->save();
        $question = $this->question($asker);
        $service = app(AnswerService::class);
        $answer = $service->publish($question, $answerer, self::GOOD);

        $this->assertFalse($service->canAccept($answer, $stranger));
        $this->assertFalse($service->canAccept($answer, $answerer));
        $this->assertTrue($service->canAccept($answer, $moderator));

        $this->assertTrue($service->accept($answer, $moderator)->isAccepted());
    }

    public function test_a_moderator_who_is_also_the_answerer_still_cannot_accept_their_own_answer(): void
    {
        $asker = $this->person();
        $moderator = $this->person();
        $moderator->forceFill(['is_moderator' => true])->save();
        $answer = app(AnswerService::class)->publish($this->question($asker), $moderator, self::GOOD);

        $this->assertFalse(app(AnswerService::class)->canAccept($answer, $moderator));
    }

    public function test_only_one_answer_is_accepted_and_switching_revokes_the_first(): void
    {
        $asker = $this->person();
        $a = $this->person();
        $b = $this->person();
        $question = $this->question($asker);
        $service = app(AnswerService::class);
        $first = $service->publish($question, $a, self::GOOD);
        $second = $service->publish($question, $b, self::GOOD.' A different route that worked for me.');

        $service->accept($first, $asker);
        Event::fake([AnswerAcceptanceRevoked::class, AnswerAccepted::class]);
        $service->accept($second, $asker);

        $this->assertFalse($first->fresh()->isAccepted());
        $this->assertTrue($second->fresh()->isAccepted());
        $this->assertSame(1, Answer::where('question_id', $question->id)->whereNotNull('accepted_at')->count());
        Event::assertDispatched(AnswerAcceptanceRevoked::class, fn ($e) => $e->ref === $first->reference());
    }

    public function test_removing_the_acceptance_reopens_the_question_and_tells_the_ledger_to_reverse(): void
    {
        Event::fake([AnswerAcceptanceRevoked::class]);
        $asker = $this->person();
        $answerer = $this->person();
        $service = app(AnswerService::class);
        $answer = $service->accept($service->publish($this->question($asker), $answerer, self::GOOD), $asker);

        $service->revokeAcceptance($answer, $asker);

        $this->assertFalse($answer->fresh()->isAccepted());
        $this->assertSame(0, (int) $answer->question->fresh()->status);
        Event::assertDispatchedTimes(AnswerAcceptanceRevoked::class, 1);
    }

    public function test_a_flagged_answer_cannot_be_accepted(): void
    {
        $asker = $this->person();
        $answerer = $this->person();
        $service = app(AnswerService::class);
        $answer = $service->publish($this->question($asker), $answerer, self::GOOD);
        $answer->forceFill(['status' => Answer::FLAGGED])->save();

        $this->assertFalse($service->canAccept($answer, $asker));
    }

    public function test_reports_are_once_per_person_per_item_and_raise_one_event(): void
    {
        Event::fake([ContentReported::class]);
        $asker = $this->person();
        $answerer = $this->person();
        $reporter = $this->person();
        $service = app(AnswerService::class);
        $answer = $service->publish($this->question($asker), $answerer, self::GOOD);

        $service->report($answer, $reporter, 'spam');
        $service->report($answer, $reporter, 'spam', 'again');

        $this->assertSame(1, ContentReport::count());
        Event::assertDispatchedTimes(ContentReported::class, 1);
    }

    public function test_an_unknown_report_reason_and_an_outsider_reporting_a_company_answer_are_refused(): void
    {
        $asker = $this->person();
        $answerer = $this->person();
        $outsider = $this->person();
        $service = app(AnswerService::class);
        $private = $this->question($asker, 'company');
        $answer = $service->publish($private, $asker, self::GOOD);

        try {
            $service->report($answer, $answerer, 'because');
            $this->fail('unknown reason accepted');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->expectException(HttpException::class);
        $service->report($answer, $outsider, 'spam');
    }

    public function test_a_likes_toggle_never_makes_a_second_row(): void
    {
        $asker = $this->person();
        $fan = $this->person();
        $question = $this->question($asker);
        $toggle = app(LikeToggle::class);

        $this->assertTrue($toggle->toggle($question, $fan));
        $this->assertFalse($toggle->toggle($question, $fan));
        $this->assertTrue($toggle->toggle($question, $fan));

        $this->assertSame(1, DB::table('likes')->where('user_id', $fan->id)->count());
        $this->assertSame(1, $question->likes()->count());
    }

    public function test_a_second_like_row_for_the_same_person_and_item_is_impossible_at_the_database(): void
    {
        $asker = $this->person();
        $fan = $this->person();
        $question = $this->question($asker);
        app(LikeToggle::class)->toggle($question, $fan);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('likes')->insert(['user_id' => $fan->id, 'likable_type' => $question->getMorphClass(), 'likable_id' => $question->id, 'like' => 1]);
    }

    public function test_marking_solved_by_hand_is_the_askers_or_a_moderators_call_not_anyones(): void
    {
        $asker = $this->person();
        $stranger = $this->person();
        $question = $this->question($asker);

        Livewire::actingAs($stranger)->test(QuestionComponent::class, ['model' => $question, 'question' => $question])
            ->call('markedAsSolved')->assertForbidden();
        $this->assertSame(0, (int) $question->fresh()->status);

        Livewire::actingAs($asker)->test(QuestionComponent::class, ['model' => $question, 'question' => $question])
            ->call('markedAsSolved')->assertOk();
        $this->assertSame(1, (int) $question->fresh()->status);
    }

    public function test_the_answers_component_posts_accepts_and_a_stranger_cannot_accept_by_hand(): void
    {
        $asker = $this->person();
        $answerer = $this->person();
        $stranger = $this->person();
        $question = $this->question($asker);

        Livewire::actingAs($answerer)->test(Answers::class, ['question' => $question])
            ->set('body', self::GOOD)->call('publish')->assertHasNoErrors()->assertSee('Your answer is posted');
        $answer = Answer::firstOrFail();

        Livewire::actingAs($stranger)->test(Answers::class, ['question' => $question])
            ->call('accept', $answer->id)->assertForbidden();
        $this->assertFalse($answer->fresh()->isAccepted());

        Livewire::actingAs($asker)->test(Answers::class, ['question' => $question])
            ->assertSee('Accept as the solution')->call('accept', $answer->id)->assertSee('Accepted as the solution');
        $this->assertTrue($answer->fresh()->isAccepted());
    }

    public function test_the_question_page_shows_the_answers_section(): void
    {
        $asker = $this->person();
        $question = $this->question($asker);

        $this->actingAs($asker)->get('/question/view/'.$question->id)->assertOk()->assertSee('No one has answered yet');
    }

    public function test_asking_a_question_raises_one_event_with_ids_only(): void
    {
        Event::fake([QuestionSubmitted::class]);
        $asker = $this->person();

        $this->actingAs($asker)->post('/questions/add', ['question' => 'How do I price a handmade product?', 'description' => 'Materials, time and a margin, but how much?'])->assertRedirect();

        Event::assertDispatchedTimes(QuestionSubmitted::class, 1);
        Event::assertDispatched(QuestionSubmitted::class, fn ($e) => $e->facts['author_uuid'] === $asker->uuid && ! array_key_exists('question', $e->facts));
    }
}

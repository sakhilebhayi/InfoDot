<?php

namespace Tests\Feature\Knowledge;

use App\Events\Knowledge\ContentReported;
use App\Livewire\Answers;
use App\Livewire\ReportContent;
use App\Models\ContentReport;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

class ReportingAbuseTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    private function service(): AnswerService
    {
        return app(AnswerService::class);
    }

    public function test_nobody_can_report_their_own_question_answer_or_solution(): void
    {
        $author = $this->person();
        $question = $this->question($author);
        $answer = $this->answer($this->question($this->person()), $author);
        $solution = $this->solution($author);

        foreach ([$question, $answer, $solution] as $own) {
            try {
                $this->service()->report($own, $author, 'spam');
                $this->fail('reported own content');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('cannot report your own', $e->errors()['report'][0]);
            }
        }

        $this->assertSame(0, ContentReport::count());
    }

    public function test_reporting_the_same_item_again_creates_nothing_even_after_the_first_was_resolved(): void
    {
        Event::fake([ContentReported::class]);
        $reporter = $this->person();
        $question = $this->question($this->person());

        $first = $this->service()->report($question, $reporter, 'spam');
        $this->assertSame($first->id, $this->service()->report($question, $reporter, 'harmful', 'again')->id);

        app(ModerationService::class)->dismiss($question, $this->moderator(), 'Not spam, a fair question.');
        $this->assertSame($first->id, $this->service()->report($question, $reporter, 'spam')->id);

        $this->assertSame(1, ContentReport::count());
        Event::assertDispatchedTimes(ContentReported::class, 1);
    }

    public function test_the_database_also_refuses_a_second_open_report_from_the_same_person(): void
    {
        $reporter = $this->person();
        $question = $this->question($this->person());
        $row = ['reportable_type' => $question->getMorphClass(), 'reportable_id' => $question->id, 'reporter_id' => $reporter->id, 'reason' => 'spam', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()];
        DB::table('content_reports')->insert($row);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('content_reports')->insert($row);
    }

    public function test_a_person_can_send_ten_new_reports_an_hour_and_the_eleventh_is_refused_with_a_clear_message(): void
    {
        $reporter = $this->person();
        RateLimiter::clear('reports:'.$reporter->id);
        $author = $this->person();

        for ($i = 1; $i <= AnswerService::REPORTS_PER_HOUR; $i++) {
            $this->service()->report($this->question($author, 'public', "Question $i about stock"), $reporter, 'spam');
        }
        $this->assertSame(10, ContentReport::count());

        try {
            $this->service()->report($this->question($author, 'public', 'Question 11 about stock'), $reporter, 'spam');
            $this->fail('the eleventh report was accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('10 reports in the last hour', $e->errors()['report'][0]);
            $this->assertMatchesRegularExpression('/Try again in \d+ minutes?\./', $e->errors()['report'][0]);
        }
        $this->assertSame(10, ContentReport::count());

        // An item already reported is not a new report, so it does not hit the limit.
        $this->assertNotNull($this->service()->report($this->question($author)->newQuery()->first(), $reporter, 'spam'));

        // Someone else is not affected.
        $this->service()->report($this->question($author, 'public', 'Question 12 about stock'), $this->person(), 'spam');
        $this->assertSame(11, ContentReport::count());

        // After the hour it is open again.
        $this->travel(61)->minutes();
        $this->service()->report($this->question($author, 'public', 'Question 13 about stock'), $reporter, 'spam');
        $this->assertSame(12, ContentReport::count());
    }

    public function test_failed_reports_do_not_use_up_the_hourly_allowance(): void
    {
        $reporter = $this->person();
        RateLimiter::clear('reports:'.$reporter->id);
        $own = $this->question($reporter);

        for ($i = 0; $i < 15; $i++) {
            try {
                $this->service()->report($own, $reporter, 'spam');
            } catch (ValidationException) {
            }
        }

        $this->service()->report($this->question($this->person()), $reporter, 'spam');
        $this->assertSame(1, ContentReport::count());
    }

    public function test_hidden_content_cannot_be_reported_again(): void
    {
        $question = $this->question($this->person());
        app(ModerationService::class)->hide($question, $this->moderator(), 'Off topic for this community.');

        $this->expectException(ValidationException::class);
        $this->service()->report($question->fresh(), $this->person(), 'spam');
    }

    public function test_a_solution_and_a_question_can_be_reported_and_something_else_cannot(): void
    {
        $reporter = $this->person();
        $solution = $this->solution($this->person());

        $report = $this->service()->report($solution, $reporter, 'duplicate');
        $this->assertSame($solution->getMorphClass(), $report->reportable_type);

        $this->expectException(HttpException::class);
        $this->service()->report($reporter, $this->person(), 'spam');
    }

    public function test_the_answers_list_says_you_reported_it_and_shows_the_own_content_and_limit_errors(): void
    {
        $asker = $this->person();
        $answerer = $this->person();
        $reporter = $this->person();
        $question = $this->question($asker);
        $answer = $this->answer($question, $answerer);
        $own = $this->answer($question, $reporter, self::GOOD.' And keep every receipt for the tax year.');

        Livewire::actingAs($reporter)->test(Answers::class, ['question' => $question])
            ->assertSee('Report')
            ->call('startReport', $answer->id)->set('reason', 'spam')->call('report')->assertHasNoErrors()
            ->assertSee('Thanks. A moderator will look at it.')->assertSee('You reported this');

        // A hand-made call for the person's own answer is refused with a message, not a crash.
        Livewire::actingAs($reporter)->test(Answers::class, ['question' => $question])
            ->call('startReport', $own->id)->call('report')->assertHasErrors('report')->assertSee('You cannot report your own content.');

        RateLimiter::clear('reports:'.$reporter->id);
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('reports:'.$reporter->id, 3600);
        }
        $other = $this->answer($question, $this->person(), self::GOOD.' Then get a tax number from SARS.');
        Livewire::actingAs($reporter)->test(Answers::class, ['question' => $question])
            ->call('startReport', $other->id)->call('report')->assertSee('You have sent 10 reports in the last hour.');
    }

    public function test_the_report_button_on_a_question_page_reports_once_and_is_absent_for_the_author(): void
    {
        $asker = $this->person();
        $reader = $this->person();
        $question = $this->question($asker);

        $this->actingAs($asker)->get('/question/view/'.$question->id)->assertOk()->assertDontSee('Report this question');
        $this->actingAs($reader)->get('/question/view/'.$question->id)->assertOk()->assertSee('Report this question');

        Livewire::actingAs($reader)->test(ReportContent::class, ['kind' => 'question', 'itemId' => $question->id])
            ->assertSee('Report this question')->call('send')->assertHasNoErrors()->assertSee('You have reported this');
        $this->assertSame(1, ContentReport::count());

        Livewire::actingAs($asker)->test(ReportContent::class, ['kind' => 'question', 'itemId' => $question->id])
            ->call('send')->assertHasErrors('report');
        $this->assertSame(1, ContentReport::count());
    }

    public function test_the_report_button_on_a_solution_page_works_the_same_way(): void
    {
        $author = $this->person();
        $reader = $this->person();
        $solution = $this->solution($author);

        $this->actingAs($reader)->get('/solution/view/'.$solution->id)->assertOk()->assertSee('Report this solution');
        Livewire::actingAs($reader)->test(ReportContent::class, ['kind' => 'solution', 'itemId' => $solution->id])->call('send')->assertHasNoErrors();
        $this->assertSame(1, ContentReport::where('reportable_type', $solution->getMorphClass())->count());
    }
}

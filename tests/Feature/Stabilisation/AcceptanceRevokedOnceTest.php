<?php

namespace Tests\Feature\Stabilisation;

use App\Events\Knowledge\AnswerAcceptanceRevoked;
use App\Models\Answer;
use App\Services\Knowledge\AnswerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/**
 * R-49. Removing an acceptance tells the points ledger to take the award back. Two requests that both still saw the
 * answer as accepted (the asker and a moderator, or two tabs) each sent that instruction, and a late one could reset
 * the question after a different answer had been accepted. The check is now made again under a lock.
 */
class AcceptanceRevokedOnceTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    public function test_two_requests_that_both_saw_the_acceptance_take_it_back_once(): void
    {
        $asker = $this->person('Asker');
        $question = $this->question($asker);
        $answer = $this->answer($question, $this->person('Helper'));
        app(AnswerService::class)->accept($answer, $asker);

        $oneRequest = Answer::findOrFail($answer->id);
        $theOther = Answer::findOrFail($answer->id);
        Event::fake([AnswerAcceptanceRevoked::class]);

        app(AnswerService::class)->revokeAcceptance($oneRequest, $asker);
        app(AnswerService::class)->revokeAcceptance($theOther, $this->moderator());

        Event::assertDispatchedTimes(AnswerAcceptanceRevoked::class, 1);
    }

    public function test_a_late_request_does_not_reset_a_question_whose_other_answer_was_accepted_since(): void
    {
        $asker = $this->person('Asker');
        $question = $this->question($asker);
        $first = $this->answer($question, $this->person('Helper'));
        $second = $this->answer($question, $this->person('Second helper'), 'Start with the costs you cannot avoid, add the margin you need, and check that against what three competitors charge.');
        app(AnswerService::class)->accept($first, $asker);
        $late = Answer::findOrFail($first->id);

        app(AnswerService::class)->accept($second->fresh(), $asker);
        app(AnswerService::class)->revokeAcceptance($late, $asker);

        $this->assertTrue($second->fresh()->isAccepted());
        $this->assertSame(1, (int) $question->fresh()->status);
    }
}

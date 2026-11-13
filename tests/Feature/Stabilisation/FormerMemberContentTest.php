<?php

namespace Tests\Feature\Stabilisation;

use App\Models\ContentReport;
use App\Models\ModerationAction;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/**
 * R-49. A person who deletes their account leaves their public questions behind, shown as written by a former member
 * (their answers and solutions go with the account). Reporting or approving such a question read the author's id from a
 * row that no longer exists and answered with a server error. There is nobody to credit, so the action goes ahead and the points
 * ledger is told nothing.
 */
class FormerMemberContentTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    public function test_a_former_members_question_can_be_reported(): void
    {
        $author = $this->person('Leaver');
        $question = $this->question($author);
        app(DeletesUsers::class)->delete($author->fresh());

        $report = app(AnswerService::class)->report($question->fresh(), $this->person('Reporter'), 'spam');

        $this->assertSame(1, ContentReport::count());
        $this->assertTrue($report->exists);
        $this->assertSame(0, DB::table('reward_outbox')->count());
    }

    public function test_a_former_members_question_can_be_approved_by_a_moderator(): void
    {
        $author = $this->person('Leaver');
        $question = $this->question($author);
        app(DeletesUsers::class)->delete($author->fresh());

        $this->assertTrue(app(ModerationService::class)->approve($question->fresh(), $this->moderator()));

        $this->assertSame(1, ModerationAction::where('action', ModerationAction::APPROVED)->count());
        $this->assertSame(0, DB::table('reward_outbox')->count());
    }
}

<?php

namespace Tests\Feature\Knowledge;

use App\Events\Knowledge\ContributionApproved;
use App\Models\SolutionImprovement;
use App\Models\Solutions;
use App\Models\SolutionVersion;
use App\Models\Steps;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\ImprovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** R-16 phases 4-5: proposals never touch approved content; approval versions, credits, rewards; conflicts are caught. */
class SolutionImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Solutions $solution;

    private Steps $firstStep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->withPersonalTeam()->create();
        $this->solution = new Solutions;
        $this->solution->forceFill([
            'user_id' => $this->author->id, 'team_id' => $this->author->current_team_id, 'visibility' => Solutions::VISIBILITY_PUBLIC,
            'solution_title' => 'Reconcile the bank weekly', 'solution_description' => 'Do it every Monday morning.',
            'tags' => 'money', 'duration' => 1, 'duration_type' => 'hours', 'steps' => 1, 'verified_at' => now(), 'verified_by' => $this->author->id,
        ])->save();

        $this->firstStep = new Steps;
        $this->firstStep->forceFill(['user_id' => $this->author->id, 'solution_id' => $this->solution->id,
            'solution_heading' => 'Open the statement', 'solution_body' => 'Download last week.'])->save();
    }

    private function propose(User $proposer, array $overrides = []): SolutionImprovement
    {
        return app(ImprovementService::class)->propose($this->solution, $proposer, $overrides + [
            'target_kind' => 'description',
            'proposed_body' => 'Do it every Monday morning, before the post arrives, using the bank export.',
            'rationale' => 'The export removes typing errors and takes half the time.',
        ]);
    }

    public function test_a_pending_proposal_notifies_the_author_and_changes_nothing(): void
    {
        $helper = User::factory()->withPersonalTeam()->create();
        $improvement = $this->propose($helper);

        $this->assertSame('pending', $improvement->status);
        $this->assertSame('Do it every Monday morning.', $this->solution->fresh()->solution_description);
        $this->assertSame(1, (int) $this->solution->fresh()->version);
        $this->assertSame(1, UserNotice::where('user_id', $this->author->id)->where('kind', UserNotice::IMPROVEMENT_PROPOSED)->count());
    }

    public function test_approval_applies_the_change_versions_credits_and_rewards(): void
    {
        Event::fake([ContributionApproved::class]);
        $helper = User::factory()->withPersonalTeam()->create();
        $improvement = $this->propose($helper);

        app(ImprovementService::class)->approve($improvement, $this->author);

        $solution = $this->solution->fresh();
        $this->assertStringContainsString('bank export', $solution->solution_description);
        $this->assertSame(2, (int) $solution->version);
        $this->assertNull($solution->verified_at, 'the moderator verified the old content, so the mark resets');

        $versions = SolutionVersion::where('solution_id', $solution->id)->orderBy('version')->get();
        $this->assertCount(2, $versions);
        $this->assertSame('Do it every Monday morning.', $versions[0]->solution_description, 'the original stays readable');
        $this->assertNull($versions[0]->contributor_id);
        $this->assertSame($helper->id, $versions[1]->contributor_id, 'the improver is credited');
        $this->assertSame($this->author->id, $versions[1]->author_id, 'ownership never transfers');

        Event::assertDispatched(ContributionApproved::class, fn ($e) => $e->ref === 'infodot:improvement:'.$improvement->id);
        $this->assertSame(1, UserNotice::where('user_id', $helper->id)->where('kind', UserNotice::IMPROVEMENT_APPROVED)->count());
    }

    public function test_a_self_proposal_can_be_approved_but_earns_no_reward(): void
    {
        Event::fake([ContributionApproved::class]);
        $improvement = $this->propose($this->author);

        app(ImprovementService::class)->approve($improvement, $this->author);

        $this->assertSame(2, (int) $this->solution->fresh()->version);
        Event::assertNotDispatched(ContributionApproved::class);
    }

    public function test_only_the_author_decides(): void
    {
        $helper = User::factory()->withPersonalTeam()->create();
        $improvement = $this->propose($helper);

        $this->expectException(HttpException::class);
        app(ImprovementService::class)->approve($improvement, $helper);
    }

    public function test_a_stale_proposal_is_not_applied_until_reevaluated(): void
    {
        $helperA = User::factory()->withPersonalTeam()->create();
        $helperB = User::factory()->withPersonalTeam()->create();
        $first = $this->propose($helperA);
        $second = $this->propose($helperB, ['proposed_body' => 'A different wording of the Monday routine, with the paper statement kept.']);

        app(ImprovementService::class)->approve($first, $this->author);

        $this->assertTrue($second->fresh()->isStale());

        try {
            app(ImprovementService::class)->approve($second->fresh(), $this->author);
            $this->fail('a proposal written against an older version must not apply silently');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(2, (int) $this->solution->fresh()->version, 'the conflicting proposal changed nothing');
        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_reject_and_clarify_keep_content_and_tell_the_proposer(): void
    {
        $helper = User::factory()->withPersonalTeam()->create();
        $improvement = $this->propose($helper);

        app(ImprovementService::class)->requestClarification($improvement, $this->author, 'Which bank export format do you mean?');
        $this->assertSame('clarify', $improvement->fresh()->status);
        $this->assertSame(1, UserNotice::where('user_id', $helper->id)->where('kind', UserNotice::IMPROVEMENT_CLARIFY)->count());

        app(ImprovementService::class)->revise($improvement->fresh(), $helper, 'Use the CSV export from the business banking portal, then reconcile.', 'CSV opens in any spreadsheet.');
        $this->assertSame('pending', $improvement->fresh()->status);

        app(ImprovementService::class)->reject($improvement->fresh(), $this->author, 'Thanks — we standardised on OFX.');
        $this->assertSame('rejected', $improvement->fresh()->status);
        $this->assertSame('Do it every Monday morning.', $this->solution->fresh()->solution_description);
        $this->assertSame(1, UserNotice::where('user_id', $helper->id)->where('kind', UserNotice::IMPROVEMENT_REJECTED)->count());
    }

    public function test_a_new_step_proposal_appends_a_step_on_approval(): void
    {
        $helper = User::factory()->withPersonalTeam()->create();
        $improvement = app(ImprovementService::class)->propose($this->solution, $helper, [
            'target_kind' => 'new-step',
            'proposed_heading' => 'File the reconciliation',
            'proposed_body' => 'Save the reconciled sheet into the month folder so the archive stays complete.',
            'rationale' => 'The five-year record rule needs the filed copy.',
        ]);

        app(ImprovementService::class)->approve($improvement, $this->author);

        $solution = $this->solution->fresh();
        $this->assertSame(2, (int) $solution->steps);
        $this->assertSame('File the reconciliation', Steps::where('solution_id', $solution->id)->orderByDesc('id')->first()->solution_heading);
        $this->assertCount(2, SolutionVersion::where('solution_id', $solution->id)->orderByDesc('version')->first()->steps);
    }
}

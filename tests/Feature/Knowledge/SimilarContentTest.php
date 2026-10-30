<?php

namespace Tests\Feature\Knowledge;

use App\Models\Questions;
use App\Models\User;
use App\Services\Knowledge\SimilarContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R-16 phase 3: differently worded drafts surface the same problem; tenants never see each other's private content. */
class SimilarContentTest extends TestCase
{
    use RefreshDatabase;

    private function question(User $author, string $title, string $body, string $visibility = Questions::VISIBILITY_PUBLIC): Questions
    {
        $question = new Questions;
        $question->forceFill([
            'user_id' => $author->id, 'team_id' => $author->current_team_id,
            'visibility' => $visibility, 'question' => $title, 'description' => $body,
        ])->save();

        return $question;
    }

    public function test_a_reworded_draft_finds_the_existing_question_without_blocking(): void
    {
        $author = User::factory()->withPersonalTeam()->create();
        $existing = $this->question($author, 'How do I improve cash flow in my small business?',
            'Sales happen but the money runs out before I can pay suppliers at month end. Cash flow is always tight.');
        $this->question($author, 'Which colour should my logo be?', 'Brand question about colours and fonts.');

        $me = User::factory()->withPersonalTeam()->create();
        $this->actingAs($me);

        $results = $this->postJson(route('knowledge.similar'), [
            'kind' => 'question',
            'text' => 'My business makes sales but I cannot pay my suppliers before month end — the cash always runs out.',
        ])->assertOk()->json();

        $titles = collect($results)->pluck('title');
        $this->assertTrue($titles->contains($existing->question), 'the reworded cash-flow draft finds the existing cash-flow question');
        $this->assertFalse($titles->contains('Which colour should my logo be?'));
        $this->assertContains(collect($results)->firstWhere('title', $existing->question)['label'], ['very-similar', 'related']);
    }

    public function test_an_unrelated_draft_returns_nothing(): void
    {
        $author = User::factory()->withPersonalTeam()->create();
        $this->question($author, 'How do I improve cash flow in my small business?', 'Money runs out before suppliers are paid.');

        $this->actingAs(User::factory()->withPersonalTeam()->create());

        $results = $this->postJson(route('knowledge.similar'), [
            'kind' => 'question',
            'text' => 'Looking for a reliable bakkie rental near Polokwane for weekend deliveries of furniture.',
        ])->assertOk()->json();

        $this->assertSame([], $results);
    }

    public function test_another_companys_private_content_never_surfaces(): void
    {
        $other = User::factory()->withPersonalTeam()->create();
        $this->question($other, 'Cash flow crisis with our biggest client account', 'Private: client pays late and cash flow suffers badly every month.', Questions::VISIBILITY_COMPANY);

        $me = User::factory()->withPersonalTeam()->create();
        $this->actingAs($me);

        $results = $this->postJson(route('knowledge.similar'), [
            'kind' => 'question',
            'text' => 'Our biggest client pays late and the cash flow suffers every month — what can we do?',
        ])->assertOk()->json();

        $this->assertSame([], $results, 'company-only content of another tenant stays invisible to duplicate detection');
    }

    public function test_own_company_private_content_does_surface_for_its_members(): void
    {
        $me = User::factory()->withPersonalTeam()->create();
        $mine = $this->question($me, 'Supplier payments keep bouncing at month end', 'Private note: our supplier payments bounce because cash runs out.', Questions::VISIBILITY_COMPANY);

        $this->actingAs($me);

        $results = $this->postJson(route('knowledge.similar'), [
            'kind' => 'question',
            'text' => 'Why do our supplier payments keep bouncing at month end when cash runs out?',
        ])->assertOk()->json();

        $this->assertTrue(collect($results)->pluck('title')->contains($mine->question));
    }

    public function test_scoring_distinguishes_very_similar_from_related(): void
    {
        $service = app(SimilarContent::class);
        $terms = $service->terms('My business makes sales but I cannot pay my suppliers before month end');

        $this->assertGreaterThanOrEqual(SimilarContent::VERY_SIMILAR,
            $service->overlap($terms, 'Sales happen but suppliers cannot be paid before month end — paying suppliers from sales money'));
        $this->assertLessThan(SimilarContent::RELATED,
            $service->overlap($terms, 'Choosing a logo colour palette for a new brand identity'));
    }
}

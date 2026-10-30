<?php

namespace Tests\Feature\Knowledge;

use App\Livewire\Answers;
use App\Models\Mention;
use App\Models\Questions;
use App\Models\SolutionRecommendation;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\MentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** R-16 phase 2: @mentions resolve to real identities and notify properly; recommendations link originals. */
class MentionsAndRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    private function publicQuestion(User $asker): Questions
    {
        $question = new Questions;
        $question->forceFill([
            'user_id' => $asker->id, 'team_id' => $asker->current_team_id,
            'visibility' => Questions::VISIBILITY_PUBLIC, 'question' => 'How do I price a service?', 'description' => 'Details.',
        ])->save();

        return $question;
    }

    private function publicSolution(User $author, string $title = 'Price from a cost floor'): Solutions
    {
        $solution = new Solutions;
        $solution->forceFill([
            'user_id' => $author->id, 'team_id' => $author->current_team_id, 'visibility' => Solutions::VISIBILITY_PUBLIC,
            'solution_title' => $title, 'solution_description' => 'How.', 'tags' => 'pricing', 'duration' => 1, 'duration_type' => 'hours', 'steps' => 0,
        ])->save();

        return $solution;
    }

    public function test_the_mention_search_finds_people_and_only_businesses_with_public_content(): void
    {
        $me = User::factory()->withPersonalTeam()->create(['name' => 'Asker Person']);
        $expert = User::factory()->withPersonalTeam()->create(['name' => 'Thandi Expert']);

        $quietOwner = User::factory()->withPersonalTeam()->create();
        $quietBusiness = Team::forceCreate(['user_id' => $quietOwner->id, 'name' => 'Quiet Holdings', 'personal_team' => false]);

        $openOwner = User::factory()->withPersonalTeam()->create();
        $openBusiness = Team::forceCreate(['user_id' => $openOwner->id, 'name' => 'Open Construction', 'personal_team' => false]);
        $q = new Questions;
        $q->forceFill(['user_id' => $openOwner->id, 'team_id' => $openBusiness->id, 'visibility' => Questions::VISIBILITY_PUBLIC, 'question' => 'x?', 'description' => 'x'])->save();

        $this->actingAs($me);
        $results = collect(app(MentionService::class)->search($me, 'Expert'));
        $this->assertTrue($results->contains(fn ($r) => $r['type'] === 'user' && $r['name'] === 'Thandi Expert'));

        $this->assertTrue(collect(app(MentionService::class)->search($me, 'Open Const'))->contains(fn ($r) => $r['type'] === 'business'));
        $this->assertFalse(collect(app(MentionService::class)->search($me, 'Quiet Hold'))->contains(fn ($r) => $r['type'] === 'business'),
            'a business with no public content stays out of other companies\' autocomplete');

        $endpoint = $this->getJson(route('mentions.search', ['q' => 'Expert']));
        $endpoint->assertOk();
        $this->assertStringNotContainsString('@', json_encode($endpoint->json()), 'no email addresses in the payload');
    }

    public function test_mentions_on_a_question_are_recorded_once_and_notify_person_and_business_reps(): void
    {
        $asker = User::factory()->withPersonalTeam()->create();
        $expert = User::factory()->withPersonalTeam()->create();
        $owner = User::factory()->withPersonalTeam()->create();
        $admin = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->withPersonalTeam()->create();
        $business = Team::forceCreate(['user_id' => $owner->id, 'name' => 'ABC Construction', 'personal_team' => false]);
        $business->users()->attach($admin, ['role' => 'admin']);
        $business->users()->attach($member, ['role' => 'member']);
        $topic = Topic::first() ?? Topic::create(['slug' => 't', 'name' => 'Topic', 'active' => true, 'sort_order' => 10]);

        $payload = json_encode([
            ['type' => 'user', 'id' => $expert->id],
            ['type' => 'user', 'id' => $expert->id], // duplicated on purpose: one row, one notice
            ['type' => 'business', 'id' => $business->id],
        ]);

        $this->actingAs($asker)->post(route('questions.add'), [
            'question' => 'How do we stop cost overruns?',
            'description' => "@{$expert->name}, you have done this before.",
            'topics' => [$topic->id],
            'publish' => 1,
            'mentions' => $payload,
        ])->assertRedirect('questions');

        $question = Questions::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame(2, Mention::where('mentionable_id', $question->id)->count());

        $this->assertSame(1, UserNotice::where('user_id', $expert->id)->where('kind', UserNotice::MENTIONED)->count());
        $this->assertSame(1, UserNotice::where('user_id', $owner->id)->where('kind', UserNotice::MENTIONED)->count());
        $this->assertSame(1, UserNotice::where('user_id', $admin->id)->where('kind', UserNotice::MENTIONED)->count());
        $this->assertSame(0, UserNotice::where('user_id', $member->id)->count(), 'ordinary members are not notified for the business');

        $this->actingAs($expert)->get(route('questions.view', ['qid' => $question->id]))
            ->assertOk()->assertSee('Mentioned:')->assertSee('ABC Construction');
    }

    public function test_an_answer_can_recommend_existing_solutions_but_not_invisible_ones(): void
    {
        $asker = User::factory()->withPersonalTeam()->create();
        $helper = User::factory()->withPersonalTeam()->create();
        $authorA = User::factory()->withPersonalTeam()->create();
        $question = $this->publicQuestion($asker);
        $public = $this->publicSolution($authorA);

        $privateAuthor = User::factory()->withPersonalTeam()->create();
        $private = new Solutions;
        $private->forceFill([
            'user_id' => $privateAuthor->id, 'team_id' => $privateAuthor->current_team_id, 'visibility' => Solutions::VISIBILITY_COMPANY,
            'solution_title' => 'Private recipe', 'solution_description' => 'x', 'tags' => 'x', 'duration' => 1, 'duration_type' => 'hours', 'steps' => 0,
        ])->save();

        Livewire::actingAs($helper)->test(Answers::class, ['question' => $question])
            ->set('body', 'There is already a good solution for this, linked below with the steps.')
            ->set('recommendationsPayload', json_encode([['id' => $public->id], ['id' => $private->id]]))
            ->call('publish')->assertHasNoErrors();

        $this->assertSame(1, SolutionRecommendation::count(), 'the company-only solution of another team is silently not found');
        $this->assertSame($public->id, SolutionRecommendation::first()->solution_id);
        $this->assertSame(1, UserNotice::where('user_id', $authorA->id)->where('kind', UserNotice::SOLUTION_RECOMMENDED)->count());

        // The card renders on the question page for people who may see the solution.
        $this->actingAs($asker)->get(route('questions.view', ['qid' => $question->id]))
            ->assertOk()->assertSee('Recommended solution')->assertSee('Price from a cost floor');
    }

    public function test_mention_limits_hold(): void
    {
        $asker = User::factory()->withPersonalTeam()->create();
        $question = $this->publicQuestion($asker);
        $others = User::factory()->withPersonalTeam()->count(6)->create();

        $tooMany = $others->map(fn ($u) => ['type' => 'user', 'id' => $u->id])->all();

        $this->expectException(ValidationException::class);
        app(MentionService::class)->record($question, $asker, $tooMany);
    }

    public function test_the_solution_picker_endpoint_respects_visibility(): void
    {
        $me = User::factory()->withPersonalTeam()->create();
        $this->publicSolution(User::factory()->withPersonalTeam()->create(), 'Collect overdue invoices calmly');

        $other = User::factory()->withPersonalTeam()->create();
        $hidden = new Solutions;
        $hidden->forceFill([
            'user_id' => $other->id, 'team_id' => $other->current_team_id, 'visibility' => Solutions::VISIBILITY_COMPANY,
            'solution_title' => 'Collect secrets', 'solution_description' => 'x', 'tags' => 'x', 'duration' => 1, 'duration_type' => 'hours', 'steps' => 0,
        ])->save();

        $json = $this->actingAs($me)->getJson(route('solutions.picker', ['q' => 'Collect']))->assertOk()->json();
        $names = collect($json)->pluck('name');
        $this->assertTrue($names->contains('Collect overdue invoices calmly'));
        $this->assertFalse($names->contains('Collect secrets'));
    }
}

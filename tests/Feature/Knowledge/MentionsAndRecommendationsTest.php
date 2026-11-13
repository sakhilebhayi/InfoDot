<?php

namespace Tests\Feature\Knowledge;

use App\Livewire\Answers;
use App\Models\Mention;
use App\Models\Questions;
use App\Models\SolutionRecommendation;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\TeamProfile;
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
        // R-49: a business is offered only to someone who may see it, so the open one has a public page.
        $openBusiness->profileOrNew()->forceFill(['visibility' => TeamProfile::PUBLIC])->save();

        // Public content alone is not enough: this one's page is private, so it is not offered either.
        $shutOwner = User::factory()->withPersonalTeam()->create();
        $shutBusiness = Team::forceCreate(['user_id' => $shutOwner->id, 'name' => 'Shut Logistics', 'personal_team' => false]);
        $q2 = new Questions;
        $q2->forceFill(['user_id' => $shutOwner->id, 'team_id' => $shutBusiness->id, 'visibility' => Questions::VISIBILITY_PUBLIC, 'question' => 'y?', 'description' => 'y'])->save();

        $this->actingAs($me);
        $results = collect(app(MentionService::class)->search($me, 'Expert'));
        $this->assertTrue($results->contains(fn ($r) => $r['type'] === 'user' && $r['name'] === 'Thandi Expert'));

        $this->assertTrue(collect(app(MentionService::class)->search($me, 'Open Const'))->contains(fn ($r) => $r['type'] === 'business'));
        $this->assertFalse(collect(app(MentionService::class)->search($me, 'Quiet Hold'))->contains(fn ($r) => $r['type'] === 'business'),
            'a business with no public content stays out of other companies\' autocomplete');
        $this->assertFalse(collect(app(MentionService::class)->search($me, 'Shut Logi'))->contains(fn ($r) => $r['type'] === 'business'),
            'a business whose page the viewer may not open is not offered');
        $this->assertTrue(collect(app(MentionService::class)->search($shutOwner, 'Shut Logi'))->contains(fn ($r) => $r['type'] === 'business'),
            'its own people are still offered it');

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
        // R-49: the asker is a stranger to this business, so it must be one the asker may see.
        $business->profileOrNew()->forceFill(['visibility' => TeamProfile::PUBLIC])->save();
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

    public function test_a_business_the_mentioner_may_not_see_is_dropped_silently(): void
    {
        $asker = User::factory()->withPersonalTeam()->create();
        $owner = User::factory()->withPersonalTeam()->create();
        $make = function (string $name, ?string $visibility, bool $restricted = false) use ($owner): Team {
            $team = Team::forceCreate(['user_id' => $owner->id, 'name' => $name, 'personal_team' => false]);
            if ($visibility !== null) {
                $team->profileOrNew()->forceFill(['visibility' => $visibility, 'restricted_at' => $restricted ? now() : null])->save();
            }

            return $team;
        };
        $unset = $make('Unset Trading', null);
        $private = $make('Private Trading', TeamProfile::PRIVATE);
        $restricted = $make('Restricted Trading', TeamProfile::PUBLIC, true);
        $members = $make('Members Trading', TeamProfile::MEMBERS);
        $question = $this->publicQuestion($asker);

        app(MentionService::class)->record($question, $asker, array_map(
            fn (Team $team) => ['type' => 'business', 'id' => $team->id], [$unset, $private, $restricted, $members]));

        $this->assertSame([$members->id], Mention::where('mentionable_id', $question->id)->pluck('mentioned_team_id')->all(),
            'only the business a signed-in person may see is recorded');
        $this->assertSame(1, UserNotice::where('user_id', $owner->id)->where('kind', UserNotice::MENTIONED)->count());

        $this->actingAs($asker)->get(route('questions.view', ['qid' => $question->id]))->assertOk()
            ->assertSee('@Members Trading')->assertDontSee('Unset Trading')->assertDontSee('Private Trading')->assertDontSee('Restricted Trading');
    }

    public function test_the_business_s_own_people_may_mention_it_while_it_is_private_or_restricted(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $business = Team::forceCreate(['user_id' => $owner->id, 'name' => 'Inside Works', 'personal_team' => false]);
        $business->profileOrNew()->forceFill(['visibility' => TeamProfile::PRIVATE, 'restricted_at' => now()])->save();
        $question = $this->publicQuestion($owner);

        app(MentionService::class)->record($question, $owner, [['type' => 'business', 'id' => $business->id]]);

        $this->assertSame(1, Mention::where('mentioned_team_id', $business->id)->count());
    }

    public function test_a_mention_recorded_earlier_stops_naming_a_business_that_has_since_gone_private(): void
    {
        $asker = User::factory()->withPersonalTeam()->create();
        $owner = User::factory()->withPersonalTeam()->create();
        $business = Team::forceCreate(['user_id' => $owner->id, 'name' => 'Sometime Open', 'personal_team' => false]);
        $business->profileOrNew()->forceFill(['visibility' => TeamProfile::PUBLIC])->save();
        $question = $this->publicQuestion($asker);
        app(MentionService::class)->record($question, $asker, [['type' => 'business', 'id' => $business->id]]);

        $this->actingAs($asker)->get(route('questions.view', ['qid' => $question->id]))->assertOk()->assertSee('@Sometime Open');

        $business->profileOrNew()->forceFill(['visibility' => TeamProfile::PRIVATE])->save();

        $this->actingAs($asker)->get(route('questions.view', ['qid' => $question->id]))->assertOk()
            ->assertDontSee('Sometime Open')->assertDontSee('Mentioned:');
        $this->actingAs($owner)->get(route('questions.view', ['qid' => $question->id]))->assertOk()->assertSee('@Sometime Open');
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

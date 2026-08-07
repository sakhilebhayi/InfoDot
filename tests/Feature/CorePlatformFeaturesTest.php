<?php

namespace Tests\Feature;

use App\Livewire\Associates;
use App\Livewire\Comments;
use App\Livewire\Question as QuestionComponent;
use App\Models\Like;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Steps;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CorePlatformFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function createVerifiedUserWithCurrentTeam(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => Carbon::now(),
        ]);

        $team = Team::factory()->create([
            'user_id' => $user->id,
            'name' => $user->name."'s Team",
            'personal_team' => true,
        ]);

        $user->forceFill([
            'current_team_id' => $team->id,
        ])->save();

        return $user;
    }

    public function test_authenticated_user_can_create_question(): void
    {
        $user = $this->createVerifiedUserWithCurrentTeam();

        $response = $this->actingAs($user)->post('/questions/add', [
            'question' => 'How do I register a business?',
            'description' => 'Need clear steps for registration in my country.',
        ]);

        $response->assertRedirect('questions');

        $this->assertDatabaseHas('questions', [
            'user_id' => $user->id,
            'question' => 'How do I register a business?',
        ]);
    }

    public function test_authenticated_user_can_create_solution_with_steps(): void
    {
        $user = $this->createVerifiedUserWithCurrentTeam();

        $response = $this->actingAs($user)->post('/solution/add', [
            'solution_title' => 'How to register a business online',
            'solution_description' => 'A practical sequence to complete your registration.',
            'tags-input' => 'business registration, legal',
            'duration' => 7,
            'duration_type' => 'days',
            'steps' => 2,
            'solution_heading' => ['Create account', 'Submit company details'],
            'solution_body' => ['Create an account on the portal.', 'Provide required legal information and submit.'],
        ]);

        $response->assertRedirect(route('solutions'));

        $solution = Solutions::where('solution_title', 'How to register a business online')->first();

        $this->assertNotNull($solution);

        $this->assertDatabaseHas('solutions', [
            'user_id' => $user->id,
            'solution_title' => 'How to register a business online',
        ]);

        $this->assertSame(2, Steps::where('solution_id', $solution->id)->count());
    }

    public function test_question_like_can_be_toggled(): void
    {
        $user = $this->createVerifiedUserWithCurrentTeam();

        $question = Questions::create([
            'user_id' => $user->id,
            'question' => 'Question title',
            'description' => 'Question description',
            'status' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test(QuestionComponent::class, [
            'model' => $question,
            'question' => $question,
        ])->call('storeLike');

        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'likable_id' => $question->id,
            'likable_type' => 'questions',
        ]);

        Livewire::test(QuestionComponent::class, [
            'model' => $question,
            'question' => $question,
        ])->call('storeLike');

        $this->assertSame(0, Like::where('user_id', $user->id)->where('likable_id', $question->id)->count());
    }

    public function test_user_can_post_comment_on_question(): void
    {
        $user = $this->createVerifiedUserWithCurrentTeam();

        $question = Questions::create([
            'user_id' => $user->id,
            'question' => 'Question title',
            'description' => 'Question description',
            'status' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test(Comments::class, [
            'model' => $question,
            'question' => $question,
        ])
            ->set('newCommentState.body', 'This is a useful answer.')
            ->call('postComment');

        $this->assertDatabaseHas('comments', [
            'user_id' => $user->id,
            'commentable_id' => $question->id,
            'commentable_type' => 'questions',
            'body' => 'This is a useful answer.',
        ]);
    }

    public function test_user_can_follow_and_unfollow_associate(): void
    {
        $user = $this->createVerifiedUserWithCurrentTeam();

        $other = User::factory()->create([
            'email_verified_at' => Carbon::now(),
        ]);

        $this->actingAs($user);

        Livewire::test(Associates::class, [
            'model' => $other,
            'user' => $other,
        ])->call('connect');

        $this->assertDatabaseHas('associates', [
            'user_id' => $user->id,
            'associate_id' => $other->id,
        ]);

        Livewire::test(Associates::class, [
            'model' => $other,
            'user' => $other,
        ])->call('connect');

        $this->assertDatabaseMissing('associates', [
            'user_id' => $user->id,
            'associate_id' => $other->id,
            'deleted_at' => null,
        ]);
    }

    public function test_solution_search_results_page_works_on_sqlite(): void
    {
        $user = $this->createVerifiedUserWithCurrentTeam();

        Solutions::create([
            'user_id' => $user->id,
            'solution_title' => 'Register your business online',
            'solution_description' => 'Complete online registration quickly.',
            'tags' => 'registration, business',
            'duration' => 3,
            'duration_type' => 'days',
            'steps' => 3,
        ]);

        $response = $this->get('/solution-results?search=Register');

        $response->assertOk();
        $response->assertSee('Register your business online');
    }

    public function test_home_route_redirects_to_solutions(): void
    {
        $user = $this->createVerifiedUserWithCurrentTeam();

        $response = $this->actingAs($user)->get('/home');

        $response->assertRedirect(route('solutions'));
    }
}

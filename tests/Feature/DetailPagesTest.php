<?php

namespace Tests\Feature;

use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Steps;
use App\Models\Team;
use App\Models\User;
use App\Support\Related;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A question page leads with its status, details and related questions; a solution page is a
 * procedure with steps that can be ticked off. Nothing from another company leaks into either.
 */
class DetailPagesTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $name = 'Northwind'): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false, 'name' => $name]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    private function question(User $by, string $title, string $description = 'Details.', int $status = 0): Questions
    {
        return Questions::withoutSyncingToSearch(fn () => Questions::create([
            'user_id' => $by->id, 'team_id' => $by->current_team_id, 'question' => $title, 'description' => $description, 'status' => $status,
        ]));
    }

    private function solution(User $by, string $title, string $tags = 'cash, till'): Solutions
    {
        return Solutions::withoutSyncingToSearch(fn () => Solutions::create([
            'user_id' => $by->id, 'team_id' => $by->current_team_id, 'solution_title' => $title, 'solution_description' => 'How it is done.',
            'tags' => $tags, 'duration' => 2, 'duration_type' => 'hours', 'steps' => 2,
        ]));
    }

    public function test_related_uses_the_words_that_matter_and_only_what_the_person_can_see(): void
    {
        $this->assertSame(['nightly', 'backup', 'folder', 'stay', 'empty', 'sheet'], Related::words('Why does the nightly backup folder stay empty on Sheet?'), 'short and filler words are dropped');

        $mine = $this->owner();
        $rival = $this->owner('Rival Ltd');
        $asked = $this->question($mine, 'Why is the nightly backup empty?');
        $this->question($mine, 'Where do nightly backups go?');
        $this->question($rival, 'Rival nightly backup secret');

        $this->actingAs($mine);
        $related = Related::questions($asked);

        $this->assertSame(['Where do nightly backups go?'], $related->pluck('question')->all(), 'not itself, not another company');
    }

    public function test_the_question_page_shows_status_details_and_more_like_this(): void
    {
        $user = $this->owner();
        $q = $this->question($user, 'How do we count the till float?', 'It is out by R50.');
        $this->question($user, 'Who recounts the till float at night?');

        $this->actingAs($user)->get('/question/view/'.$q->id)->assertOk()
            ->assertSee('Waiting for an answer')
            ->assertSee('How do we count the till float?')
            ->assertSee('Asked by')
            ->assertSee('More like this')
            ->assertSee('Who recounts the till float at night?')
            ->assertSee('Ask another question');

        $q->forceFill(['status' => 1])->save();
        $this->actingAs($user)->get('/question/view/'.$q->id)->assertOk()->assertSee('Solved');
    }

    public function test_replies_are_counted_and_the_asker_is_marked(): void
    {
        $asker = $this->owner();
        $q = $this->question($asker, 'How do we order stock?');
        $reply = $q->comments()->make(['body' => 'Use the weekly sheet.']);
        $reply->user()->associate($asker);
        $reply->save();

        $this->actingAs($asker)->get('/question/view/'.$q->id)->assertOk()
            ->assertSee('Use the weekly sheet.')
            ->assertSee('Asker')
            ->assertSeeInOrder(['ix-thread__head', '01']);
    }

    public function test_the_solution_page_is_a_procedure_with_tickable_steps(): void
    {
        $user = $this->owner();
        $s = $this->solution($user, 'Doing the till cash-up');
        foreach (['Count the cash', 'Record the total'] as $heading) {
            Steps::forceCreate(['solution_id' => $s->id, 'user_id' => $user->id, 'solution_heading' => $heading, 'solution_body' => 'Do it carefully.']);
        }

        $html = $this->actingAs($user)->get('/solution/view/'.$s->id)->assertOk()
            ->assertSee('Count the cash')
            ->assertSee('Record the total')
            ->assertSee('Mark step 2 done')
            ->assertSee('cash')
            ->assertSee('Time it takes')
            ->getContent();

        $this->assertStringContainsString('role="checkbox"', $html);
        $this->assertStringContainsString('ix-proc-'.$s->id, $html, 'progress is remembered per solution, in the browser only');
    }

    public function test_a_solution_without_steps_says_so_and_never_runs_markup(): void
    {
        $user = $this->owner();
        $s = $this->solution($user, '<script>alert(1)</script> cash-up');

        $this->actingAs($user)->get('/solution/view/'.$s->id)->assertOk()
            ->assertSee('This solution has no written steps yet.')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_another_company_cannot_open_either_page(): void
    {
        $mine = $this->owner();
        $theirs = $this->owner('Rival Ltd');
        $q = $this->question($theirs, 'Rival pricing');
        $s = $this->solution($theirs, 'Rival playbook');

        $this->actingAs($mine)->get('/question/view/'.$q->id)->assertNotFound();
        $this->actingAs($mine)->get('/solution/view/'.$s->id)->assertNotFound();
    }
}

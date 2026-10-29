<?php

namespace Tests\Feature\Reputation;

use App\Models\ReputationAppeal;
use App\Models\ReputationSnapshot;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use App\Services\Reputation\ReputationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/** R-13 knowledge reputation: private, rule-driven, explained, appealable, never a source of permissions. */
class ReputationTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        config(['services.dopemine.secret' => null, 'reputation.enabled' => true]);
    }

    private function engine(): ReputationEngine
    {
        return app(ReputationEngine::class);
    }

    public function test_a_newcomer_is_a_spark_with_nothing_invented(): void
    {
        $r = $this->engine()->compute($this->person());

        $this->assertSame(['spark', 0], [$r['tier'], $r['total']]);
        $this->assertSame('InfoDot Venture', $r['next']['name']);
        $this->assertSame(['learning', 'contribution', 'impact', 'consistency', 'expertise', 'trust'], array_keys($r['dimensions']));
    }

    public function test_work_that_others_accepted_counts_and_help_to_yourself_does_not(): void
    {
        $asker = $this->person('Asker');
        $helper = $this->person('Helper');
        $q = $this->question($asker);
        $answer = $this->answer($q, $helper);
        app(AnswerService::class)->accept($answer, $asker);

        $own = $this->question($helper, 'public', 'My own question');
        $this->answer($own, $helper); // self-answer

        $r = $this->engine()->compute($helper);
        $this->assertSame(1.0, $r['dimensions']['impact']['evidence']['answer_accepted']);
        $this->assertSame(1, $r['dimensions']['contribution']['evidence']['answer_given'] <=> 0);
        $this->assertSame(1.0, $r['dimensions']['contribution']['evidence']['answer_given'], 'the self-answer is not counted');
    }

    public function test_repeated_acceptance_by_the_same_person_decays(): void
    {
        $asker = $this->person('Asker');
        $helper = $this->person('Helper');
        for ($i = 0; $i < 4; $i++) {
            $q = $this->question($asker, 'public', "Question number $i about invoicing");
            app(AnswerService::class)->accept($this->answer($q, $helper), $asker);
        }

        $r = $this->engine()->compute($helper);
        $this->assertSame(1.75, $r['dimensions']['impact']['evidence']['answer_accepted'], '1 + 0.5 + 0.25 + 0');
    }

    public function test_company_only_work_counts_for_nothing(): void
    {
        $asker = $this->person('Asker');
        $helper = $this->person('Helper');
        $asker->currentTeam->users()->attach($helper->id, ['role' => 'member']);
        $private = $this->question($asker, 'team', 'A private company question');
        app(AnswerService::class)->accept($this->answer($private, $helper), $asker);

        $this->assertSame(0, $this->engine()->compute($helper)['total']);
    }

    public function test_a_dimension_cannot_pass_its_cap_and_the_score_is_rule_derived(): void
    {
        $u = $this->person();
        config(['reputation.points.question_asked.each' => 1000]);

        $r = $this->engine()->compute($u);
        $this->assertLessThanOrEqual($r['dimensions']['learning']['cap'], $r['dimensions']['learning']['points']);
        $this->assertSame($this->engine()->compute($u), $r, 'same facts, same rules, same answer');
    }

    public function test_snapshots_are_stored_only_when_something_changed(): void
    {
        $u = $this->person();
        $this->engine()->snapshot($u);
        $this->engine()->snapshot($u);
        $this->assertSame(1, ReputationSnapshot::where('user_id', $u->id)->count());

        config(['reputation.version' => 2]);
        $this->engine()->snapshot($u);
        $this->assertSame(2, ReputationSnapshot::where('user_id', $u->id)->count(), 'a new rules version is a new snapshot');
    }

    public function test_the_page_is_private_explained_and_off_by_default(): void
    {
        $u = $this->person();
        $this->actingAs($u)->get('/progress')->assertOk()->assertSee('Your progress')->assertSee('InfoDot Spark')->assertSee('How it is worked out')->assertSee('no ranking');

        config(['reputation.enabled' => false]);
        $this->actingAs($u)->get('/progress')->assertNotFound();
        $this->post('/progress/appeal', ['message' => 'This is wrong'])->assertNotFound();
    }

    public function test_the_tier_name_is_never_shown_on_anyone_elses_pages(): void
    {
        $helper = $this->person('Helper');
        $this->engine()->snapshot($helper);
        $colleague = $this->person('Colleague');
        $helper->currentTeam->users()->attach($colleague->id, ['role' => 'member']);

        $this->actingAs($colleague)->get('/user/profile/'.$helper->id)->assertOk()->assertDontSee('InfoDot Spark')->assertDontSee('InfoDot Venture');
        $this->actingAs($this->person('Stranger'))->get('/user/profile/'.$helper->id)->assertNotFound();
    }

    public function test_an_appeal_is_answered_by_a_moderator_with_a_recalculation_and_a_note(): void
    {
        $u = $this->person('Asker');
        $mod = $this->moderator();

        $this->actingAs($u)->post('/progress/appeal', ['message' => 'My accepted answers are missing'])->assertRedirect();
        $this->actingAs($u)->post('/progress/appeal', ['message' => 'Second appeal while waiting'])->assertRedirect();
        $this->assertSame(1, ReputationAppeal::count(), 'one open appeal at a time');

        $this->actingAs($u)->get('/moderation/reputation')->assertForbidden();
        $appeal = ReputationAppeal::first();
        $this->actingAs($mod)->get('/moderation/reputation')->assertOk()->assertSee('My accepted answers are missing');
        $this->actingAs($mod)->post('/moderation/reputation/'.$appeal->id, ['note' => 'Checked: both answers were self-answers.'])->assertRedirect();

        $appeal->refresh();
        $this->assertSame(['reviewed', $mod->id], [$appeal->status, $appeal->resolved_by]);
        $this->actingAs($u)->get('/progress')->assertSee('Checked: both answers were self-answers.');
    }

    public function test_a_tier_gives_no_organisation_permission(): void
    {
        $owner = User::factory()->create();
        $team = \App\Models\Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $member = $this->person('Luminary');
        $team->users()->attach($member, ['role' => 'member']);
        config(['reputation.tiers.luminary.from' => 0, 'reputation.tiers.spark.from' => -1]);

        $this->assertSame('luminary', $this->engine()->compute($member)['tier']);
        foreach (['addTeamMember', 'updateTeamMember', 'removeTeamMember', 'viewLicence', 'changeLicence', 'delete'] as $ability) {
            $this->assertFalse($member->can($ability, $team), $ability);
        }
    }

    public function test_the_recalculate_command_does_nothing_while_off(): void
    {
        $this->person();
        config(['reputation.enabled' => false]);
        $this->artisan('reputation:recalculate')->assertSuccessful();
        $this->assertSame(0, ReputationSnapshot::count());

        config(['reputation.enabled' => true]);
        $this->artisan('reputation:recalculate')->assertSuccessful();
        $this->assertSame(1, ReputationSnapshot::count());
    }

    public function test_two_people_accepting_each_other_count_for_less(): void
    {
        $a = $this->person('A');
        $b = $this->person('B');
        app(AnswerService::class)->accept($this->answer($this->question($a, 'public', 'Question from A about cash flow'), $b), $a);
        app(AnswerService::class)->accept($this->answer($this->question($b, 'public', 'Question from B about stock control'), $a), $b);

        $this->assertSame(0.5, $this->engine()->compute($b)['dimensions']['impact']['evidence']['answer_accepted'], 'B was accepted by A, but A was accepted by B too');
        $this->assertSame(0.5, $this->engine()->compute($a)['dimensions']['impact']['evidence']['answer_accepted']);

        $c = $this->person('C');
        app(AnswerService::class)->accept($this->answer($this->question($c, 'public', 'Question from C about hiring'), $b), $c);
        $this->assertSame(1.5, $this->engine()->compute($b)['dimensions']['impact']['evidence']['answer_accepted'], 'help from someone who never got help back counts in full');
    }

    public function test_opting_out_deletes_what_is_stored_and_stops_the_score(): void
    {
        $u = $this->person();
        $this->actingAs($u)->get('/progress')->assertOk();
        $this->actingAs($u)->post('/progress/appeal', ['message' => 'Something looks wrong here'])->assertRedirect();
        $this->assertSame([1, 1], [ReputationSnapshot::count(), ReputationAppeal::count()]);

        $this->actingAs($u)->post('/progress/preference', ['reputation' => '0'])->assertRedirect('/progress');
        $this->assertSame([0, 0], [ReputationSnapshot::count(), ReputationAppeal::count()]);

        $u->refresh(); // a real request loads the person fresh; the test keeps one instance
        $this->actingAs($u)->get('/progress')->assertOk()->assertSee('switched off')->assertDontSee('How it is worked out');
        $this->assertNull($this->engine()->snapshot($u));
        $this->artisan('reputation:recalculate')->assertSuccessful();
        $this->assertSame(0, ReputationSnapshot::count(), 'the nightly run skips people who opted out');
        $this->actingAs($u)->post('/progress/appeal', ['message' => 'Appeal while switched off'])->assertNotFound();

        $this->actingAs($u)->post('/progress/preference', ['reputation' => '1'])->assertRedirect();
        $u->refresh();
        $this->actingAs($u)->get('/progress')->assertOk()->assertSee('How it is worked out');
        $this->actingAs($u)->post('/progress/preference', ['reputation' => 'maybe'])->assertSessionHasErrors('reputation');
    }

    public function test_a_person_can_download_only_their_own_score_data(): void
    {
        $u = $this->person('Me');
        $other = $this->person('Other');
        $this->actingAs($other)->get('/progress');
        $this->actingAs($u)->get('/progress');
        $this->actingAs($u)->post('/progress/appeal', ['message' => 'Please check my score']);

        $response = $this->actingAs($u)->get('/progress/export')->assertOk();
        $json = json_decode($response->streamedContent(), true);

        $this->assertSame($u->email, $json['person']);
        $this->assertCount(1, $json['snapshots']);
        $this->assertSame('Please check my score', $json['appeals'][0]['message']);
        $this->assertStringNotContainsString($other->email, json_encode($json));
    }
}

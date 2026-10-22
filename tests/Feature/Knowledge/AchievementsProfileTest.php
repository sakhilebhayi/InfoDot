<?php

namespace Tests\Feature\Knowledge;

use App\Models\Answer;
use App\Models\Team;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

class AchievementsProfileTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        config(['services.dopemine.secret' => null]);
    }

    /** Two people in one company, so one can open the other's profile. */
    private function colleagues(): array
    {
        $owner = $this->person('Owner');
        $viewer = $this->person('Viewer');
        $company = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $company->users()->attach($owner, ['role' => 'admin']);
        $company->users()->attach($viewer, ['role' => 'editor']);

        return [$owner, $viewer];
    }

    private function accepted(User $author, string $title = 'Which bank account suits a new brand?'): Answer
    {
        $asker = $this->person('Asker');

        return app(AnswerService::class)->accept($this->answer($this->question($asker, 'public', $title), $author), $asker);
    }

    private function profile(User $as, User $of): string
    {
        return $this->actingAs($as)->get('/user/profile/'.$of->id)->assertOk()->getContent();
    }

    // ---- the owner's own profile ---------------------------------------------------------------------------------------

    public function test_the_achievements_section_is_separate_from_the_points_panel(): void
    {
        config(['services.dopemine.url' => 'https://dopemine.test', 'services.dopemine.secret' => 'shared']);
        $me = $this->person();
        Http::fake([
            'dopemine.test/api/points/'.$me->uuid => Http::response(['balance' => 17, 'total_earned' => 25, 'history' => []]),
            'dopemine.test/*' => Http::response([], 202),
        ]);
        $this->accepted($me);

        $html = $this->profile($me, $me);

        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $points = $xpath->query('//*[contains(@class,"ix-points")]')->item(0);
        $achievements = $xpath->query('//section[@id="profile-achievements"]')->item(0);

        $this->assertNotNull($points);
        $this->assertNotNull($achievements);
        $this->assertFalse($this->contains($points, $achievements), 'the achievements section is not inside the points panel');
        $this->assertFalse($this->contains($achievements, $points), 'the points panel is not inside the achievements section');
        $this->assertStringContainsString('17</strong> you can redeem', $html);
        $this->assertStringContainsString('They are not points, cannot be redeemed, and are not a score', $html);

        // No number is shared between the two: the points balance never appears in the achievements, and milestones never in points.
        $this->assertStringNotContainsString('17', $dom->saveHTML($achievements));
        $this->assertStringNotContainsString('First accepted answer', $dom->saveHTML($points));
    }

    private function contains(\DOMNode $outer, \DOMNode $inner): bool
    {
        for ($node = $inner; $node !== null; $node = $node->parentNode) {
            if ($node->isSameNode($outer)) {
                return true;
            }
        }

        return false;
    }

    public function test_the_owner_sees_earned_milestones_with_meaning_and_date_and_the_next_ones_with_progress(): void
    {
        $me = $this->person();
        $this->accepted($me);

        $html = $this->profile($me, $me);

        $this->assertStringContainsString('First accepted answer', $html);
        $this->assertStringContainsString('Someone else accepted one of your answers as the solution to their problem.', $html);
        $this->assertStringContainsString('Reached '.now()->format('j M Y'), $html);
        $this->assertStringContainsString('Next milestones', $html);
        $this->assertStringContainsString('1 of 5 accepted answers', $html);
        $this->assertStringContainsString('1 of 10 answers given', $html);
        $this->assertStringContainsString('0 of 1 questions asked', $html);
        $this->assertStringContainsString('<progress', $html);
    }

    public function test_the_wording_is_plain_with_no_urgency_streaks_or_ranking(): void
    {
        $me = $this->person();
        $this->accepted($me);

        $text = strtolower(strip_tags($this->profile($me, $me)));
        $section = substr($text, strpos($text, 'achievements'));

        foreach (['streak', 'leaderboard', 'rank ', 'hurry', 'expires', 'lose your', 'don\'t miss', 'top '] as $bad) {
            $this->assertStringNotContainsString($bad, substr($section, 0, 2500), $bad);
        }
    }

    public function test_a_milestone_that_was_taken_back_is_simply_not_listed_and_nothing_alarming_is_said(): void
    {
        $me = $this->person();
        $mod = $this->moderator();
        $answer = $this->accepted($me);
        app(ModerationService::class)->hide($answer, $mod, 'This answer is wrong and misleading.');

        $html = $this->profile($me, $me);

        $this->assertStringNotContainsString('data-achievement="first_accepted"', $html);
        $this->assertStringNotContainsString('data-achievement="first_answer"', $html);
        $this->assertStringContainsString('No milestones yet', $html);
        $this->assertStringNotContainsString('revoked', strtolower(substr($html, strpos($html, 'profile-achievements'), 3000)));
        $this->assertStringContainsString('0 of 1 accepted answers', $html);
    }

    public function test_the_owner_sees_the_state_of_sharing_and_a_one_sentence_explanation(): void
    {
        $me = $this->person();

        $html = $this->profile($me, $me);

        $this->assertStringContainsString('Show my achievements and public contributions to other people', $html);
        $this->assertStringContainsString('no points, no private or company questions, no moderation history', $html);
        $this->assertStringContainsString('Right now this is <strong>off</strong>', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }

    // ---- the privacy switch ---------------------------------------------------------------------------------------------

    public function test_the_toggle_requires_sign_in_and_a_csrf_token(): void
    {
        $this->post('/user/profile/achievements-visibility', ['achievements_public' => '1'])->assertRedirect('/login');

        $me = $this->person();
        // CSRF verification is skipped while unit tests run: run the request in production mode, where it is active.
        $this->app['env'] = 'production';
        $this->actingAs($me)->post('/user/profile/achievements-visibility', ['achievements_public' => '1'])->assertStatus(419);
        $this->assertFalse($me->fresh()->achievements_public);
    }

    public function test_the_toggle_turns_sharing_on_and_off_and_changes_only_the_signed_in_row(): void
    {
        $me = $this->person();
        $other = $this->person();

        $this->actingAs($me)->post('/user/profile/achievements-visibility', ['achievements_public' => '1', 'user_id' => $other->id, 'id' => $other->id])
            ->assertRedirect(route('profile.show', ['id' => $me->id]).'#profile-achievements')
            ->assertSessionHas('achievements_notice', 'Other people can now see your achievements and public contributions.');
        $this->assertTrue($me->fresh()->achievements_public);
        $this->assertFalse($other->fresh()->achievements_public, 'someone else\'s row is never touched');

        $this->actingAs($me)->post('/user/profile/achievements-visibility', ['achievements_public' => '0'])
            ->assertSessionHas('achievements_notice', 'Only you can see your achievements now.');
        $this->assertFalse($me->fresh()->achievements_public);
    }

    public function test_the_toggle_refuses_anything_but_on_or_off(): void
    {
        $me = $this->person();

        $this->actingAs($me)->post('/user/profile/achievements-visibility', ['achievements_public' => 'maybe'])->assertSessionHasErrors('achievements_public');
        $this->actingAs($me)->post('/user/profile/achievements-visibility', [])->assertSessionHasErrors('achievements_public');
        $this->assertFalse($me->fresh()->achievements_public);
    }

    // ---- what other people see ------------------------------------------------------------------------------------------

    public function test_other_people_see_no_achievements_section_at_all_until_the_owner_shares(): void
    {
        [$owner, $viewer] = $this->colleagues();
        $this->accepted($owner);

        $html = $this->profile($viewer, $owner);

        $this->assertStringNotContainsString('profile-achievements', $html);
        $this->assertStringNotContainsString('First accepted answer', $html);
        $this->assertStringNotContainsString('Which bank account suits a new brand?', strip_tags(substr($html, strpos($html, 'profile-contribution'), 1500)));
    }

    public function test_once_shared_other_people_see_milestones_and_the_public_portfolio_but_no_progress_or_toggle(): void
    {
        [$owner, $viewer] = $this->colleagues();
        $this->accepted($owner, 'Which bank account suits a new brand?');
        $mod = $this->moderator();
        $solution = $this->solution($owner, 'public', 'Register a company with CIPC');
        app(ModerationService::class)->verify($solution, $mod);
        $owner->forceFill(['achievements_public' => true])->save();

        $html = $this->profile($viewer, $owner);

        $this->assertStringContainsString('profile-achievements', $html);
        $this->assertStringContainsString('First accepted answer', $html);
        $this->assertStringContainsString('First verified solution', $html);
        $this->assertStringContainsString('Answers accepted as the solution', $html);
        $this->assertStringContainsString('Which bank account suits a new brand?', $html);
        $this->assertStringContainsString('Verified solutions', $html);
        $this->assertStringContainsString('Register a company with CIPC', $html);
        $this->assertStringNotContainsString('Next milestones', $html, 'progress is for the owner');
        $this->assertStringNotContainsString('achievements-visibility', $html, 'no switch on someone else\'s profile');
        $this->assertStringNotContainsString('Show my achievements', $html);
    }

    public function test_company_only_and_hidden_work_never_appears_in_what_others_see(): void
    {
        [$owner, $viewer] = $this->colleagues();
        $mod = $this->moderator();
        $owner->forceFill(['achievements_public' => true])->save();

        $this->accepted($owner, 'A hidden-later public question');
        $hidden = Answer::where('user_id', $owner->id)->first();
        app(ModerationService::class)->hide($hidden, $mod, 'This answer is wrong and misleading.');

        // Company-only question, answer accepted by the asker: confidential, must not show.
        $asker = $this->person('Asker2');
        $private = $this->question($asker, 'company', 'Our confidential supplier terms');
        Answer::create(['question_id' => $private->id, 'user_id' => $owner->id, 'body' => self::GOOD, 'body_hash' => 'p', 'status' => 'published'])
            ->forceFill(['accepted_at' => now(), 'accepted_by' => $asker->id])->save();

        // Company-only verified solution.
        $companySolution = $this->solution($owner, 'company', 'Internal payroll procedure');
        $companySolution->forceFill(['verified_at' => now(), 'verified_by' => $mod->id])->save();

        // Public verified solution that is later hidden.
        $later = $this->solution($owner, 'public', 'Hidden verified solution');
        app(ModerationService::class)->verify($later, $mod);
        app(ModerationService::class)->hide($later->refresh(), $mod, 'The procedure is unsafe.');

        $html = $this->profile($viewer, $owner);
        $section = substr($html, strpos($html, 'id="profile-achievements"'));
        $section = substr($section, 0, strpos($section, '</section>'));

        foreach (['A hidden-later public question', 'Our confidential supplier terms', 'Internal payroll procedure', 'Hidden verified solution', 'First accepted answer', 'First verified solution'] as $secret) {
            $this->assertStringNotContainsString($secret, $section, $secret.' must not be in the shared section');
        }
        $this->assertStringNotContainsString('Public contributions', $section);
    }

    public function test_the_shared_section_carries_no_points_moderation_notes_or_private_text(): void
    {
        config(['services.dopemine.url' => 'https://dopemine.test', 'services.dopemine.secret' => 'shared']);
        Http::fake(['dopemine.test/*' => Http::response([], 202)]);
        [$owner, $viewer] = $this->colleagues();
        $mod = $this->moderator();
        $this->accepted($owner, 'A public question about banking');
        $other = $this->accepted($owner, 'Another public question');
        app(ModerationService::class)->hide($other, $mod, 'SECRET-MODERATION-NOTE about this answer');
        $owner->forceFill(['achievements_public' => true])->save();

        $html = $this->profile($viewer, $owner);
        $section = substr($html, strpos($html, 'id="profile-achievements"'));
        $section = substr($section, 0, strpos($section, '</section>'));

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/points/'), 'viewing someone else asks the ledger nothing');
        $this->assertStringContainsString('A public question about banking', $section);
        $this->assertStringNotContainsString('SECRET-MODERATION-NOTE', $html);
        $this->assertStringNotContainsString('Another public question', $section, 'hidden work is not shown');
        foreach (['Dot Points', 'points', 'redeem', 'moderat', 'appeal', 'hidden', 'company'] as $word) {
            // The explanation in the owner's view mentions these; the shared view must not.
            $this->assertStringNotContainsString($word, strtolower(strip_tags(preg_replace('/<p class="ix-ach__note">.*?<\/p>/s', '', $section))), $word);
        }
    }

    public function test_the_portfolio_is_capped_and_says_how_many_there_are(): void
    {
        [$owner, $viewer] = $this->colleagues();
        $owner->forceFill(['achievements_public' => true])->save();
        $asker = $this->person('Asker3');

        foreach (range(1, 12) as $i) {
            $q = $this->question($asker, 'public', 'Public question '.$i);
            Answer::create(['question_id' => $q->id, 'user_id' => $owner->id, 'body' => self::GOOD, 'body_hash' => 'n'.$i, 'status' => 'published'])
                ->forceFill(['accepted_at' => now()->subMinutes(20 - $i), 'accepted_by' => $asker->id])->save();
        }

        $html = $this->profile($viewer, $owner);

        $this->assertStringContainsString('Latest 10 of 12', $html);
        $this->assertStringContainsString('Public question 12', $html);
        $this->assertStringNotContainsString('Public question 1<', $html);
    }

    public function test_a_stranger_still_cannot_open_the_profile_at_all(): void
    {
        $owner = $this->person();
        $owner->forceFill(['achievements_public' => true])->save();
        $stranger = $this->person();

        $this->actingAs($stranger)->get('/user/profile/'.$owner->id)->assertNotFound();
    }
}

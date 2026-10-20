<?php

namespace Tests\Feature\Knowledge;

use App\Models\Answer;
use App\Models\Questions;
use App\Models\Team;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use App\Support\Knowledge\ContributionStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProfileContributionTest extends TestCase
{
    use RefreshDatabase;

    private const GOOD = 'First register the business with CIPC, then open a bank account, then ask your supplier for terms in writing.';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
    }

    private function person(?Team $joinTeam = null): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    private function question(User $asker, string $visibility = 'public'): Questions
    {
        return Questions::create(['user_id' => $asker->id, 'team_id' => $asker->current_team_id, 'visibility' => $visibility, 'question' => 'How do I register a clothing brand?', 'description' => 'Steps please.']);
    }

    private function configure(): void
    {
        config(['services.dopemine.url' => 'https://dopemine.test', 'services.dopemine.secret' => 'shared']);
    }

    public function test_the_owner_sees_their_redeemable_balance_expiry_and_history_as_separate_things(): void
    {
        $this->configure();
        $me = $this->person();
        Http::fake(['dopemine.test/api/points/'.$me->uuid => Http::response([
            'balance' => 17, 'total_earned' => 25, 'expiring_soon' => ['points' => 5, 'first_at' => now()->addDays(10)->toIso8601String()], 'next_expiry' => now()->addDays(10)->toIso8601String(),
            'history' => [
                ['at' => now()->toIso8601String(), 'type' => 'award', 'points' => 10, 'label' => 'Answer accepted as the solution', 'rule' => 'infodot.answer.accepted', 'rule_version' => 1],
                ['at' => now()->subDay()->toIso8601String(), 'type' => 'redeem', 'points' => -8, 'label' => 'Redeemed: AI boost', 'rule' => null, 'rule_version' => null],
            ],
        ])]);

        $html = $this->actingAs($me)->get('/user/profile/'.$me->id)->assertOk()->getContent();

        $this->assertStringContainsString('17</strong> you can redeem', $html);
        $this->assertStringContainsString('no cash value', $html);
        $this->assertStringContainsString('5 lapse by', $html);
        $this->assertStringContainsString('25 earned in total', $html);
        $this->assertStringContainsString('Answer accepted as the solution', $html);
        $this->assertStringContainsString('spent', $html);
        $this->assertStringContainsString('Questions asked', $html);
    }

    public function test_someone_else_looking_at_a_profile_never_triggers_a_points_lookup_or_sees_points(): void
    {
        $this->configure();
        $owner = $this->person();
        $viewer = $this->person();
        $company = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $company->users()->attach($owner, ['role' => 'admin']);
        $company->users()->attach($viewer, ['role' => 'editor']);

        $html = $this->actingAs($viewer)->get('/user/profile/'.$owner->id)->assertOk()->getContent();

        Http::assertNothingSent();
        $this->assertStringNotContainsString('Dot Points', $html);
        $this->assertStringContainsString('public questions and answers only', $html);
    }

    public function test_when_the_points_service_is_down_the_profile_says_so_instead_of_showing_zero(): void
    {
        $this->configure();
        $me = $this->person();
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->actingAs($me)->get('/user/profile/'.$me->id)->assertOk()
            ->assertSee('unavailable right now')->assertDontSee('you can redeem');
    }

    public function test_when_points_are_not_configured_the_profile_says_they_are_not_switched_on(): void
    {
        config(['services.dopemine.secret' => null]);
        $me = $this->person();

        $this->actingAs($me)->get('/user/profile/'.$me->id)->assertOk()->assertSee('not switched on yet');
        Http::assertNothingSent();
    }

    public function test_counts_come_from_infodot_and_a_self_accepted_answer_never_counts_as_help_given(): void
    {
        config(['services.dopemine.secret' => null]);
        $me = $this->person();
        $other = $this->person();
        $service = app(AnswerService::class);
        $mine = $this->question($me);
        $theirs = $this->question($other);

        $service->publish($mine, $me, self::GOOD);                 // answered my own question: counts as an answer, never as accepted help
        $helpful = $service->publish($theirs, $me, self::GOOD);    // helped someone else
        $service->accept($helpful, $other);

        $stats = ContributionStats::for($me, true);

        $this->assertSame(['questions' => 1, 'answers' => 2, 'accepted' => 1], $stats);
    }

    public function test_other_people_only_see_counts_of_public_work(): void
    {
        $me = $this->person();
        $other = $this->person();
        $public = $this->question($other);
        $private = $this->question($other, 'company');
        $this->question($me, 'company');
        $this->question($me);
        Answer::create(['question_id' => $public->id, 'user_id' => $me->id, 'body' => self::GOOD, 'body_hash' => 'a', 'status' => 'published']);
        Answer::create(['question_id' => $private->id, 'user_id' => $me->id, 'body' => self::GOOD.' x', 'body_hash' => 'b', 'status' => 'published']);

        $this->assertSame(['questions' => 1, 'answers' => 1, 'accepted' => 0], ContributionStats::for($me, false));
        $this->assertSame(['questions' => 2, 'answers' => 2, 'accepted' => 0], ContributionStats::for($me, true));
    }
}

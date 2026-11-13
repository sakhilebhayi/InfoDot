<?php

namespace Tests\Feature;

use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use App\Support\GettingStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The "Getting started" block on Home (R-49): a new person is shown the few things to do first, each row read from what
 * is really in the database, and the block goes away when everything is done or when the person puts it away.
 */
class GettingStartedTest extends TestCase
{
    use RefreshDatabase;

    /** A person as registration leaves them: verified, with a personal workspace and nothing else. */
    private function newcomer(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now(), 'name' => 'Nomvula Newcomer']);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true, 'name' => "Nomvula's Team"]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    private function business(User $owner, string $name = 'Northwind Finance'): Team
    {
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => $name]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        return $team;
    }

    /** @return array<string, array{key: string, title: string, note: string, done: bool, url: string, action: string}> */
    private function steps(User $user): array
    {
        return collect(GettingStarted::steps($user->fresh()))->keyBy('key')->all();
    }

    /** The markup of one row of the block, by its step. */
    private function row(string $html, string $key): string
    {
        $this->assertSame(1, preg_match('#<li[^>]*data-step="'.$key.'".*?</li>#s', $html, $m), 'no row for '.$key);

        return $m[0];
    }

    public function test_a_new_person_sees_the_block_with_every_step_open_and_a_link_to_the_real_page(): void
    {
        $user = $this->newcomer();

        $html = $this->actingAs($user)->get('/home')->assertOk()
            ->assertSee('Getting started')->assertSee('0 of 4 done')->getContent();

        $this->assertSame(1, substr_count($html, 'class="ix-start"'));
        foreach ([
            'photo' => [route('profile.edit').'#profile', 'Add a profile picture'],
            'contribute' => [route('seek'), 'Ask a first question or write a first solution'],
            'business' => [route('teams.create'), 'Create your business and list it'],
            'platform' => [route('ecosystem.widget'), 'Open a platform'],
        ] as $key => [$url, $title]) {
            $row = $this->row($html, $key);
            $this->assertStringContainsString($title, $row);
            $this->assertStringContainsString('href="'.$url.'"', $row);
            $this->assertStringNotContainsString('ix-start__done', $row);
        }

        // Inviting is not offered to someone with no business to invite people into.
        $this->assertStringNotContainsString('data-step="invite"', $html);

        // Every link in the block opens a page that exists for this person.
        foreach (GettingStarted::steps($user) as $step) {
            $this->get(strtok($step['url'], '#'))->assertOk();
        }
    }

    public function test_each_step_is_read_from_what_the_database_holds(): void
    {
        $user = $this->newcomer();
        $this->assertSame(['photo' => false, 'contribute' => false, 'business' => false, 'platform' => false], array_map(fn ($s) => $s['done'], $this->steps($user)));

        // A photo on the account.
        $user->forceFill(['profile_photo_path' => 'profile-photos/abc.jpg'])->save();
        $this->assertTrue($this->steps($user)['photo']['done']);

        // A question of their own; and, separately, a solution counts just as well.
        $question = new Questions(['user_id' => $user->id, 'team_id' => $user->current_team_id, 'question' => 'How do we price a call-out?', 'description' => 'We charge by the hour today.', 'status' => 0]);
        $question->visibility = Questions::VISIBILITY_COMPANY;
        $question->save();
        $this->assertTrue($this->steps($user)['contribute']['done']);

        $other = $this->newcomer();
        $solution = new Solutions(['user_id' => $other->id, 'team_id' => $other->current_team_id, 'solution_title' => 'Close the month', 'solution_description' => 'Steps.', 'duration' => 1, 'duration_type' => 'hours', 'steps' => 0]);
        $solution->visibility = Solutions::VISIBILITY_COMPANY;
        $solution->save();
        $this->assertTrue($this->steps($other)['contribute']['done']);
        $this->assertFalse($this->steps($other)['photo']['done'], 'one person\'s photo is not another\'s');

        // Someone else's question does nothing for this person.
        $third = $this->newcomer();
        $this->assertFalse($this->steps($third)['contribute']['done']);
    }

    public function test_the_business_step_says_what_is_missing_from_the_listing_and_then_that_it_is_listed(): void
    {
        $user = $this->newcomer();
        $team = $this->business($user);

        // A business exists but is private and has no description: not listed, and the row says why.
        $step = $this->steps($user)['business'];
        $this->assertFalse($step['done']);
        $this->assertSame('List Northwind Finance in the directory', $step['title']);
        $this->assertStringContainsString('say what the business does and choose who may see it', $step['note']);
        $this->assertSame(route('business.edit', $team), $step['url']);
        $this->assertFalse($team->fresh()->listing()['listed']);

        // Described, but still for the team only.
        $team->profileOrNew()->forceFill(['description' => 'Bookkeeping for small firms.'])->save();
        $this->assertStringContainsString('until you choose who may see it', $this->steps($user)['business']['note']);

        // Open to anyone, but the description is taken away again.
        $team->profile->forceFill(['description' => null, 'visibility' => TeamProfile::PUBLIC])->save();
        $this->assertSame('Not listed yet: say what the business does.', $this->steps($user)['business']['note']);

        // Listed: the step is done, and it agrees with Team::listing().
        $team->profile->forceFill(['description' => 'Bookkeeping for small firms.'])->save();
        $step = $this->steps($user)['business'];
        $this->assertTrue($team->fresh()->listing()['listed']);
        $this->assertTrue($step['done']);
        $this->assertSame('Northwind Finance is listed', $step['title']);

        $row = $this->row($this->actingAs($user->fresh())->get('/home')->assertOk()->getContent(), 'business');
        $this->assertStringContainsString('ix-start__done', $row);
        $this->assertStringContainsString('Northwind Finance is listed', $row);
        $this->assertStringNotContainsString('<a ', $row, 'a finished row has nothing left to open');
    }

    public function test_inviting_is_offered_only_to_someone_who_manages_a_business_and_is_done_by_a_real_invitation_or_member(): void
    {
        $user = $this->newcomer();
        $this->assertArrayNotHasKey('invite', $this->steps($user));

        $team = $this->business($user);
        $step = $this->steps($user)['invite'];
        $this->assertFalse($step['done']);
        $this->assertSame(route('teams.show', $team).'#members', $step['url']);

        // An invitation that has gone out counts.
        $team->teamInvitations()->create(['email' => 'colleague@northwind.test', 'role' => 'member']);
        $this->assertTrue($this->steps($user)['invite']['done']);

        // So does a colleague who is already in the business.
        $second = $this->newcomer();
        $other = $this->business($second, 'Harbour Freight');
        $this->assertFalse($this->steps($second)['invite']['done']);
        $other->users()->attach($this->newcomer(), ['role' => 'member']);
        $this->assertTrue($this->steps($second)['invite']['done']);

        // A member with no say over the business is not asked to invite into it.
        $member = $this->newcomer();
        $team->users()->attach($member, ['role' => 'member']);
        $member->forceFill(['current_team_id' => $team->id])->save();
        $this->assertArrayNotHasKey('invite', $this->steps($member));
        $this->assertSame(route('teams.create'), $this->steps($member)['business']['url']);
    }

    public function test_opening_a_platform_is_noted_once_when_the_person_is_really_sent_there(): void
    {
        config(['ecosystem.platforms.files.url' => 'https://files.infodot.co.za', 'ecosystem.platforms.files.active' => true]);
        $user = $this->newcomer();

        // Looking at the register is not opening a platform.
        $this->actingAs($user)->get(route('ecosystem.widget'))->assertOk();
        $this->assertNull($user->fresh()->platform_opened_at);
        $this->assertFalse($this->steps($user)['platform']['done']);

        // A platform that cannot be opened is not counted either.
        $this->actingAs($user)->get('/go/no-such-platform');
        $this->assertNull($user->fresh()->platform_opened_at);

        Carbon::setTestNow('2026-11-13 10:00:00');
        $this->actingAs($user)->get('/go/files')->assertOk();
        $first = $user->fresh()->platform_opened_at;
        $this->assertNotNull($first);
        $this->assertTrue($this->steps($user)['platform']['done']);

        // The first time is kept; later opens do not move it.
        Carbon::setTestNow('2026-11-14 10:00:00');
        $this->actingAs($user->fresh())->get('/go/files')->assertOk();
        $this->assertTrue($first->equalTo($user->fresh()->platform_opened_at));
        Carbon::setTestNow();
    }

    public function test_done_steps_show_done_and_the_count_follows(): void
    {
        $user = $this->newcomer();
        $user->forceFill(['profile_photo_path' => 'profile-photos/abc.jpg', 'platform_opened_at' => now()])->save();

        $html = $this->actingAs($user->fresh())->get('/home')->assertOk()->assertSee('2 of 4 done')->getContent();

        foreach (['photo', 'platform'] as $key) {
            $row = $this->row($html, $key);
            $this->assertStringContainsString('class="is-done"', $row);
            $this->assertStringContainsString('<span class="ix-start__done">Done</span>', $row);
        }
        foreach (['contribute', 'business'] as $key) {
            $this->assertStringNotContainsString('ix-start__done', $this->row($html, $key));
        }
    }

    public function test_the_block_is_absent_for_someone_who_has_done_everything(): void
    {
        $user = $this->newcomer();
        $team = $this->business($user);
        $team->profileOrNew()->forceFill(['description' => 'Bookkeeping for small firms.', 'visibility' => TeamProfile::PUBLIC])->save();
        $team->teamInvitations()->create(['email' => 'colleague@northwind.test', 'role' => 'member']);
        $user->forceFill(['profile_photo_path' => 'profile-photos/abc.jpg', 'platform_opened_at' => now()])->save();
        $question = new Questions(['user_id' => $user->id, 'team_id' => $team->id, 'question' => 'How do we price a call-out?', 'description' => 'By the hour today.', 'status' => 0]);
        $question->visibility = Questions::VISIBILITY_COMPANY;
        $question->save();

        $this->assertNotContains(false, array_map(fn ($s) => $s['done'], $this->steps($user)));
        $this->assertFalse(GettingStarted::showsFor($user->fresh()));
        $this->actingAs($user->fresh())->get('/home')->assertOk()->assertDontSee('Getting started')->assertDontSee('ix-start', false);

        // Nothing was stored to make it go away, so it comes back if a step is undone.
        $this->assertNull($user->fresh()->getting_started_dismissed_at);
        $user->forceFill(['profile_photo_path' => null])->save();
        $this->actingAs($user->fresh())->get('/home')->assertOk()->assertSee('Getting started')->assertSee('4 of 5 done');
    }

    public function test_putting_it_away_is_remembered_for_that_person_only(): void
    {
        Carbon::setTestNow('2026-11-13 09:00:00');
        $user = $this->newcomer();
        $other = $this->newcomer();

        $this->actingAs($user)->post(route('getting-started.dismiss'))->assertRedirect(route('dashboard'));
        $this->assertSame('2026-11-13 09:00:00', $user->fresh()->getting_started_dismissed_at->toDateTimeString());

        $this->actingAs($user->fresh())->get('/home')->assertOk()->assertDontSee('ix-start', false);

        // Still away on a later day, and a second press does not move the date.
        Carbon::setTestNow('2026-12-01 09:00:00');
        $this->actingAs($user->fresh())->post(route('getting-started.dismiss'))->assertRedirect(route('dashboard'));
        $this->assertSame('2026-11-13 09:00:00', $user->fresh()->getting_started_dismissed_at->toDateTimeString());
        $this->actingAs($user->fresh())->get('/home')->assertOk()->assertDontSee('ix-start', false);
        Carbon::setTestNow();

        // Somebody else still has theirs, and the steps themselves were never stored.
        $this->assertNull($other->fresh()->getting_started_dismissed_at);
        $this->actingAs($other->fresh())->get('/home')->assertOk()->assertSee('Getting started');
        $this->assertFalse($this->steps($user)['photo']['done']);
    }

    public function test_the_person_is_told_it_was_put_away_in_a_status_message(): void
    {
        $user = $this->newcomer();

        $this->actingAs($user)->followingRedirects()->post(route('getting-started.dismiss'))->assertOk()
            ->assertSee('<p class="ix-alert" role="status">Getting started is put away.', false)
            ->assertDontSee('class="ix-start"', false);
    }

    public function test_nothing_is_shown_to_or_changed_by_a_guest(): void
    {
        $this->get('/home')->assertRedirect(route('login'));
        $this->post(route('getting-started.dismiss'))->assertRedirect(route('login'));
        $this->get('/')->assertOk()->assertDontSee('Getting started')->assertDontSee('ix-start', false);

        // An unverified person is sent to verify first.
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->post(route('getting-started.dismiss'))->assertRedirect(route('verification.notice'));
        $this->assertNull($unverified->fresh()->getting_started_dismissed_at);
    }

    public function test_verifying_an_email_lands_on_home_with_the_block_and_a_confirmation(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Nomvula Newcomer']);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->actingAs($user)->get($link)->assertRedirect(route('dashboard', ['verified' => 1]));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->actingAs($user->fresh())->get(route('dashboard', ['verified' => 1]))->assertOk()
            ->assertSee('<p class="ix-alert" role="status">Your email address is confirmed.', false)
            ->assertSee('Getting started')->assertSee('0 of 4 done');

        // The confirmation is for that arrival only.
        $this->actingAs($user->fresh())->get('/home')->assertOk()->assertDontSee('Your email address is confirmed.');
    }

    public function test_the_two_columns_are_nullable_timestamps_and_the_migration_can_be_run_twice_and_back(): void
    {
        foreach (['getting_started_dismissed_at', 'platform_opened_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('users', $column));
            $this->assertNull(User::factory()->create()->fresh()->{$column});
        }

        $migration = require database_path('migrations/2026_11_13_120000_add_getting_started_to_users_table.php');
        $migration->up(); // guarded: a second run changes nothing and does not fail
        $this->assertTrue(Schema::hasColumn('users', 'getting_started_dismissed_at'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'getting_started_dismissed_at'));
        $this->assertFalse(Schema::hasColumn('users', 'platform_opened_at'));
        $migration->down(); // and so is taking it away twice

        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'platform_opened_at'));
    }
}

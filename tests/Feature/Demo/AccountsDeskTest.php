<?php

namespace Tests\Feature\Demo;

use App\Models\AccountSuspensionEvent;
use App\Models\Team;
use App\Models\User;
use App\Services\Accounts\AccountSuspension;
use App\Services\Demo\DemoRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R-49: the moderators' accounts desk. A moderator finds a person, reads the trail and suspends or restores the
 * account; the rules about who may act on whom hold on the server, whatever the page offers.
 */
class AccountsDeskTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Posting the same advert on every question after two warnings.';

    private function moderator(string $name = 'Mod Mokoena'): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => now(), 'is_moderator' => true]);
    }

    private function member(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['email_verified_at' => now()]);
    }

    public function test_only_moderators_reach_the_desk_and_only_they_see_its_tab(): void
    {
        $person = $this->member();

        $this->get(route('moderation.accounts'))->assertRedirect(route('login'));

        $this->actingAs($person);
        // What the rest of /moderation gives a non-moderator.
        $this->get(route('moderation'))->assertForbidden();
        $this->get(route('moderation.accounts'))->assertForbidden();
        $this->get(route('moderation.accounts.show', $person))->assertForbidden();
        $this->post(route('moderation.accounts.suspend', $person), ['reason' => self::REASON, 'confirm' => 1])->assertForbidden();
        $this->post(route('moderation.accounts.restore', $person))->assertForbidden();
        $this->get(route('questions'))->assertOk()->assertDontSee(route('moderation'), false);
        $this->assertFalse($person->fresh()->isSuspended());

        $this->flushSession();
        $this->actingAs($this->moderator());
        $this->get(route('questions'))->assertOk()->assertSee(route('moderation'), false);
        $this->get(route('moderation'))->assertOk()->assertSee(route('moderation.accounts'), false);
        $this->get(route('moderation.accounts'))->assertOk()->assertSee('aria-current="page"', false);
    }

    public function test_the_list_shows_recent_sign_ups_by_default_and_never_everyone(): void
    {
        $old = $this->member(['name' => 'Oldest Person', 'created_at' => now()->subYear()]);
        User::factory()->count(20)->create(['created_at' => now()->subDay()]);
        $new = $this->member(['name' => 'Newest Person', 'created_at' => now()->addMinute()]);

        $page = $this->actingAs($this->moderator())->get(route('moderation.accounts'))->assertOk();

        $page->assertSee('Newest sign-ups')->assertSee('Newest Person')->assertDontSee('Oldest Person');
        $this->assertSame(15, substr_count($page->getContent(), '<a class="title"'), 'fifteen newest, not all twenty-three');
    }

    public function test_search_finds_people_by_name_or_email_and_shows_each_row_s_facts(): void
    {
        $owner = $this->member(['name' => 'Zanele Khumalo', 'email' => 'zanele@northwind.co.za', 'created_at' => '2026-03-04 10:00:00']);
        $company = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => 'Northwind Traders']);
        $unverified = User::factory()->unverified()->create(['name' => 'Zane Unverified', 'email' => 'someone@elsewhere.co.za']);
        $suspended = $this->member(['name' => 'Bongani Zulu', 'email' => 'bz@elsewhere.co.za']);
        app(AccountSuspension::class)->suspend($suspended, 'Abusive messages to three businesses.');
        $other = $this->member(['name' => 'Peter Other', 'email' => 'peter@other.co.za']);

        $this->actingAs($this->moderator());

        $byName = $this->get(route('moderation.accounts', ['q' => 'zan']))->assertOk();
        $byName->assertSee('Zanele Khumalo')->assertSee('zanele@northwind.co.za')->assertSee('4 Mar 2026')->assertSee('Northwind Traders')
            ->assertSee('Zane Unverified')->assertSee('Email not verified')->assertSee('Active')
            ->assertDontSee('Peter Other')->assertDontSee('Bongani Zulu');

        $this->get(route('moderation.accounts', ['q' => 'bz@elsewhere']))->assertOk()
            ->assertSee('Bongani Zulu')->assertSee('Suspended since '.now()->format('j M Y'))->assertSee('Abusive messages to three businesses.')
            ->assertDontSee('Zane Unverified');

        // Wildcards are words, not patterns; one character is not a search.
        $this->get(route('moderation.accounts', ['q' => '%']))->assertOk()->assertSee('at least two characters');
        $this->get(route('moderation.accounts', ['q' => '__']))->assertOk()->assertSee('No account has that')->assertDontSee($other->email);
        $this->member(['name' => 'Under_score Person', 'email' => 'under_score@elsewhere.co.za']);
        $this->get(route('moderation.accounts', ['q' => 'r_s']))->assertOk()->assertSee('Under_score Person')->assertDontSee('Peter Other');
        $this->get(route('moderation.accounts', ['q' => 'nobody-by-this-name']))->assertOk()->assertSee('No account has that');
    }

    public function test_search_results_are_paginated(): void
    {
        foreach (range(1, 23) as $i) {
            $this->member(['name' => sprintf('Paged Person %02d', $i)]);
        }

        $this->actingAs($this->moderator());

        $first = $this->get(route('moderation.accounts', ['q' => 'Paged Person']))->assertOk();
        $first->assertSee('Paged Person 01')->assertSee('Paged Person 20')->assertDontSee('Paged Person 21')->assertSee('Page 1 of 2');
        $this->get(route('moderation.accounts', ['q' => 'Paged Person', 'page' => 2]))->assertOk()->assertSee('Paged Person 23')->assertDontSee('Paged Person 01<', false);
    }

    public function test_a_moderator_suspends_with_a_reason_and_a_confirmation_and_restores(): void
    {
        $moderator = $this->moderator();
        $person = $this->member(['name' => 'Spam Sender']);
        $person->createToken('device');
        $this->actingAs($moderator);

        $page = $this->get(route('moderation.accounts.show', $person))->assertOk();
        $page->assertSee('This account has never been suspended.')
            ->assertSee('Ended everywhere at once: every browser session')->assertSee('They cannot sign in to InfoDot or any Dot platform')
            ->assertSee('class="ix-textarea"', false);

        $url = route('moderation.accounts.suspend', $person);
        $this->post($url, ['confirm' => 1])->assertSessionHasErrors('reason');
        $this->post($url, ['reason' => 'too short', 'confirm' => 1])->assertSessionHasErrors('reason');
        $this->post($url, ['reason' => str_repeat('x', 256), 'confirm' => 1])->assertSessionHasErrors('reason');
        $this->post($url, ['reason' => self::REASON])->assertSessionHasErrors('confirm');
        $this->assertFalse($person->fresh()->isSuspended());
        $this->assertSame(0, AccountSuspensionEvent::count());

        $this->post($url, ['reason' => self::REASON, 'confirm' => 1])->assertRedirect(route('moderation.accounts.show', $person))->assertSessionHas('status');

        $person->refresh();
        $this->assertTrue($person->isSuspended());
        $this->assertSame(self::REASON, $person->suspended_reason);
        $this->assertSame(0, $person->tokens()->count(), 'the service ended what the account held');
        $event = AccountSuspensionEvent::sole();
        $this->assertSame([$person->id, AccountSuspensionEvent::SUSPENDED, self::REASON, $moderator->id], [$event->user_id, $event->action, $event->reason, $event->actor_id]);

        // A second click writes no second line.
        $this->post($url, ['reason' => self::REASON, 'confirm' => 1])->assertRedirect();
        $this->assertSame(1, AccountSuspensionEvent::count());

        $this->get(route('moderation.accounts.show', $person))->assertOk()
            ->assertSee('Suspended since')->assertSee(self::REASON)->assertSee('Suspended — Mod Mokoena')->assertSee('Restore the account');

        $this->post(route('moderation.accounts.restore', $person), ['reason' => str_repeat('x', 256)])->assertSessionHasErrors('reason');
        $this->post(route('moderation.accounts.restore', $person))->assertRedirect(route('moderation.accounts.show', $person));

        $this->assertFalse($person->fresh()->isSuspended());
        $restored = AccountSuspensionEvent::orderByDesc('id')->first();
        $this->assertSame([AccountSuspensionEvent::RESTORED, null, $moderator->id], [$restored->action, $restored->reason, $restored->actor_id]);
        $this->get(route('moderation.accounts.show', $person))->assertOk()->assertSee('Restored — Mod Mokoena')->assertSee('Active');

        // Restoring an account that is not suspended changes nothing.
        $this->post(route('moderation.accounts.restore', $person), ['reason' => 'Twice.'])->assertRedirect();
        $this->assertSame(2, AccountSuspensionEvent::count());
    }

    public function test_a_moderator_cannot_suspend_themselves_or_another_moderator(): void
    {
        $moderator = $this->moderator();
        $colleague = $this->moderator('Second Moderator');
        $this->actingAs($moderator);

        $this->get(route('moderation.accounts.show', $moderator))->assertOk()
            ->assertSee('This is your own account')->assertDontSee(route('moderation.accounts.suspend', $moderator), false);
        $this->post(route('moderation.accounts.suspend', $moderator), ['reason' => self::REASON, 'confirm' => 1])->assertForbidden();

        $this->get(route('moderation.accounts.show', $colleague))->assertOk()
            ->assertSee('One moderator cannot suspend or restore another')->assertDontSee(route('moderation.accounts.suspend', $colleague), false);
        $this->post(route('moderation.accounts.suspend', $colleague), ['reason' => self::REASON, 'confirm' => 1])->assertForbidden();

        // Nor undo the owner's suspension of a moderator.
        app(AccountSuspension::class)->suspend($colleague, 'Suspended by the owner from the command line.');
        $this->post(route('moderation.accounts.restore', $colleague))->assertForbidden();

        $this->assertFalse($moderator->fresh()->isSuspended());
        $this->assertTrue($colleague->fresh()->isSuspended());
        $this->assertSame(1, AccountSuspensionEvent::count(), 'only the owner\'s own line is in the trail');
        $this->get(route('moderation.accounts.show', $colleague))->assertOk()->assertSee('from the command line');
    }

    public function test_a_person_who_does_not_exist_is_a_404(): void
    {
        $this->actingAs($this->moderator());

        $this->get('/moderation/accounts/999999')->assertNotFound();
        $this->post('/moderation/accounts/999999/suspend', ['reason' => self::REASON, 'confirm' => 1])->assertNotFound();
        $this->post('/moderation/accounts/999999/restore')->assertNotFound();
        $this->get('/moderation/accounts/not-a-number')->assertNotFound();
    }

    public function test_the_demonstration_moderator_acts_on_demonstration_accounts_only(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Http::preventStrayRequests();
        $real = $this->member(['name' => 'Real Person']);
        $this->artisan('demo:install')->assertSuccessful();
        app(DemoRegistry::class)->flush();

        $demoModerator = User::where('email', 'thabo@demo.infodot.test')->firstOrFail();
        $demoPerson = User::where('email', 'lerato@demo.infodot.test')->firstOrFail();
        $this->actingAs($demoModerator);

        $this->get(route('moderation.accounts', ['q' => 'demo.infodot.test']))->assertOk()->assertSee('>Demonstration</span>', false);
        $this->get(route('moderation.accounts.show', $real))->assertOk()->assertSee('demonstration accounts only');
        $this->post(route('moderation.accounts.suspend', $real), ['reason' => self::REASON, 'confirm' => 1])->assertForbidden();
        $this->assertFalse($real->fresh()->isSuspended());

        $this->post(route('moderation.accounts.suspend', $demoPerson), ['reason' => self::REASON, 'confirm' => 1])->assertRedirect();
        $this->assertTrue($demoPerson->fresh()->isSuspended());

        // The trail of a demonstration account leaves with it.
        $this->app['auth']->forgetGuards();
        $this->artisan('demo:remove')->assertSuccessful();
        $this->assertSame(0, AccountSuspensionEvent::count());
        $this->assertNotNull($real->fresh());
    }
}

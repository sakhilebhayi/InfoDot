<?php

namespace Tests\Feature;

use App\Livewire\Search;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Two synthetic companies. Nothing private to one may be listed, searched, counted,
 * named or opened by the other, by any route: lists, direct links and guessed ids,
 * search, Home, analytics and profiles. Public content is the only shared surface.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Team $acme;

    private Team $globex;

    private User $alice;   // Acme owner

    private User $adam;    // Acme editor

    private User $gina;    // Globex owner

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = $this->person('Alice Acme');
        $this->adam = $this->person('Adam Acme');
        $this->gina = $this->person('Gina Globex');

        $this->acme = $this->company($this->alice, 'Acme Ltd');
        $this->acme->users()->attach($this->adam, ['role' => 'editor']);
        $this->adam->forceFill(['current_team_id' => $this->acme->id])->save();
        $this->globex = $this->company($this->gina, 'Globex Corp');
    }

    private function person(string $name): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => Carbon::now()]);
    }

    private function company(User $owner, string $name): Team
    {
        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => $name, 'personal_team' => false]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        return $team;
    }

    private function question(User $by, Team $team, string $title, string $visibility = 'company'): Questions
    {
        return Questions::withoutSyncingToSearch(fn () => Questions::create([
            'user_id' => $by->id, 'team_id' => $team->id, 'visibility' => $visibility,
            'question' => $title, 'description' => 'details', 'status' => 0,
        ]));
    }

    private function solution(User $by, Team $team, string $title, string $visibility = 'company'): Solutions
    {
        return Solutions::withoutSyncingToSearch(fn () => Solutions::create([
            'user_id' => $by->id, 'team_id' => $team->id, 'visibility' => $visibility,
            'solution_title' => $title, 'solution_description' => 'how to', 'tags' => 'tag',
            'duration' => 1, 'duration_type' => 'hours', 'steps' => 1,
        ]));
    }

    public function test_a_company_sees_its_own_and_public_content_but_never_another_companys_private_content(): void
    {
        $this->question($this->alice, $this->acme, 'Acme private question');
        $this->question($this->gina, $this->globex, 'Globex private question');
        $this->question($this->gina, $this->globex, 'Globex public question', 'public');
        $this->solution($this->gina, $this->globex, 'Globex private solution');

        $this->actingAs($this->adam)->get('/questions')
            ->assertOk()
            ->assertSee('Acme private question')
            ->assertSee('Globex public question')
            ->assertDontSee('Globex private question');

        $this->actingAs($this->adam)->get('/solutions')->assertOk()->assertDontSee('Globex private solution');
    }

    public function test_opening_another_companys_private_item_by_link_is_not_found_not_forbidden(): void
    {
        $q = $this->question($this->gina, $this->globex, 'Globex secret');
        $s = $this->solution($this->gina, $this->globex, 'Globex secret solution');

        $this->actingAs($this->adam)->get(route('questions.view', ['qid' => $q->id]))->assertNotFound();
        $this->actingAs($this->adam)->get(route('solutions.view', ['id' => $s->id]))->assertNotFound();
    }

    public function test_walking_every_id_only_ever_opens_what_the_person_may_see(): void
    {
        $own = $this->question($this->alice, $this->acme, 'Acme one');
        $pub = $this->question($this->gina, $this->globex, 'Globex public', 'public');
        $other = $this->question($this->gina, $this->globex, 'Globex hidden');

        foreach (range(1, $other->id + 3) as $id) {
            $status = $this->actingAs($this->adam)->get(route('questions.view', ['qid' => $id]))->getStatusCode();

            $this->assertSame(in_array($id, [$own->id, $pub->id], true) ? 200 : 404, $status, "question id $id");
        }
    }

    public function test_search_never_returns_another_companys_private_content(): void
    {
        $this->question($this->gina, $this->globex, 'Payroll secret');
        $this->solution($this->gina, $this->globex, 'Payroll secret playbook');
        $this->question($this->gina, $this->globex, 'Payroll public notice', 'public');

        $this->actingAs($this->adam);
        $component = Livewire::test(Search::class)->set('query', 'Payroll');

        $titles = $component->get('questions')->pluck('question')->merge($component->get('solutions')->pluck('solution_title'));
        $this->assertSame(['Payroll public notice'], $titles->all());

        // The signed-out search page shows public content only.
        auth()->logout();
        $this->get('/solution-results?search=Payroll')->assertOk()->assertSee('Payroll public notice')->assertDontSee('Payroll secret');
    }

    public function test_home_and_analytics_count_and_name_only_the_company(): void
    {
        $this->question($this->alice, $this->acme, 'Acme question');
        $this->question($this->gina, $this->globex, 'Globex question');
        $this->question($this->gina, $this->globex, 'Globex public question', 'public');

        $home = $this->actingAs($this->alice)->get('/home')->assertOk()->getContent();
        // Gina's PUBLIC question may appear (public is shared by design); her private one may not.
        $this->assertStringNotContainsString('Globex question<', $home);
        $this->assertStringContainsString('Globex public question', $home);

        $analytics = $this->actingAs($this->alice)->get('/analytics')->assertOk()->getContent();
        $this->assertStringNotContainsString('Gina Globex', $analytics);
        $this->assertStringContainsString('Alice Acme', $analytics);
        $this->assertStringContainsString('Adam Acme', $analytics);
        // Only Acme's one question is counted, not Globex's two.
        $this->assertMatchesRegularExpression('#Questions</span>\s*<span class="ix-fig"\s*>1</span>#', $analytics);
    }

    public function test_a_profile_is_visible_to_yourself_and_to_people_who_share_a_company_only(): void
    {
        $this->actingAs($this->adam)->get('/user/profile/'.$this->adam->id)->assertOk();
        $this->actingAs($this->adam)->get('/user/profile/'.$this->alice->id)->assertOk();
        $this->actingAs($this->adam)->get('/user/profile/'.$this->gina->id)->assertNotFound();
        $this->actingAs($this->gina)->get('/user/profile/'.$this->adam->id)->assertNotFound();
        $this->actingAs($this->adam)->get('/user/profile/999999')->assertNotFound();
    }

    public function test_switching_company_switches_what_is_visible(): void
    {
        $this->question($this->alice, $this->acme, 'Acme only');
        $this->question($this->gina, $this->globex, 'Globex only');
        $this->globex->users()->attach($this->adam, ['role' => 'editor']);

        $this->adam->forceFill(['current_team_id' => $this->acme->id])->save();
        $this->actingAs($this->adam->fresh())->get('/questions')->assertSee('Acme only')->assertDontSee('Globex only');

        $this->adam->forceFill(['current_team_id' => $this->globex->id])->save();
        $this->actingAs($this->adam->fresh())->get('/questions')->assertSee('Globex only')->assertDontSee('Acme only');
    }

    public function test_new_content_is_private_to_the_company_unless_published(): void
    {
        $this->actingAs($this->adam)->post('/questions/add', ['question' => 'Private by default', 'description' => 'details'])->assertRedirect();
        $this->actingAs($this->adam)->post('/questions/add', ['question' => 'Published on purpose', 'description' => 'details', 'publish' => '1'])->assertRedirect();

        $private = Questions::withoutGlobalScopes()->where('question', 'Private by default')->first();
        $public = Questions::withoutGlobalScopes()->where('question', 'Published on purpose')->first();

        $this->assertSame('company', $private->visibility);
        $this->assertSame($this->acme->id, $private->team_id);
        $this->assertSame('public', $public->visibility);
        $this->assertSame($this->acme->id, $public->team_id);

        $this->actingAs($this->gina)->get('/questions')->assertSee('Published on purpose')->assertDontSee('Private by default');
    }

    public function test_the_author_always_sees_their_own_content(): void
    {
        $q = $this->question($this->adam, $this->acme, 'Adam wrote this');
        $this->acme->users()->detach($this->adam);
        $this->adam->forceFill(['current_team_id' => null])->save();

        $this->actingAs($this->adam->fresh())->get(route('questions.view', ['qid' => $q->id]))->assertOk();
    }

    public function test_the_migration_keeps_existing_content_public_and_attaches_it_to_the_authors_team(): void
    {
        $q = Questions::withoutGlobalScopes()->create([
            'user_id' => $this->alice->id, 'question' => 'Legacy', 'description' => 'old', 'status' => 0,
        ]);
        \DB::table('questions')->where('id', $q->id)->update(['team_id' => null, 'visibility' => 'company']);

        (require database_path('migrations/2026_10_17_160000_add_team_and_visibility_to_questions_and_solutions.php'))->up();

        $fresh = Questions::withoutGlobalScopes()->find($q->id);
        $this->assertSame('public', $fresh->visibility);
        $this->assertSame($this->acme->id, $fresh->team_id);
    }
}

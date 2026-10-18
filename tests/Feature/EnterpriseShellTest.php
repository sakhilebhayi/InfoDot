<?php

namespace Tests\Feature;

use App\Livewire\Comments;
use App\Livewire\PlatformLink;
use App\Livewire\Search;
use App\Models\Questions;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Yard shell: the company's owner code and name in the top bar, area tabs, an
 * area band that carries the code, the area code and the breadcrumb, doors to Files
 * and Documents that open signed in, administration only for administrators, and no
 * social counters anywhere.
 */
class EnterpriseShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ecosystem.trusted_domains' => ['infodot.co.za'],
            'ecosystem.platforms.files' => ['name' => 'Dot.Files', 'url' => 'https://files.infodot.co.za', 'icon' => 'folder', 'active' => true],
            'ecosystem.platforms.docs' => ['name' => 'Dot.docs', 'url' => 'https://doc.infodot.co.za', 'icon' => 'description', 'active' => true],
        ]);
    }

    private function owner(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false, 'name' => 'Northwind Finance']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    private function editorIn(Team $team): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now(), 'current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'editor']);

        return $user;
    }

    public function test_home_opens_with_the_companys_owner_code_name_and_what_is_waiting(): void
    {
        $user = $this->owner();
        Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $user->id, 'team_id' => $user->current_team_id, 'question' => 'Who approves purchase orders?', 'description' => 'x', 'status' => 0]));

        $html = $this->actingAs($user)->get('/home')->assertOk()
            ->assertSee('Northwind Finance')
            ->assertSee('Welcome back')
            ->assertSee('Who approves purchase orders?')
            ->assertSee('waiting for an answer')
            ->getContent();

        $this->assertStringContainsString('NOFI', $html);
        $this->assertMatchesRegularExpression('#NOFI-Q-0000\d\d#', $html, 'items on Home carry their reference');
    }

    public function test_the_top_bar_has_the_area_tabs_and_marks_the_current_one(): void
    {
        $html = $this->actingAs($this->owner())->get('/questions')->assertOk()->getContent();

        foreach (['Home', 'Knowledge', 'Platforms', 'People', 'Analytics'] as $tab) {
            $this->assertMatchesRegularExpression('#class="ix-tab"[^>]*>\s*'.$tab.'#', $html, $tab);
        }
        $this->assertMatchesRegularExpression('#href="'.preg_quote(url('/questions'), '#').'" class="ix-tab"[^>]*aria-current="page"#', $html);
    }

    public function test_each_area_sets_its_colour_and_the_band_shows_code_and_area(): void
    {
        $user = $this->owner();

        foreach (['/questions' => ['knowledge', 'KNOW'], '/platforms' => ['platforms', 'PLAT'], '/analytics' => ['analytics', 'DATA'], '/people' => ['people', 'CREW'], '/home' => ['home', 'GATE']] as $url => [$area, $code]) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('data-area="'.$area.'"', $html, $url);

            if ($area !== 'home') {
                $this->assertStringContainsString('ix-band__code">'.$code.'<', $html, $url);
                $this->assertStringNotContainsString('ix-owner__code', $html, $url);
            }
        }
    }

    public function test_knowledge_has_questions_and_solutions_beneath_the_band(): void
    {
        $html = $this->actingAs($this->owner())->get('/solutions')->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Knowledge"', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(url('/solutions'), '#').'"\s+aria-current="page"#', $html);
    }

    public function test_files_and_documents_open_the_platform_signed_in_and_only_when_live(): void
    {
        $user = $this->owner();

        $html = $this->actingAs($user)->get('/home')->getContent();
        $this->assertStringContainsString('wire:click="open"', $html);

        Livewire::actingAs($user)->test(PlatformLink::class, ['platform' => 'files', 'label' => 'Files', 'icon' => 'folder', 'variant' => 'door'])
            ->call('open')
            ->assertRedirectContains('https://files.infodot.co.za/auth/ecosystem?token=');
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'ecosystem-handoff:files', 'tokenable_id' => $user->id]);

        config(['ecosystem.platforms.docs.active' => false]);
        Livewire::actingAs($user)->test(PlatformLink::class, ['platform' => 'docs', 'label' => 'Documents', 'variant' => 'tab'])
            ->assertDontSee('Documents');
    }

    public function test_company_settings_are_only_offered_to_team_administrators(): void
    {
        $owner = $this->owner();
        $editor = $this->editorIn($owner->currentTeam);

        $this->actingAs($owner)->get('/home')->assertSee('Team settings');
        $this->actingAs($editor)->get('/home')->assertOk()->assertDontSee('Team settings')->assertDontSee('API tokens');
    }

    public function test_people_lists_only_the_companys_members_with_their_roles(): void
    {
        $owner = $this->owner();
        $editor = $this->editorIn($owner->currentTeam);
        $stranger = User::factory()->create(['name' => 'Stranger Danger', 'email_verified_at' => Carbon::now()]);

        $this->actingAs($owner)->get('/people')->assertOk()
            ->assertSee($owner->name)
            ->assertSee($editor->name)
            ->assertSee('Owner')
            ->assertSee('Editor')
            ->assertDontSee($stranger->name);
    }

    public function test_the_stamp_appears_when_a_question_is_solved(): void
    {
        $user = $this->owner();
        $q = Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $user->id, 'team_id' => $user->current_team_id, 'question' => 'Q', 'description' => 'd', 'status' => 0]));

        $this->actingAs($user)->get(route('questions.view', ['qid' => $q->id]))->assertDontSee('ix-stamp');

        Livewire::actingAs($user)->test(Comments::class, ['model' => $q, 'question' => $q])
            ->call('markedAsSolved')
            ->assertSeeHtml('ix-stamp');
    }

    public function test_a_question_shows_its_reference_for_copying(): void
    {
        $user = $this->owner();
        $q = Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $user->id, 'team_id' => $user->current_team_id, 'question' => 'Q', 'description' => 'd', 'status' => 0]));
        $ref = sprintf('NOFI-Q-%06d', $q->id);

        $this->actingAs($user)->get(route('questions.view', ['qid' => $q->id]))
            ->assertOk()
            ->assertSee($ref)
            ->assertSee('Copy reference');
    }

    public function test_typing_a_reference_in_search_jumps_to_the_item_and_never_to_a_hidden_one(): void
    {
        $user = $this->owner();
        $q = Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $user->id, 'team_id' => $user->current_team_id, 'question' => 'Jump target', 'description' => 'd', 'status' => 0]));
        $other = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $otherTeam = Team::forceCreate(['user_id' => $other->id, 'name' => 'Globex', 'personal_team' => false]);
        $hidden = Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $other->id, 'team_id' => $otherTeam->id, 'question' => 'Hidden target', 'description' => 'd', 'status' => 0]));

        $this->actingAs($user);
        $mine = Livewire::test(Search::class)->set('query', sprintf('NOFI-Q-%06d', $q->id));
        $mine->assertSee('Jump target');
        $this->assertSame($q->id, $mine->get('reference')->id);

        $theirs = Livewire::test(Search::class)->set('query', sprintf('%s-Q-%06d', $otherTeam->code, $hidden->id));
        $theirs->assertDontSee('Hidden target');
        $this->assertNull($theirs->get('reference'));
    }

    public function test_no_page_shows_like_counters(): void
    {
        $user = $this->owner();
        $q = Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $user->id, 'team_id' => $user->current_team_id, 'question' => 'Q', 'description' => 'd', 'status' => 0]));

        foreach (['/home', '/questions', "/question/view/{$q->id}", '/solutions', '/analytics', '/people'] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/\blikes?\b/i', strip_tags(preg_replace('#<style.*?</style>|<script.*?</script>#s', '', $html)), "$url mentions likes");
        }
    }
}

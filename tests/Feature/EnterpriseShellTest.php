<?php

namespace Tests\Feature;

use App\Livewire\PlatformLink;
use App\Models\Questions;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The signed-in shell: contextual side navigation, breadcrumbs in the top bar, a
 * real Home page, Files and Documents that open signed in, administration only for
 * administrators, and no social counters anywhere.
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

    public function test_home_is_a_page_about_the_team_and_what_needs_an_answer(): void
    {
        $user = $this->owner();
        Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $user->id, 'question' => 'Who approves purchase orders?', 'description' => 'x', 'status' => 0]));

        $this->actingAs($user)->get('/home')
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Northwind Finance')
            ->assertSee('Who approves purchase orders?')
            ->assertSee('Waiting for an answer');
    }

    public function test_the_side_navigation_groups_work_knowledge_and_ecosystem(): void
    {
        $html = $this->actingAs($this->owner())->get('/home')->assertOk()->getContent();

        foreach (['Work', 'Knowledge', 'Ecosystem', 'Home', 'Questions', 'Solutions', 'Platforms', 'Analytics'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertMatchesRegularExpression('#href="'.preg_quote(url('/home'), '#').'"[^>]*aria-current="page"#', $html);
    }

    public function test_files_and_documents_open_the_platform_signed_in_and_only_when_live(): void
    {
        $user = $this->owner();

        $html = $this->actingAs($user)->get('/home')->getContent();
        $this->assertStringContainsString('wire:click="open"', $html);

        Livewire::actingAs($user)->test(PlatformLink::class, ['platform' => 'files', 'label' => 'Files', 'icon' => 'folder'])
            ->call('open')
            ->assertRedirectContains('https://files.infodot.co.za/auth/ecosystem?token=');
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'ecosystem-handoff:files', 'tokenable_id' => $user->id]);

        config(['ecosystem.platforms.docs.active' => false]);
        Livewire::actingAs($user)->test(PlatformLink::class, ['platform' => 'docs', 'label' => 'Documents'])
            ->assertDontSee('Documents');
    }

    public function test_administration_is_only_offered_to_team_administrators(): void
    {
        $owner = $this->owner();
        $editor = $this->editorIn($owner->currentTeam);

        $this->actingAs($owner)->get('/home')->assertSee('Administration')->assertSee('Team and members');
        $this->actingAs($editor)->get('/home')->assertOk()->assertDontSee('Administration')->assertDontSee('Team and members');
    }

    public function test_the_top_bar_shows_where_you_are(): void
    {
        $user = $this->owner();
        $q = Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $user->id, 'question' => 'How long do we keep contracts?', 'description' => 'x', 'status' => 0]));

        $html = $this->actingAs($user)->get(route('questions.view', ['qid' => $q->id]))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Breadcrumb"', $html);
        $this->assertMatchesRegularExpression('#Knowledge.*Questions.*<span class="here" aria-current="page">How long do we keep contracts\?</span>#s', $html);
    }

    public function test_no_page_shows_like_counters(): void
    {
        $user = $this->owner();
        $q = Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $user->id, 'question' => 'Q', 'description' => 'd', 'status' => 0]));

        foreach (['/home', '/questions', "/question/view/{$q->id}", '/solutions', '/analytics'] as $url) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/\blikes?\b/i', strip_tags(preg_replace('#<style.*?</style>|<script.*?</script>#s', '', $html)), "$url mentions likes");
        }
    }

    public function test_the_logo_follows_the_theme(): void
    {
        $html = $this->actingAs($this->owner())->get('/home')->getContent();

        $this->assertStringContainsString('<picture>', $html);
        $this->assertStringContainsString('logo_white.png', $html);
        $this->assertStringContainsString('media="(prefers-color-scheme: dark)"', $html);
    }
}

<?php

namespace Tests\Feature;

use App\Livewire\Search;
use App\Models\Resource;
use App\Models\Team;
use App\Models\User;
use App\Support\MembershipWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Header search also finds the company's files and documents: each platform is asked, signed,
 * and only answers with what the person may see; InfoDot shows a result only if it already
 * holds that pointer for this company.
 */
class PlatformSearchTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $name = 'Northwind', bool $personal = false): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now(), 'email' => 'Amara'.Str::random(6).'@Example.test']);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => $personal, 'name' => $name]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    private function pointer(Team $team, string $platform, string $type): Resource
    {
        return Resource::create(['team_id' => $team->id, 'type' => $type, 'platform' => $platform, 'platform_object_uuid' => (string) Str::uuid(), 'path' => '/o/x']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ecosystem.trusted_domains' => ['infodot.co.za'],
            'ecosystem.search_platforms' => ['files', 'docs'],
            'ecosystem.service_secrets' => ['files' => 'files-secret', 'docs' => 'docs-secret'],
            'ecosystem.platforms.files' => ['name' => 'Dot.Files', 'url' => 'https://files.infodot.co.za', 'icon' => 'folder', 'active' => true],
            'ecosystem.platforms.docs' => ['name' => 'Dot.docs', 'url' => 'https://doc.infodot.co.za', 'icon' => 'description', 'active' => true],
        ]);
    }

    private function fake(array $files = [], array $docs = [], int $status = 200): void
    {
        $this->swap(HttpFactory::class, new HttpFactory);
        Http::fake([
            'files.infodot.co.za/*' => Http::response(['results' => $files], $status),
            'doc.infodot.co.za/*' => Http::response(['results' => $docs], $status),
        ]);
    }

    public function test_matches_in_files_and_documents_appear_with_their_platform_and_open_through_the_resolver(): void
    {
        $user = $this->owner();
        $team = $user->currentTeam;
        $file = $this->pointer($team, 'files', 'file');
        $doc = $this->pointer($team, 'docs', 'document');
        $this->fake([['resource' => $file->uuid, 'title' => 'Cash-up sheet.xlsx']], [['resource' => $doc->uuid, 'title' => 'Till cash-up policy']]);

        $search = Livewire::actingAs($user)->test(Search::class)->set('query', 'cash-up')->call('loadExternal');

        $search->assertSee('In Files and Documents')
            ->assertSee('<mark>Cash-up</mark> sheet.xlsx', false)
            ->assertSee('Till <mark>cash-up</mark> policy', false)
            ->assertSee('Dot.Files')->assertSee('Dot.docs')
            ->assertSee(route('resources.open', ['resource' => $file->uuid]), false)
            ->assertSee(route('resources.open', ['resource' => $doc->uuid]), false);
    }

    public function test_each_platform_is_asked_with_a_valid_signature_and_only_identifiers_and_the_query(): void
    {
        $user = $this->owner();
        $this->fake();

        Livewire::actingAs($user)->test(Search::class)->set('query', 'cash-up')->call('loadExternal');

        foreach (['files.infodot.co.za' => 'files-secret', 'doc.infodot.co.za' => 'docs-secret'] as $host => $secret) {
            $request = collect(Http::recorded())->map(fn ($p) => $p[0])->first(fn (Request $r) => str_contains($r->url(), $host));
            $this->assertNotNull($request, $host);
            $this->assertSame("https://$host/api/ecosystem/search", $request->url());
            $ts = (int) $request->header(MembershipWebhook::HEADER_TIMESTAMP)[0];
            $this->assertSame(MembershipWebhook::sign($secret, $ts, $request->body()), $request->header(MembershipWebhook::HEADER_SIGNATURE)[0]);
            $this->assertSame(['team_uuid' => $user->currentTeam->uuid, 'email' => strtolower($user->email), 'q' => 'cash-up'], json_decode($request->body(), true));
        }
    }

    public function test_a_result_for_a_pointer_this_company_does_not_hold_is_dropped(): void
    {
        $user = $this->owner();
        $rival = $this->owner('Rival Ltd');
        $theirs = $this->pointer($rival->currentTeam, 'files', 'file');
        $wrongPlatform = $this->pointer($user->currentTeam, 'docs', 'document');
        $this->fake([
            ['resource' => $theirs->uuid, 'title' => 'Rival payroll'],
            ['resource' => (string) Str::uuid(), 'title' => 'Invented file'],
            ['resource' => $wrongPlatform->uuid, 'title' => 'Docs pointer claimed by Files'],
        ]);

        Livewire::actingAs($user)->test(Search::class)->set('query', 'payroll')->call('loadExternal')
            ->assertDontSee('Rival payroll')->assertDontSee('Invented file')->assertDontSee('claimed by Files');
    }

    public function test_titles_are_escaped(): void
    {
        $user = $this->owner();
        $file = $this->pointer($user->currentTeam, 'files', 'file');
        $this->fake([['resource' => $file->uuid, 'title' => '<img src=x onerror=alert(1)> plan']]);

        Livewire::actingAs($user)->test(Search::class)->set('query', 'plan')->call('loadExternal')
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt; <mark>plan</mark>', false);
    }

    public function test_no_one_is_asked_for_a_personal_workspace_or_a_very_short_query(): void
    {
        $this->fake();

        Livewire::actingAs($this->owner('Me', true))->test(Search::class)->set('query', 'cash-up')->call('loadExternal');
        Livewire::actingAs($this->owner())->test(Search::class)->set('query', 'ca')->call('loadExternal');

        Http::assertNothingSent();
    }

    public function test_a_platform_that_errors_or_times_out_just_adds_nothing(): void
    {
        $user = $this->owner();
        $this->fake([], [], 500);

        Livewire::actingAs($user)->test(Search::class)->set('query', 'cash-up')->call('loadExternal')
            ->assertDontSee('In Files and Documents')
            ->assertSee('See every result for');
    }

    public function test_arrow_keys_and_enter_reach_a_file_result(): void
    {
        $user = $this->owner();
        $file = $this->pointer($user->currentTeam, 'files', 'file');
        $this->fake([['resource' => $file->uuid, 'title' => 'Cash-up sheet']]);

        $search = Livewire::actingAs($user)->test(Search::class)->set('query', 'cash-up')->call('loadExternal');
        $kinds = array_column($search->get('items'), 'kind');
        $search->set('highlightIndex', array_search('external', $kinds, true))->call('openHighlighted')
            ->assertRedirect(route('resources.open', ['resource' => $file->uuid]));
    }
}

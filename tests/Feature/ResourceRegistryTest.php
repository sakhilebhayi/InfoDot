<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\Team;
use App\Models\User;
use App\Support\Reference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The resource registry and the opener: platforms register pointers to what they own,
 * a reference opens it only for people in the owning company, and nothing leaks across
 * companies.
 */
class ResourceRegistryTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'files-secret-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ecosystem.trusted_domains' => ['infodot.co.za'],
            'ecosystem.service_secrets' => ['files' => self::SECRET, 'docs' => 'docs-secret-for-tests'],
            'ecosystem.platforms.files' => ['name' => 'Dot.Files', 'url' => 'https://files.infodot.co.za', 'icon' => 'folder', 'active' => true],
            'ecosystem.platforms.docs' => ['name' => 'Dot.docs', 'url' => 'https://doc.infodot.co.za', 'icon' => 'description', 'active' => true],
        ]);
    }

    private function company(string $name): Team
    {
        $owner = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => $name]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        return $team;
    }

    private function register(array $over = [], ?string $secret = self::SECRET, string $platform = 'files')
    {
        return $this->withHeaders($secret === null ? [] : ['Authorization' => 'Bearer '.$secret])
            ->postJson('/api/ecosystem/resources', array_merge([
                'platform' => $platform,
                'team_uuid' => $this->company('Northwind Finance')->uuid,
                'type' => 'file',
                'object_uuid' => (string) Str::uuid(),
                'path' => '/o/abc',
            ], $over));
    }

    public function test_files_registers_a_file_and_gets_a_reference_and_link_back(): void
    {
        $team = $this->company('Northwind Finance');
        $object = (string) Str::uuid();

        $response = $this->register(['team_uuid' => $team->uuid, 'object_uuid' => $object])->assertCreated();

        $this->assertMatchesRegularExpression('/^'.$team->code.'-F-000001$/', $response->json('reference'));
        $this->assertSame(1, Resource::count());

        // Registering the same object again updates it instead of duplicating it.
        $this->register(['team_uuid' => $team->uuid, 'object_uuid' => $object, 'path' => '/o/moved'])->assertOk();
        $this->assertSame(1, Resource::count());
        $this->assertSame('/o/moved', Resource::first()->path);
    }

    public function test_the_registry_holds_pointers_only(): void
    {
        $this->register()->assertCreated();

        $this->assertEqualsCanonicalizing(
            ['id', 'uuid', 'team_id', 'type', 'platform', 'platform_object_uuid', 'path', 'created_at', 'updated_at'],
            array_keys(Resource::first()->getAttributes()),
            'no names, titles or content are stored'
        );
    }

    public function test_only_the_owning_platform_with_its_own_secret_may_register(): void
    {
        $this->register([], null)->assertStatus(401);
        $this->register([], 'wrong')->assertStatus(401);
        // Docs' secret cannot register under Files' name, and Files cannot register documents.
        $this->register([], 'docs-secret-for-tests', 'files')->assertStatus(401);
        $this->register(['type' => 'document'])->assertStatus(403);
        $this->assertSame(0, Resource::count());
    }

    public function test_it_refuses_personal_teams_unknown_companies_and_odd_paths(): void
    {
        $personal = Team::factory()->create(['user_id' => User::factory()->create()->id, 'personal_team' => true]);

        $this->register(['team_uuid' => $personal->uuid])->assertStatus(422);
        $this->register(['team_uuid' => (string) Str::uuid()])->assertStatus(422);
        $this->register(['path' => 'https://evil.example/x'])->assertStatus(422);
        $this->register(['path' => '//evil.example/x'])->assertStatus(422);
    }

    public function test_a_platform_can_only_remove_its_own_pointers(): void
    {
        $object = (string) Str::uuid();
        $this->register(['object_uuid' => $object])->assertCreated();

        $this->withHeaders(['Authorization' => 'Bearer docs-secret-for-tests'])
            ->deleteJson('/api/ecosystem/resources/'.$object, ['platform' => 'docs'])->assertOk();
        $this->assertSame(1, Resource::count(), 'docs cannot remove a Files pointer');

        $this->withHeaders(['Authorization' => 'Bearer '.self::SECRET])
            ->deleteJson('/api/ecosystem/resources/'.$object, ['platform' => 'files'])->assertOk();
        $this->assertSame(0, Resource::count());
    }

    public function test_a_member_opens_it_signed_in_with_the_exact_object_and_the_companys_workspace(): void
    {
        $team = $this->company('Northwind Finance');
        $object = (string) Str::uuid();
        $this->register(['team_uuid' => $team->uuid, 'object_uuid' => $object, 'path' => '/o/'.$object])->assertCreated();
        $resource = Resource::first();

        $member = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $personal = Team::factory()->create(['user_id' => $member->id, 'personal_team' => true]);
        $member->forceFill(['current_team_id' => $personal->id])->save();
        $team->users()->attach($member, ['role' => 'editor']);

        $response = $this->actingAs($member)->get('/open/'.$resource->uuid);

        $response->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertStringStartsWith(url('/go/files'), $url);
        $this->assertStringNotContainsString('token', $url);
        $this->assertStringContainsString('redirect='.rawurlencode('/o/'.$object), $url);
        $this->assertSame($team->id, $member->fresh()->current_team_id, 'opening switches to the owning company');
    }

    public function test_docs_registers_a_document_and_it_is_referenced_with_a_d(): void
    {
        $team = $this->company('Northwind Finance');
        $object = (string) Str::uuid();

        $response = $this->register(['team_uuid' => $team->uuid, 'object_uuid' => $object, 'type' => 'document'], 'docs-secret-for-tests', 'docs')->assertCreated();

        $this->assertMatchesRegularExpression('/^'.$team->code.'-D-000001$/', $response->json('reference'));
    }

    public function test_a_document_opens_in_docs_and_the_letter_must_match_what_it_is(): void
    {
        $team = $this->company('Northwind Finance');
        $object = (string) Str::uuid();
        $this->register(['team_uuid' => $team->uuid, 'object_uuid' => $object, 'type' => 'document', 'path' => '/o/'.$object], 'docs-secret-for-tests', 'docs')->assertCreated();
        $resource = Resource::first()->load('team');

        $member = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team->users()->attach($member, ['role' => 'editor']);

        $response = $this->actingAs($member)->get('/open/'.$resource->uuid);
        $this->assertStringStartsWith(url('/go/docs'), $response->headers->get('Location'));
        $this->assertStringNotContainsString('token', $response->headers->get('Location'));
        $this->assertStringContainsString('redirect='.rawurlencode('/o/'.$object), $response->headers->get('Location'));

        $ref = Reference::for($resource);
        $this->assertStringContainsString('-D-', $ref);
        $this->assertTrue(Reference::resolve($ref)?->is($resource));
        $this->assertNull(Reference::resolve(str_replace('-D-', '-F-', $ref)), 'a document never opens through an F reference');
    }

    public function test_a_person_in_another_company_is_handed_to_the_platform_which_decides_and_keeps_their_own_workspace(): void
    {
        $this->register()->assertCreated();
        $resource = Resource::first();

        $outsider = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $other = Team::factory()->create(['user_id' => $outsider->id, 'personal_team' => false, 'name' => 'Rival Ltd']);
        $outsider->forceFill(['current_team_id' => $other->id])->save();

        $response = $this->actingAs($outsider)->get('/open/'.$resource->uuid);

        // InfoDot does not decide for the platform (a document collaborator or a file share may
        // legitimately open it); it only says who the person is. Nothing about the object is shown here.
        $response->assertRedirect();
        $this->assertStringStartsWith(url('/go/files'), $response->headers->get('Location'));
        $this->assertStringNotContainsString('token', $response->headers->get('Location'));
        $this->assertSame($other->id, $outsider->fresh()->current_team_id, 'their workspace was not switched to the owning company');
    }

    public function test_an_unknown_resource_is_a_plain_404_and_an_unavailable_platform_says_so(): void
    {
        $outsider = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $this->actingAs($outsider)->get('/open/'.(string) Str::uuid())->assertNotFound();

        $this->register()->assertCreated();
        config(['ecosystem.platforms.files.active' => false]);
        $this->actingAs($outsider)->get('/open/'.Resource::first()->uuid)->assertStatus(503);
    }

    public function test_signed_out_people_are_sent_to_login(): void
    {
        $this->register()->assertCreated();

        $this->get('/open/'.Resource::first()->uuid)->assertRedirect('/login');
    }

    public function test_a_reference_resolves_only_for_members_and_only_with_the_real_owner_code(): void
    {
        $team = $this->company('Northwind Finance');
        $this->register(['team_uuid' => $team->uuid])->assertCreated();
        $resource = Resource::first()->load('team');
        $text = Reference::for($resource);

        $member = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team->users()->attach($member, ['role' => 'editor']);
        $outsider = User::factory()->create(['email_verified_at' => Carbon::now()]);

        $this->actingAs($member);
        $this->assertTrue(Reference::resolve($text)?->is($resource));
        $this->assertNull(Reference::resolve('ZZZZ-F-'.substr($text, -6)), 'a forged owner code opens nothing');

        $this->actingAs($outsider);
        $this->assertNull(Reference::resolve($text), 'a person outside the company cannot resolve it');
    }
}

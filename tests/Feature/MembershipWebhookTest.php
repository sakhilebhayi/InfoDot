<?php

namespace Tests\Feature;

use App\Actions\Jetstream\RemoveTeamMember;
use App\Models\Team;
use App\Models\User;
use App\Support\MembershipWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Jetstream\Actions\UpdateTeamMemberRole;
use Tests\TestCase;

/**
 * Access ends in seconds: removing someone from a company, changing their role or deleting
 * the company tells the platforms that hold a copy, signed with each platform's own secret.
 */
class MembershipWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ecosystem.trusted_domains' => ['infodot.co.za'],
            'ecosystem.membership_platforms' => ['files', 'docs'],
            'ecosystem.service_secrets' => ['files' => 'files-secret', 'docs' => 'docs-secret'],
            'ecosystem.platforms.files' => ['name' => 'Dot.Files', 'url' => 'https://files.infodot.co.za', 'icon' => 'folder', 'active' => true],
            'ecosystem.platforms.docs' => ['name' => 'Dot.docs', 'url' => 'https://doc.infodot.co.za', 'icon' => 'description', 'active' => true],
        ]);

        $this->swap(HttpFactory::class, new HttpFactory);
        Http::fake(['*' => Http::response(['ok' => true], 202)]);
    }

    private function company(): array
    {
        $owner = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => 'Northwind']);
        $member = User::factory()->create(['email_verified_at' => Carbon::now(), 'email' => 'Thabo@Example.test', 'current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => 'editor']);

        return [$owner, $team, $member];
    }

    private function sent(string $host): ?Request
    {
        return collect(Http::recorded())->map(fn ($pair) => $pair[0])->first(fn (Request $r) => str_contains($r->url(), $host));
    }

    public function test_removing_a_member_tells_files_and_docs_with_a_valid_signature(): void
    {
        [$owner, $team, $member] = $this->company();

        (new RemoveTeamMember)->remove($owner, $team, $member);
        app()->terminate();

        foreach (['files.infodot.co.za' => 'files-secret', 'doc.infodot.co.za' => 'docs-secret'] as $host => $secret) {
            $request = $this->sent($host);
            $this->assertNotNull($request, "$host was told");
            $this->assertSame("https://$host/api/ecosystem/membership", $request->url());

            $timestamp = (int) $request->header(MembershipWebhook::HEADER_TIMESTAMP)[0];
            $this->assertEqualsWithDelta(time(), $timestamp, 5);
            $this->assertSame(MembershipWebhook::sign($secret, $timestamp, $request->body()), $request->header(MembershipWebhook::HEADER_SIGNATURE)[0]);

            $this->assertSame(
                ['event' => 'member.removed', 'team_uuid' => $team->uuid, 'email' => 'thabo@example.test'],
                json_decode($request->body(), true),
                'identifiers only, nothing else'
            );
        }
    }

    public function test_a_signature_made_with_another_platforms_secret_does_not_verify(): void
    {
        [$owner, $team, $member] = $this->company();
        (new RemoveTeamMember)->remove($owner, $team, $member);
        app()->terminate();

        $request = $this->sent('files.infodot.co.za');
        $timestamp = (int) $request->header(MembershipWebhook::HEADER_TIMESTAMP)[0];

        $this->assertNotSame(MembershipWebhook::sign('docs-secret', $timestamp, $request->body()), $request->header(MembershipWebhook::HEADER_SIGNATURE)[0]);
    }

    public function test_a_role_change_carries_the_new_role(): void
    {
        [$owner, $team, $member] = $this->company();

        (new UpdateTeamMemberRole)->update($owner, $team, $member->id, 'admin');
        app()->terminate();

        $this->assertSame(
            ['event' => 'member.role_changed', 'team_uuid' => $team->uuid, 'email' => 'thabo@example.test', 'role' => 'admin', 'infodot_role' => 'admin'],
            json_decode($this->sent('files.infodot.co.za')->body(), true)
        );
    }

    public function test_deleting_a_company_tells_the_platforms_without_naming_anyone(): void
    {
        [, $team] = $this->company();
        $uuid = $team->uuid;

        $team->delete();
        app()->terminate();

        $this->assertSame(['event' => 'team.deleted', 'team_uuid' => $uuid], json_decode($this->sent('files.infodot.co.za')->body(), true));
    }

    public function test_personal_teams_are_never_announced(): void
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $personal = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $other = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $personal->users()->attach($other, ['role' => 'editor']);

        (new RemoveTeamMember)->remove($user, $personal, $other);
        app()->terminate();

        Http::assertNothingSent();
    }

    public function test_a_platform_without_a_secret_or_on_an_untrusted_host_is_skipped(): void
    {
        config([
            'ecosystem.service_secrets' => ['files' => 'files-secret'],
            'ecosystem.platforms.files.url' => 'https://evil.example',
        ]);
        [$owner, $team, $member] = $this->company();

        (new RemoveTeamMember)->remove($owner, $team, $member);
        app()->terminate();

        Http::assertNothingSent();
    }

    public function test_an_unreachable_platform_never_breaks_the_screen_that_caused_it(): void
    {
        $this->swap(HttpFactory::class, new HttpFactory);
        Http::fake(['*' => Http::response('boom', 500)]);
        [$owner, $team, $member] = $this->company();

        (new RemoveTeamMember)->remove($owner, $team, $member);
        app()->terminate();

        $this->assertFalse($team->fresh()->hasUser($member), 'the removal itself stands');
    }
}

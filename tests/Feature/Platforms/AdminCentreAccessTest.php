<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformCatalogueEvent;
use App\Models\PlatformEntitlement;
use App\Models\PlatformEntitlementEvent;
use App\Models\PlatformPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Who gets into the admin centre (R-50 section 9): the platform administrator, and nobody else. Every route is
 * tried as an administrator, a moderator, a company owner, a member and a signed-out visitor, with a body that would
 * succeed for an administrator, and nothing may change for the other four.
 */
class AdminCentreAccessTest extends AdminCentreTestCase
{
    private Team $team;

    private PlatformPlan $plan;

    private int $bundleId;

    private int $outboxId;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = $this->administrator();
        $this->team = $this->company();
        $this->plan = $this->plan('hr', ['trial_days' => 14], true, $admin);
        $this->bundleId = (int) $this->bundle(['forms', 'projects'], false, $admin)->id;
        $this->entitlements()->grantComplimentary($this->team, 'forms', $admin, null, self::WHY);
        $this->outboxId = (int) DB::table('entitlement_outbox')->value('id');
        DB::table('entitlement_outbox')->update(['failed_at' => now()]);
    }

    /** Every admin centre route, with the body that makes it succeed. @return list<array{0: string, 1: string, 2: array<string, mixed>}> */
    private function doors(): array
    {
        $company = '/admin/platforms/companies/'.$this->team->uuid;
        $why = ['reason' => self::WHY];

        return [
            ['GET', '/admin/platforms', []],
            ['GET', '/admin/platforms/catalogue/hr', []],
            ['POST', '/admin/platforms/catalogue/hr', $why + ['operational_status' => 'maintenance', 'discoverable' => 1]],
            ['POST', '/admin/platforms/catalogue/projects/plans', $why + ['name' => 'Starter', 'model' => 'subscription', 'amount_minor' => 9900, 'currency' => 'ZAR', 'interval' => 'month']],
            ['GET', '/admin/platforms/plans/'.$this->plan->id, []],
            ['POST', '/admin/platforms/plans/'.$this->plan->id, $why + ['name' => 'Team', 'model' => 'subscription', 'amount_minor' => 29900, 'currency' => 'ZAR', 'interval' => 'month', 'active' => 1]],
            ['POST', '/admin/platforms/plans/'.$this->plan->id.'/withdraw', $why],
            ['POST', '/admin/platforms/plans/'.$this->plan->id.'/approve', $why + ['confirm' => 1]],
            ['GET', '/admin/platforms/bundles', []],
            ['POST', '/admin/platforms/bundles', $why + ['name' => 'Trio', 'platforms' => ['hr', 'forms', 'projects'], 'amount_minor' => 59900, 'currency' => 'ZAR']],
            ['GET', '/admin/platforms/bundles/'.$this->bundleId, []],
            ['POST', '/admin/platforms/bundles/'.$this->bundleId, $why + ['name' => 'Office pair', 'platforms' => ['forms', 'projects', 'hr'], 'amount_minor' => 39900, 'currency' => 'ZAR']],
            ['POST', '/admin/platforms/bundles/'.$this->bundleId.'/approve', $why + ['confirm' => 1]],
            ['POST', '/admin/platforms/bundles/'.$this->bundleId.'/withdraw', $why],
            ['GET', '/admin/platforms/companies', []],
            ['GET', $company, []],
            ['POST', $company.'/grant', $why + ['platform' => 'projects']],
            ['POST', $company.'/trial', $why + ['platform' => 'hr', 'days' => 14]],
            ['POST', $company.'/suspend', $why + ['platform' => 'forms', 'confirm' => 1]],
            ['POST', $company.'/restore', $why + ['platform' => 'forms']],
            ['POST', $company.'/access', $why + ['platform' => 'forms', 'mode' => 'selected']],
            ['POST', $company.'/cancel', $why + ['platform' => 'forms', 'confirm' => 1]],
            ['GET', '/admin/platforms/outbox', []],
            ['POST', '/admin/platforms/outbox/'.$this->outboxId.'/retry', []],
            ['GET', '/admin/platforms/payments', []],
            ['GET', '/admin/platforms/audit', []],
        ];
    }

    /** @return array<string, int> */
    private function fingerprint(): array
    {
        return [
            'catalogue_events' => PlatformCatalogueEvent::count(),
            'entitlement_events' => PlatformEntitlementEvent::count(),
            'plans' => PlatformPlan::count(),
            'bundles' => DB::table('platform_bundles')->count(),
            'entitlements' => PlatformEntitlement::count(),
            'outbox' => DB::table('entitlement_outbox')->count(),
            'parked' => DB::table('entitlement_outbox')->whereNotNull('failed_at')->count(),
            'checksum' => crc32((string) json_encode([DB::table('platform_catalogue')->get(), DB::table('platform_plans')->get(), DB::table('platform_entitlements')->get()])),
        ];
    }

    public function test_the_doors_listed_here_are_all_the_admin_centre_routes(): void
    {
        $registered = collect(app('router')->getRoutes())->filter(fn ($route) => str_starts_with((string) $route->getName(), 'admin.platforms'));

        // Each registered route appears in doors() (decision and action routes several times), and each carries the gate.
        $this->assertCount(19, $registered);

        foreach ($registered as $route) {
            $this->assertContains('platform.admin', $route->gatherMiddleware(), $route->uri());
            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), $route->uri());
            $this->assertContains('verified', $route->gatherMiddleware(), $route->uri());
        }

        foreach ($this->doors() as [$method, $uri]) {
            $this->assertNotNull(app('router')->getRoutes()->match(request()->create($uri, $method))->getName());
        }
    }

    public function test_a_signed_out_visitor_is_sent_to_sign_in_and_nothing_changes(): void
    {
        $before = $this->fingerprint();

        foreach ($this->doors() as [$method, $uri, $body]) {
            $this->call($method, $uri, $body)->assertRedirect('/login');
        }

        $this->assertSame($before, $this->fingerprint());
    }

    public function test_a_moderator_an_owner_an_administrator_of_the_company_and_a_member_are_refused_everywhere(): void
    {
        $before = $this->fingerprint();
        $people = [
            'moderator' => $this->moderator(),
            'owner' => $this->team->owner,
            'company administrator' => $this->memberOf($this->team, 'admin'),
            'member' => $this->memberOf($this->team),
            'moderator who owns the company' => tap($this->company('Second Yard')->owner, fn (User $u) => $u->forceFill(['is_moderator' => true])->save())->fresh(),
        ];

        foreach ($people as $who => $person) {
            foreach ($this->doors() as [$method, $uri, $body]) {
                $this->actingAs($person)->call($method, $uri, $body)->assertForbidden();
            }

            $this->assertSame($before, $this->fingerprint(), "the $who changed something");
        }
    }

    public function test_an_administrator_whose_email_is_not_verified_is_not_let_in(): void
    {
        $administrator = $this->administrator();
        $administrator->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($administrator->fresh())->get('/admin/platforms')->assertRedirect();
        $this->actingAs($administrator->fresh())->post('/admin/platforms/catalogue/hr', ['reason' => self::WHY, 'operational_status' => 'maintenance'])->assertRedirect();
        $this->assertSame('operational', DB::table('platform_catalogue')->where('key', 'hr')->value('operational_status'));
    }

    public function test_a_platform_administrator_gets_through_every_door(): void
    {
        $administrator = $this->administrator();

        foreach ($this->doors() as [$method, $uri, $body]) {
            $response = $this->actingAs($administrator)->call($method, $uri, $body);

            if ($method === 'GET') {
                $response->assertOk();
            } else {
                $response->assertRedirect()->assertSessionHasNoErrors();
                $this->assertNotEmpty(session('status'), "$uri gave no confirmation");
            }
        }
    }

    public function test_the_navigation_shows_the_admin_centre_to_administrators_only(): void
    {
        $this->actingAs($this->administrator())->get('/admin/platforms')->assertSee('Platform admin')->assertSee('Catalogue');
        $this->actingAs($this->moderator())->get('/home')->assertDontSee('href="'.route('admin.platforms').'"', false);
        $this->actingAs($this->team->owner)->get('/home')->assertDontSee('href="'.route('admin.platforms').'"', false);
    }

    public function test_a_personal_workspace_is_not_a_company_here(): void
    {
        $administrator = $this->administrator();
        $personal = $administrator->currentTeam;

        $this->actingAs($administrator)->get('/admin/platforms/companies/'.$personal->uuid)->assertNotFound();
        $this->actingAs($administrator)->post('/admin/platforms/companies/'.$personal->uuid.'/grant', ['platform' => 'hr', 'reason' => self::WHY])->assertNotFound();
        $this->assertSame(0, PlatformEntitlement::where('team_id', $personal->id)->count());
    }
}

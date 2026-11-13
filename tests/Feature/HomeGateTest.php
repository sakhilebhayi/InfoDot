<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * R-49. The Home gate is marked by the company's owner code, stencilled in gold, not by a coloured bar down one side of
 * the company name (the one side stripe the design pass had left).
 */
class HomeGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_gate_shows_the_owner_code_beside_the_company_name(): void
    {
        $owner = User::factory()->withPersonalTeam()->create(['email_verified_at' => Carbon::now()]);
        $company = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => 'Ledger Works']);
        $owner->forceFill(['current_team_id' => $company->id])->save();

        $html = (string) $this->actingAs($owner->fresh())->get('/home')->assertOk()->getContent();

        $this->assertNotEmpty($company->fresh()->code);
        $this->assertMatchesRegularExpression('#<h1>Ledger Works</h1>#', $html);
        $this->assertStringContainsString('<p class="ix-gate__code"><span class="ix-sr">Owner code </span>'.$company->fresh()->code.'</p>', $html);
    }

    public function test_the_gate_has_no_stripe_down_one_side(): void
    {
        $css = (string) file_get_contents(resource_path('css/infodot.css'));
        preg_match_all('/\.ix-gate[a-z_-]*\s*\{[^}]*\}/', $css, $rules);

        $this->assertNotEmpty($rules[0]);
        foreach ($rules[0] as $rule) {
            $this->assertDoesNotMatchRegularExpression('/border-(left|right|inline-start|inline-end)\s*:/', $rule);
        }
        $this->assertMatchesRegularExpression('/\.ix-gate__code\s*\{[^}]*font-family:\s*var\(--ix-stencil-font\)[^}]*color:\s*var\(--ix-stencil\)/s', $css);
    }
}

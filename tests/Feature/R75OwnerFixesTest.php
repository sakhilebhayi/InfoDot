<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R-75 owner fixes (2026-11-27), from the three-visitor review: the hero says what InfoDot is and promises less, the access
 * sentence says how to find out, the sign-up page does not frighten people without a company, and two listings read right.
 */
class R75OwnerFixesTest extends TestCase
{
    use RefreshDatabase;

    private function text(string $url): string
    {
        return html_entity_decode(strip_tags((string) $this->get($url)->assertOk()->getContent()), ENT_QUOTES | ENT_HTML5);
    }

    public function test_the_hero_says_what_infodot_is_and_does_not_overpromise(): void
    {
        $text = $this->text('/');

        $this->assertStringContainsString('A better, more connected way to build your business.', $text);
        $this->assertStringContainsString("Give the business you've been dreaming about a place to grow, and get more of it right the first time.", $text);
        $this->assertStringContainsString('InfoDot: free business answers, connected tools and a directory of businesses, in one account.', $text);
        $this->assertStringNotContainsString('grow, get it right the first time', $text);
    }

    public function test_every_access_sentence_that_needs_it_says_how_to_find_out(): void
    {
        foreach (['/', '/about', '/features', '/faqs'] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsStringIgnoringCase('to ask what', strip_tags($html), "{$url} says how to find out");
            $this->assertStringContainsString('href="'.route('contact').'"', $html, "{$url} links the contact form");
        }
    }

    public function test_sign_up_says_the_company_can_be_just_you(): void
    {
        $text = $this->text('/register');

        $this->assertStringContainsString("Next, you'll set up your company on InfoDot: it can be just you, and it doesn't need to be registered.", $text);
        $this->assertStringNotContainsString('You will set up your company next.', $text);
    }

    public function test_the_two_listings_are_corrected_and_only_exact_matches_change(): void
    {
        $blupin = Team::factory()->create(['name' => 'BluPin AI & Coding Institue', 'personal_team' => false]);
        $infodot = Team::factory()->create(['name' => 'InfoDot', 'personal_team' => false]);
        $other = Team::factory()->create(['name' => 'Some Agency', 'personal_team' => false]);
        DB::table('team_profiles')->insert([
            ['team_id' => $blupin->id, 'trading_name' => 'BluPin AI & Coding Institue', 'business_type' => 'company', 'created_at' => now(), 'updated_at' => now()],
            ['team_id' => $infodot->id, 'trading_name' => null, 'business_type' => 'public_entity', 'created_at' => now(), 'updated_at' => now()],
            ['team_id' => $other->id, 'trading_name' => null, 'business_type' => 'public_entity', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration = require database_path('migrations/2026_11_27_190000_correct_two_directory_listings.php');
        $migration->up();

        $this->assertSame('BluPin AI & Coding Institute', $blupin->fresh()->name);
        $this->assertSame('BluPin AI & Coding Institute', TeamProfile::where('team_id', $blupin->id)->value('trading_name'));
        $this->assertNull(TeamProfile::where('team_id', $infodot->id)->value('business_type'));
        $this->assertSame('public_entity', TeamProfile::where('team_id', $other->id)->value('business_type'), 'a real public entity keeps its type');

        $migration->down();
        $this->assertSame('BluPin AI & Coding Institue', $blupin->fresh()->name);
        $this->assertSame('public_entity', TeamProfile::where('team_id', $infodot->id)->value('business_type'));
    }
}

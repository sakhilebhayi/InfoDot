<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\Profile\ProfilePhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * R-49, the structural items the design pass left: U-12 (a picture is checked in the browser before it is uploaded),
 * U-13 (the compulsory topics come straight after the title), U-18 (the long business editor on a phone keeps its index
 * in reach) and the contact page's layout. The behaviour in a real browser is in tests/Browser (g-failure-paths).
 */
class StructuralUxItemsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->withPersonalTeam()->create(['email_verified_at' => Carbon::now()]);
        $company = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => 'Ledger Works']);
        $owner->forceFill(['current_team_id' => $company->id])->save();

        return $owner->fresh();
    }

    public function test_the_photo_field_checks_size_and_type_before_anything_is_uploaded(): void
    {
        $html = (string) $this->actingAs($this->owner())->get('/user/profile/edit')->assertOk()->getContent();
        preg_match('/<input type="file" id="photo".*?" \/>/s', $html, $input);   // the handler holds a ">" of its own

        $this->assertNotEmpty($input, 'the photo field');
        // Bound to Livewire directly, the file would start uploading the moment it is chosen.
        $this->assertStringNotContainsString('wire:model', $input[0]);
        $this->assertStringContainsString('file.size > photoMax', $input[0]);
        $this->assertStringContainsString('$wire.upload(', $input[0]);
        $this->assertStringContainsString('photoMax: '.(ProfilePhoto::MAX_KILOBYTES * 1024), $html, 'the browser uses the same limit as the server');
        $this->assertStringContainsString('id="photo-check" role="alert"', $html);
        $this->assertStringContainsString('up to '.(int) (ProfilePhoto::MAX_KILOBYTES / 1024).' MB', $html);
    }

    public function test_topics_come_straight_after_the_title_on_the_ask_and_write_forms(): void
    {
        $owner = $this->owner();

        foreach (['/questions/ask' => ['id="question"', 'id="description"'], '/solution/create' => ['id="solution_title"', 'id="solution_description"']] as $url => [$title, $next]) {
            $html = (string) $this->actingAs($owner)->get($url)->assertOk()->assertSee('Required: choose 1 to')->getContent();
            $topics = strpos($html, 'class="ix-topicpick"');

            $this->assertNotFalse($topics, $url);
            $this->assertGreaterThan(strpos($html, $title), $topics, $url.': topics come after the title');
            $this->assertLessThan(strpos($html, $next), $topics, $url.': and before the next field');
            $this->assertLessThan(strpos($html, 'data-uploader') ?: PHP_INT_MAX, $topics, $url.': and above the attachments');
        }
    }

    public function test_the_business_editor_keeps_its_index_in_reach_on_a_phone(): void
    {
        $owner = $this->owner();
        $html = (string) $this->actingAs($owner)->get('/business/'.$owner->currentTeam->uuid.'/edit')->assertOk()->getContent();

        $this->assertStringContainsString('<nav class="ix-settings__index" id="sections" aria-label="Profile sections">', $html);
        $this->assertStringContainsString('<a class="ix-settings__up" href="#sections">Sections</a>', $html);

        $css = (string) file_get_contents(resource_path('css/infodot.css'));
        $this->assertMatchesRegularExpression('/\.ix-settings__up\s*\{\s*display:\s*none;/', $css, 'hidden where the index stays beside the form');
        $this->assertMatchesRegularExpression('/@media \(max-width: 899px\)\s*\{.*?\.ix-settings__up\s*\{[^}]*position:\s*sticky;[^}]*min-height:\s*44px;/s', $css);
    }

    public function test_the_contact_page_uses_the_reading_layout_of_the_other_public_pages(): void
    {
        $this->get('/contact')->assertOk()->assertSee('<article class="ix-prose ix-prose--ledger">', false)->assertSee('privacy@infodot.co.za');
    }
}

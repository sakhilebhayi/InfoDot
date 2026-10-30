<?php

namespace Tests\Feature\Hardening;

use App\Models\Team;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The accessibility facts an HTTP test can actually check, on the pages people use most: a language on the page, a viewport that allows zooming,
 * exactly one h1, every form control labelled, every image with alt text, and no positive tabindex. This is a floor, not an audit: colour
 * contrast, focus order and screen-reader behaviour need a browser and a person, and nothing here claims them.
 */
class AccessibilityBaselineTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    private function dom(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        @$doc->loadHTML($html);

        return new DOMXPath($doc);
    }

    /** @return list<string> everything wrong on this page, empty when it passes */
    private function faults(string $url, string $html): array
    {
        $x = $this->dom($html);
        $faults = [];

        if ($x->query('//html[@lang]')->length !== 1) {
            $faults[] = "$url: the html element has no lang attribute";
        }

        foreach ($x->query('//meta[@name="viewport"]') as $meta) {
            $content = strtolower((string) $meta->getAttribute('content'));

            if (str_contains($content, 'user-scalable=no') || str_contains($content, 'maximum-scale=1')) {
                $faults[] = "$url: the viewport forbids zooming";
            }
        }

        $h1 = $x->query('//h1')->length;

        if ($h1 !== 1) {
            $faults[] = "$url: expected exactly one h1, found $h1";
        }

        foreach ($x->query('//input[not(@type="hidden")] | //textarea | //select') as $control) {
            $id = (string) $control->getAttribute('id');
            $labelled = ($id !== '' && $x->query('//label[@for="'.$id.'"]')->length > 0)
                || $control->getAttribute('aria-label') !== ''
                || $control->getAttribute('aria-labelledby') !== ''
                || $x->query('ancestor::label', $control)->length > 0
                || ($control->getAttribute('type') === 'submit');

            if (! $labelled) {
                $faults[] = "$url: an unlabelled ".$control->nodeName.' (name="'.$control->getAttribute('name').'")';
            }
        }

        foreach ($x->query('//img[not(@alt)]') as $img) {
            $faults[] = "$url: an image with no alt attribute (src=".substr((string) $img->getAttribute('src'), 0, 60).')';
        }

        foreach ($x->query('//*[@tabindex]') as $el) {
            if ((int) $el->getAttribute('tabindex') > 0) {
                $faults[] = "$url: a positive tabindex on ".$el->nodeName;
            }
        }

        return $faults;
    }

    public function test_the_signed_out_pages_meet_the_baseline(): void
    {
        $faults = [];

        foreach (['/login', '/register', '/forgot-password', '/', '/contact'] as $url) {
            $faults = array_merge($faults, $this->faults($url, $this->get($url)->assertOk()->getContent()));
        }

        $this->assertSame([], $faults, implode("\n", $faults));
    }

    public function test_the_main_signed_in_pages_meet_the_baseline(): void
    {
        $member = $this->member();
        $faults = [];

        foreach (['/questions', '/solutions', '/seminars', '/notifications', '/home'] as $url) {
            $faults = array_merge($faults, $this->faults($url, $this->actingAs($member)->get($url)->assertOk()->getContent()));
        }

        $this->assertSame([], $faults, implode("\n", $faults));
    }

    public function test_the_forms_people_depend_on_meet_the_baseline(): void
    {
        $member = $this->member();
        $faults = [];

        foreach (['/seminars/new', '/questions/ask', '/user/profile/edit'] as $url) {
            $faults = array_merge($faults, $this->faults($url, $this->actingAs($member)->get($url)->assertOk()->getContent()));
        }

        $this->assertSame([], $faults, implode("\n", $faults));
    }
}

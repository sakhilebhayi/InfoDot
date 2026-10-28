<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Support\OptionalVite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * No link and no form on a page a person can reach may lead to an error. The test opens the entry pages as a visitor and as a signed-in
 * person, follows every internal link once, and checks every form posts to a route that exists.
 */
class LinkIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const ENTRY = ['/', '/about', '/features', '/faqs', '/contact', '/complains', '/policy', '/terms', '/cookies', '/solution-results', '/solution-results?search=register', '/login', '/register', '/forgot-password'];

    private const SIGNED_IN_ENTRY = ['/home', '/questions', '/questions/ask', '/solutions', '/solution/create', '/topics', '/library', '/platforms', '/people', '/analytics', '/points', '/notifications', '/user/profile/edit'];

    private function person(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now(), 'is_moderator' => true]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    /** @return array{links: list<string>, forms: list<array{method: string, action: string}>} */
    private function harvest(string $html): array
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $links = [];
        preg_match_all('/<a\b[^>]*\bhref="([^"]*)"/i', $html, $m);
        foreach ($m[1] as $href) {
            $href = html_entity_decode($href);
            if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto:|tel:|javascript:)#i', $href)) {
                continue;
            }
            $parts = parse_url($href);
            if (isset($parts['host']) && $parts['host'] !== $host && $parts['host'] !== 'localhost' && $parts['host'] !== '127.0.0.1') {
                continue; // another site: not ours to check here
            }
            $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
            // Opening a platform signs a token over to another host and is covered by its own tests.
            if (str_starts_with($path, '/go/') || str_starts_with($path, '/open/') || str_starts_with($path, '/sub-services/') || str_starts_with($path, '/logout')) {
                continue;
            }
            $links[$path] = true;
        }

        $forms = [];
        preg_match_all('/<form\b([^>]*)>/i', $html, $f);
        foreach ($f[1] as $attrs) {
            preg_match('/\baction="([^"]*)"/i', $attrs, $a);
            preg_match('/\bmethod="([^"]*)"/i', $attrs, $me);
            if (! empty($a[1])) {
                $forms[] = ['method' => strtoupper($me[1] ?? 'GET'), 'action' => html_entity_decode($a[1])];
            }
        }

        return ['links' => array_keys($links), 'forms' => $forms];
    }

    private function assertNothingBreaks(array $entry, ?User $as, string $who): void
    {
        $checked = [];
        foreach ($entry as $page) {
            $response = $as ? $this->actingAs($as)->get($page) : $this->get($page);
            $this->assertContains($response->getStatusCode(), [200, 302], "{$who}: {$page} answered ".$response->getStatusCode());
            if ($response->getStatusCode() !== 200) {
                continue;
            }

            $found = $this->harvest($response->getContent());

            foreach ($found['links'] as $link) {
                if (isset($checked[$link])) {
                    continue;
                }
                $checked[$link] = true;
                $r = $as ? $this->actingAs($as)->get($link) : $this->get($link);
                $this->assertLessThan(400, $r->getStatusCode(), "{$who}: the link {$link} on {$page} answered ".$r->getStatusCode());
            }

            foreach ($found['forms'] as $form) {
                $path = parse_url($form['action'], PHP_URL_PATH) ?: '/';
                $request = Request::create($path, $form['method'] === 'GET' ? 'GET' : 'POST');
                try {
                    Route::getRoutes()->match($request);
                } catch (\Throwable $e) {
                    $this->fail("{$who}: the form on {$page} posts to {$form['method']} {$path}, which no route answers");
                }
            }
        }

        $this->assertNotEmpty($checked, "{$who}: no links were found to check");
    }

    public function test_a_visitor_can_follow_every_link_and_every_form_leads_somewhere(): void
    {
        $this->assertNothingBreaks(self::ENTRY, null, 'visitor');
    }

    public function test_a_signed_in_person_can_follow_every_link_and_every_form_leads_somewhere(): void
    {
        $this->assertNothingBreaks(array_merge(['/'], self::SIGNED_IN_ENTRY), $this->person(), 'signed in');
    }

    public function test_the_logo_always_leads_home_and_home_always_opens(): void
    {
        foreach (['/about', '/solution-results', '/policy', '/login'] as $from) {
            $html = $this->get($from)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('#<a href="[^"]*"[^>]*aria-label="InfoDot home"#', $html, $from);
            $this->get('/')->assertOk();
        }
    }

    public function test_the_landing_page_still_opens_when_its_motion_script_is_missing_from_the_build(): void
    {
        $this->assertSame('', OptionalVite::tags('resources/js/this-file-does-not-exist.js'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * R-49. Six placeholder pages under /sub-services said a platform "has no workspace inside InfoDot yet". Nothing
 * linked to them; they are gone. This keeps them gone: the addresses answer 404 for everyone and no route, view or
 * sitemap entry brings them back.
 */
class RetiredPlaceholderPagesTest extends TestCase
{
    use RefreshDatabase;

    private const RETIRED = ['dot-files', 'dot-docs', 'dot-sheets', 'dot-press', 'dot-forms', 'dot-engage'];

    public function test_the_placeholder_addresses_answer_404_signed_in_or_not(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        foreach (self::RETIRED as $page) {
            $this->get('/sub-services/'.$page)->assertNotFound();
        }
        $this->actingAs($user);
        foreach (self::RETIRED as $page) {
            $this->get('/sub-services/'.$page)->assertNotFound();
        }
    }

    public function test_no_route_or_view_is_left_behind(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('sub-services', $route->uri());
            $this->assertStringStartsNotWith('subservices.', (string) $route->getName());
        }
        $this->assertDirectoryDoesNotExist(resource_path('views/sub-services'));
    }

    public function test_the_sitemap_never_listed_them_and_still_lists_the_public_pages(): void
    {
        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString('sub-services', $xml);
        foreach (['about', 'features', 'faqs', 'policy'] as $name) {
            $this->assertStringContainsString('<loc>'.route($name).'</loc>', $xml);
        }
    }
}

<?php

namespace Tests\Feature\Stabilisation;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A person who meets an error page should learn what happened and what to do next, and after a 500 should have something to
 * quote: the request reference that is on the matching log line. Each status is raised the way the application raises
 * it, through the exception handler, with debug off.
 */
class ErrorPagesTest extends TestCase
{
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        $this->secret = 'detail-'.Str::random(12);

        Route::middleware('web')->group(function () {
            Route::get('/_stabilisation/abort/{status}', fn (int $status) => abort($status, 'internal reason '.$this->secret));
            Route::get('/_stabilisation/broken', fn () => throw new \RuntimeException('SQLSTATE[HY000] '.$this->secret));
        });
    }

    /** @return array<string, array{int, string, string}> */
    public static function statuses(): array
    {
        return [
            '403' => [403, "You don't have access to this", 'check with whoever owns this workspace'],
            '404' => [404, "We couldn't find that page", 'Double-check the link'],
            '419' => [419, 'Your session timed out', 'Go back and retry'],
            '429' => [429, 'Slow down a little', 'Wait a minute and try again'],
            '503' => [503, "We'll be right back", 'Check back shortly'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_each_status_says_what_happened_and_what_to_do_next(int $status, string $what, string $next): void
    {
        $r = $this->get("/_stabilisation/abort/$status")->assertStatus($status);

        $r->assertSee($what)->assertSee($next)->assertSee('Error '.$status)->assertSee('href="/"', false);
        $r->assertDontSee($this->secret);
    }

    public function test_an_address_that_does_not_exist_gets_the_404_page(): void
    {
        $this->get('/no-such-page-'.Str::random(8))->assertNotFound()->assertSee("We couldn't find that page");
    }

    public function test_the_500_page_shows_the_request_reference_and_no_exception_text(): void
    {
        $r = $this->get('/_stabilisation/broken')->assertStatus(500);
        $id = (string) $r->headers->get('X-Request-Id');

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $id);
        $r->assertSee('Something went wrong on our end')->assertSee('Reference')->assertSee($id)->assertSee('quote this reference');
        $r->assertDontSee($this->secret)->assertDontSee('SQLSTATE')->assertDontSee('RuntimeException')->assertDontSee('ErrorPagesTest.php');
    }

    public function test_a_callers_own_reference_is_the_one_shown(): void
    {
        $this->get('/_stabilisation/broken', ['X-Request-Id' => 'monitor-abc12345'])->assertStatus(500)->assertSee('monitor-abc12345');
    }

    public function test_a_json_caller_gets_no_exception_text_either(): void
    {
        $r = $this->getJson('/_stabilisation/broken')->assertStatus(500)->assertExactJson(['message' => 'Server Error']);

        $this->assertNotEmpty($r->headers->get('X-Request-Id'));
    }

    public function test_only_the_500_page_carries_a_reference(): void
    {
        $this->get('/_stabilisation/abort/404')->assertDontSee('Reference');
    }
}

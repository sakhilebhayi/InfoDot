<?php

namespace Tests\Feature\Stabilisation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The log line for an unhandled exception has to answer: which request (the reference on the 500 page), who was
 * signed in, and where in the application. It said which request and who; the route and the method were missing,
 * so "POST what?" meant reading a stack trace. What the person typed is never part of it.
 */
class ExceptionLogContextTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<MessageLogged> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logged[] = $event;
        });

        Route::middleware('web')->group(function () {
            Route::post('/_stabilisation/things/{thing}', fn () => throw new \RuntimeException('it broke'))->name('stabilisation.things.update');
            Route::get('/_stabilisation/unnamed', fn () => throw new \RuntimeException('it broke'));
        });
    }

    /** @return array<string, mixed> */
    private function errorContext(): array
    {
        $errors = array_values(array_filter($this->logged, fn (MessageLogged $e) => $e->level === 'error' && $e->message === 'it broke'));
        $this->assertCount(1, $errors, 'the exception is logged once');

        return $errors[0]->context;
    }

    public function test_an_unhandled_exception_is_logged_with_the_request_the_person_the_route_and_the_method(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $typed = 'typed-'.Str::random(16);

        $r = $this->actingAs($user)->post('/_stabilisation/things/41?note='.$typed, ['password' => $typed, 'body' => $typed])->assertStatus(500);

        $context = $this->errorContext();
        $this->assertSame($r->headers->get('X-Request-Id'), $context['request_id']);
        $this->assertSame($user->id, $context['userId']);
        $this->assertSame('stabilisation.things.update', $context['route']);
        $this->assertSame('POST', $context['method']);
        $this->assertInstanceOf(\RuntimeException::class, $context['exception']);

        // Nothing the person sent, and no address or name: the id is enough to find the account.
        $flat = json_encode(array_diff_key($context, ['exception' => 1]));
        $this->assertStringNotContainsString($typed, $flat);
        $this->assertStringNotContainsString($user->email, $flat);
        $this->assertSame(['exception', 'method', 'request_id', 'route', 'userId'], collect($context)->keys()->sort()->values()->all());
    }

    public function test_a_route_without_a_name_is_logged_by_its_pattern_and_a_visitor_has_no_user_id(): void
    {
        $this->get('/_stabilisation/unnamed')->assertStatus(500);

        $context = $this->errorContext();
        $this->assertSame('_stabilisation/unnamed', $context['route']);
        $this->assertSame('GET', $context['method']);
        $this->assertArrayNotHasKey('userId', $context);
        $this->assertNotEmpty($context['request_id']);
    }
}

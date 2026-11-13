<?php

namespace Tests\Feature\Stabilisation;

use App\Models\User;
use App\Services\Reputation\ReputationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * R-49. The nightly reputation pass works through everyone in id order and remembers where it stopped. One person
 * whose score could not be calculated ended the run before the position was saved, so every later run started at
 * the same place and stopped at the same person: nobody after them was recalculated again and nothing was logged
 * about who. One person's failure is now logged and the pass goes on.
 */
class ReputationPassResilienceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: list<int>, 1: User} the ids that were recalculated, and the person who fails */
    private function passWithOneFailure(string $command): array
    {
        config(['reputation.enabled' => true]);
        Cache::flush();
        Log::spy();
        $people = User::factory()->count(3)->create();
        $broken = $people[1];
        $done = [];

        $engine = Mockery::mock(ReputationEngine::class);
        $engine->shouldReceive('pruneSnapshots')->andReturn(0);
        $engine->shouldReceive('snapshot')->andReturnUsing(function (User $user) use ($broken, &$done) {
            if ($user->is($broken)) {
                throw new RuntimeException('could not be calculated');
            }
            $done[] = $user->id;
        });
        $this->app->instance(ReputationEngine::class, $engine);

        $this->artisan($command)->assertSuccessful();

        return [$done, $broken];
    }

    public function test_the_nightly_pass_finishes_when_one_person_cannot_be_calculated(): void
    {
        [$done, $broken] = $this->passWithOneFailure('reputation:recalculate --resume');

        $this->assertCount(2, $done);
        $this->assertNotContains($broken->id, $done);
        $this->assertSame(now()->toDateString(), Cache::get('reputation:done'));
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context = []) => $message === 'reputation.recalculate_failed' && ($context['user_id'] ?? null) === $broken->id)->once();
    }

    public function test_the_full_pass_finishes_when_one_person_cannot_be_calculated(): void
    {
        [$done, $broken] = $this->passWithOneFailure('reputation:recalculate');

        $this->assertCount(2, $done);
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context = []) => $message === 'reputation.recalculate_failed' && ($context['user_id'] ?? null) === $broken->id)->once();
    }
}

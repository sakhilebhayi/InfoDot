<?php

namespace Tests\Feature\Performance;

use App\Models\Team;
use App\Models\User;
use App\Support\LazyLoadLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** R-72 (P-27): a lazy load is logged once per model and relation in a request, and the page still gets its data. */
class LazyLoadLogTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        Model::handleLazyLoadingViolationUsing(null);
        parent::tearDown();
    }

    public function test_a_lazy_load_is_logged_once_and_still_returns_the_data(): void
    {
        Log::spy();
        Model::preventLazyLoading();
        Model::handleLazyLoadingViolationUsing(new LazyLoadLog);

        $owners = User::factory()->withPersonalTeam()->count(3)->create();
        $teams = Team::query()->whereIn('user_id', $owners->pluck('id'))->orderBy('user_id')->get();
        $this->assertCount(3, $teams);

        foreach ($teams as $i => $team) {
            $this->assertSame($owners[$i]->id, $team->owner->id, 'the relation is still loaded');
        }

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $message === 'performance.lazy_load'
            && $context['model'] === Team::class && $context['relation'] === 'owner');
    }
}

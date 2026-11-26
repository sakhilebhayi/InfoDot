<?php

namespace Tests\Feature\Performance;

use App\Models\Team;
use App\Models\User;
use App\Services\Directory\BusinessDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R-72 (P-20, P-21): the directory sorts and pages every listed business, not the first few hundred, and someone only
 * browsing does not pay for a search they cannot see.
 */
class DirectoryPagingTest extends TestCase
{
    use RefreshDatabase;

    private function listed(string $name, string $description = 'A listed business.'): Team
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => $name, 'personal_team' => false]);
        $team->profileOrNew()->forceFill(['visibility' => 'public', 'description' => $description])->save();

        return $team;
    }

    public function test_without_words_the_database_sorts_and_pages_every_business(): void
    {
        foreach (range(1, 30) as $n) {
            $this->listed(sprintf('Firm %02d', $n));
        }

        $first = app(BusinessDirectory::class)->search(['sort' => 'name', 'page' => 1], null)['results'];
        $second = app(BusinessDirectory::class)->search(['sort' => 'name', 'page' => 2], null)['results'];

        $this->assertSame(30, $first->total());
        $this->assertSame('Firm 01', $first->items()[0]->name);
        $this->assertSame(['Firm 25', 'Firm 26', 'Firm 27', 'Firm 28', 'Firm 29', 'Firm 30'], collect($second->items())->pluck('name')->all());
        $this->assertTrue($second->items()[0]->relationLoaded('logo'), 'the page shown has its pictures');
    }

    public function test_with_words_every_match_is_scored_and_paged(): void
    {
        foreach (range(1, 30) as $n) {
            $this->listed(sprintf('Firm %02d', $n), 'We drill boreholes.');
        }
        $best = $this->listed('Borehole Masters', 'Boreholes.');

        $first = app(BusinessDirectory::class)->search(['q' => 'borehole', 'page' => 1], null);
        $second = app(BusinessDirectory::class)->search(['q' => 'borehole', 'page' => 2], null)['results'];

        $this->assertSame(31, $first['results']->total());
        $this->assertSame($best->id, $first['results']->items()[0]->id, 'a name match ranks first');
        $this->assertSame(['name'], array_slice($first['reasons'][$best->id], 0, 1));
        $this->assertCount(7, $second->items());
        $this->assertTrue($second->items()[0]->relationLoaded('logo'));
    }

    public function test_browsing_runs_no_search(): void
    {
        $this->listed('Firm 01');

        $pagingCounts = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk()->assertSee('Firm 01');
            $n = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains(strtolower($q['query']), 'count(*) as aggregate from "teams"'))->count();
            DB::disableQueryLog();

            return $n;
        };

        $this->assertSame(0, $pagingCounts('/businesses'), 'no results list is counted while browsing');
        $this->assertSame(1, $pagingCounts('/businesses?sort=name'), 'a results list is counted once when asked for');
    }
}

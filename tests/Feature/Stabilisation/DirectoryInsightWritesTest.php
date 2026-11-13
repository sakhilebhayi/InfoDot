<?php

namespace Tests\Feature\Stabilisation;

use App\Models\TeamProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * R-48 performance. Measured: one directory search wrote two or three statements for every business on the page
 * (48 to 72 writes for a page of 24) to count "appeared in a search". The counts are the same; they are now written
 * in one statement per table.
 */
class DirectoryInsightWritesTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 Version/18.5 Mobile/15E148 Safari/604.1';

    private function businesses(int $n): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        for ($i = 0; $i < $n; $i++) {
            $id = DB::table('teams')->insertGetId(['user_id' => $owner->id, 'name' => "Business $i", 'personal_team' => 0, 'uuid' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('team_profiles')->insert(['team_id' => $id, 'visibility' => TeamProfile::PUBLIC, 'description' => "We do thing $i for people.", 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** @return list<string> the write statements one request ran against the two counter tables */
    private function writesDuring(string $url): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withHeader('User-Agent', self::BROWSER)->get($url)->assertOk();
        $writes = array_values(array_filter(array_column(DB::getQueryLog(), 'query'), fn ($q) => preg_match('/^(insert|update)\b.*business_(stats|search_terms)_daily/i', $q) === 1));
        DB::disableQueryLog();

        return $writes;
    }

    public function test_a_search_page_counts_every_business_on_it_in_one_statement_per_table(): void
    {
        $this->businesses(24);

        $writes = $this->writesDuring('/businesses?q=thing');

        $this->assertLessThanOrEqual(2, count($writes), implode("\n", $writes));
        $this->assertSame(24, DB::table('business_stats_daily')->where('metric', 'search')->count());
        $this->assertSame(24, (int) DB::table('business_stats_daily')->where('metric', 'search')->sum('count'));
        $this->assertSame(24, DB::table('business_search_terms_daily')->where('term', 'thing')->count());
    }

    public function test_a_second_visitor_adds_to_the_same_rows(): void
    {
        $this->businesses(5);

        $this->writesDuring('/businesses?q=thing');
        $this->flushSession();
        $this->app['session']->flush();
        $this->writesDuring('/businesses?q=thing');

        $this->assertSame(5, DB::table('business_stats_daily')->where('metric', 'search')->count());
        $this->assertSame(10, (int) DB::table('business_stats_daily')->where('metric', 'search')->sum('count'));
        $this->assertSame(10, (int) DB::table('business_search_terms_daily')->where('term', 'thing')->sum('count'));
    }

    public function test_the_same_visitor_repeating_a_search_is_counted_once(): void
    {
        $this->businesses(5);

        $this->writesDuring('/businesses?q=thing');
        $again = $this->writesDuring('/businesses?q=thing');

        $this->assertSame([], $again);
        $this->assertSame(5, (int) DB::table('business_stats_daily')->where('metric', 'search')->sum('count'));
    }
}

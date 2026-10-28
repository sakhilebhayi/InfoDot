<?php

namespace Tests\Feature\Ux;

use App\Services\Ux\FrictionReport;
use App\Services\Ux\UxSignals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/** R-12 first increment: counts only, off by default, nothing personal, and findings that say what they saw. */
class UxSignalsTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    private function on(): void
    {
        config(['ux.enabled' => true]);
    }

    private function row(string $route, string $type, string $status, int $hits, string $error = '', ?string $day = null, string $bucket = ''): void
    {
        DB::table('ux_daily_counts')->insert(['day' => $day ?? now()->toDateString(), 'route' => $route, 'type' => $type, 'status' => $status, 'error' => $error, 'bucket' => $bucket, 'hits' => $hits]);
    }

    public function test_nothing_is_counted_while_it_is_off(): void
    {
        $this->assertFalse(config('ux.enabled'));
        $this->actingAs($this->person())->get('/home');

        $this->assertSame(0, DB::table('ux_daily_counts')->count());
        $this->assertFalse(app(UxSignals::class)->record('nav.view', '/home', 'success'));
    }

    public function test_a_page_view_is_counted_under_its_route_template_not_its_address(): void
    {
        $this->on();
        $asker = $this->person();
        $question = $this->question($asker, 'public', 'A private-sounding title');

        $this->actingAs($asker)->get('/question/view/'.$question->id.'?utm=abc')->assertOk();

        $routes = DB::table('ux_daily_counts')->pluck('route')->all();
        $this->assertContains('/question/view/{qid}', $routes);
        foreach ($routes as $route) {
            $this->assertStringNotContainsString((string) $question->id, $route);
            $this->assertStringNotContainsString('utm', $route);
        }
    }

    public function test_the_same_page_counted_twice_is_one_row_with_two_hits(): void
    {
        $this->on();
        $me = $this->person();

        $this->actingAs($me)->get('/home');
        $this->actingAs($me)->get('/home');

        $row = DB::table('ux_daily_counts')->where(['route' => '/home', 'type' => 'nav.view', 'status' => 'success'])->first();
        $this->assertSame(2, (int) $row->hits);
        $this->assertSame(1, DB::table('ux_daily_counts')->where('route', '/home')->count());
    }

    public function test_an_unknown_address_is_counted_as_a_group_and_the_address_is_dropped(): void
    {
        $this->on();

        $this->actingAs($this->person())->get('/private-looking/anna.smith@example.com')->assertNotFound();

        $row = DB::table('ux_daily_counts')->where('error', 'not_found')->first();
        $this->assertNotNull($row);
        $this->assertSame('/{unmatched}', $row->route);
        $this->assertStringNotContainsString('anna', json_encode(DB::table('ux_daily_counts')->get()));
    }

    public function test_a_form_that_sends_people_back_is_counted_as_validation_failure(): void
    {
        $this->on();
        $me = $this->person();

        $this->actingAs($me)->from('/notifications')->post('/notifications/preference', ['notices_in_app' => 'maybe'])->assertSessionHasErrors();

        $row = DB::table('ux_daily_counts')->where('type', 'form.validation_failed')->first();
        $this->assertNotNull($row);
        $this->assertSame('/notifications/preference', $row->route);
        $this->assertSame('validation', $row->error);
    }

    public function test_a_search_that_finds_nothing_is_counted_without_the_words(): void
    {
        $this->on();
        $this->actingAs($this->person())->get('/solution-results?search=zzqxw+nothing+matches')->assertOk();

        $row = DB::table('ux_daily_counts')->where('type', 'search.empty')->first();
        $this->assertNotNull($row);
        $this->assertSame('/solution-results', $row->route);
        $this->assertStringNotContainsString('zzqxw', json_encode(DB::table('ux_daily_counts')->get()));
    }

    public function test_the_service_refuses_anything_outside_the_contract(): void
    {
        $this->on();
        $s = app(UxSignals::class);

        $this->assertFalse($s->record('ui.hover', '/x', 'success'), 'unknown type');
        $this->assertFalse($s->record('nav.view', '/x', 'weird'), 'status not allowed for the type');
        $this->assertFalse($s->record('nav.view', '/x', 'failure', 'free text error'), 'error outside the enum');
        $this->assertFalse($s->record('nav.view', '/x?q=term', 'success'), 'a query string is not a route template');
        $this->assertFalse($s->record('nav.view', '/x@y.co', 'success'), 'nothing that looks like an address');
        $this->assertSame(0, DB::table('ux_daily_counts')->count());
        $this->assertTrue($s->record('nav.view', '/x', 'success'));
    }

    public function test_the_table_has_no_column_that_could_hold_a_person_or_text(): void
    {
        $this->assertEqualsCanonicalizing(['id', 'day', 'route', 'type', 'status', 'error', 'bucket', 'hits'], Schema::getColumnListing('ux_daily_counts'));
    }

    public function test_the_contract_types_match_dot_brains_taxonomy(): void
    {
        // The Dot.Brain contract (services/ux-interactions/src/taxonomy.js) is the source; these are the ones InfoDot uses.
        $contract = ['nav.view', 'form.submit', 'form.validation_failed', 'search.run', 'search.empty', 'request.failed', 'request.slow'];
        $this->assertEqualsCanonicalizing($contract, array_keys(UxSignals::TYPES));
    }

    public function test_findings_need_enough_observations_and_say_what_they_saw(): void
    {
        $this->row('/seek', 'form.submit', 'success', 5);
        $this->row('/seek', 'form.validation_failed', 'failure', 4, 'validation');
        $this->assertSame([], app(FrictionReport::class)->build(14)['findings'], 'nine submissions is too few to say anything');

        $this->row('/seek', 'form.validation_failed', 'failure', 30, 'validation', now()->subDay()->toDateString());
        $this->row('/solution-results', 'search.empty', 'failure', 15);
        $this->row('/solution-results', 'search.run', 'success', 25);
        $this->row('/question/view/{qid}', 'nav.view', 'success', 100);
        $this->row('/question/view/{qid}', 'request.slow', 'partial', 30, '', null, 'lt_3s');
        $this->row('/old-link', 'nav.view', 'failure', 12, 'not_found');
        $this->row('/broken', 'nav.view', 'success', 10);
        $this->row('/broken', 'request.failed', 'failure', 4, 'server');

        $report = app(FrictionReport::class)->build(14);
        $byRule = collect($report['findings'])->keyBy(fn ($f) => $f['rule'].'@'.$f['route']);

        $this->assertEqualsCanonicalizing(
            ['validation_failures@/seek', 'empty_searches@/solution-results', 'slow_pages@/question/view/{qid}', 'not_found@/old-link', 'server_errors@/broken'],
            $byRule->keys()->all(),
        );
        $this->assertSame(34, $byRule['validation_failures@/seek']['events']);
        $this->assertSame(39, $byRule['validation_failures@/seek']['of']);
        $this->assertSame('medium', $byRule['empty_searches@/solution-results']['confidence'] === 'low' ? 'medium' : $byRule['empty_searches@/solution-results']['confidence']);
        $this->assertNotSame('', $byRule['slow_pages@/question/view/{qid}']['suggestion']);
    }

    public function test_the_trend_compares_with_the_period_before(): void
    {
        $this->row('/seek', 'form.submit', 'success', 40, '', now()->subDays(20)->toDateString());
        $this->row('/seek', 'form.validation_failed', 'failure', 10, 'validation', now()->subDays(20)->toDateString());
        $this->row('/seek', 'form.submit', 'success', 40);
        $this->row('/seek', 'form.validation_failed', 'failure', 40, 'validation');

        $f = app(FrictionReport::class)->build(14)['findings'][0];

        $this->assertSame('worse', $f['change']);
        $this->assertSame(0.2, $f['previous_rate']);
    }

    public function test_only_moderators_open_the_page_and_it_says_when_counting_is_off(): void
    {
        $this->get('/moderation/friction')->assertRedirect();
        $this->actingAs($this->person())->get('/moderation/friction')->assertForbidden();

        $page = $this->actingAs($this->moderator())->get('/moderation/friction')->assertOk()->getContent();
        $this->assertStringContainsString('Counting is off', $page);
        $this->assertStringContainsString('Where people struggle', $page);
    }

    public function test_the_page_lists_findings_for_a_moderator(): void
    {
        $this->on();
        $this->row('/seek', 'form.submit', 'success', 10);
        $this->row('/seek', 'form.validation_failed', 'failure', 30, 'validation');

        $page = $this->actingAs($this->moderator())->get('/moderation/friction?days=30')->assertOk()->getContent();

        $this->assertStringNotContainsString('Counting is off', $page);
        $this->assertStringContainsString('Form sends people back', $page);
        $this->assertStringContainsString('/seek', $page);
        $this->assertStringContainsString('75.0%', $page);
    }

    public function test_pruning_keeps_the_retention_window(): void
    {
        $this->row('/a', 'nav.view', 'success', 1, '', now()->subDays(200)->toDateString());
        $this->row('/b', 'nav.view', 'success', 1, '', now()->subDays(10)->toDateString());

        $this->assertSame(1, app(UxSignals::class)->prune(180));
        $this->assertSame(['/b'], DB::table('ux_daily_counts')->pluck('route')->all());
    }
}

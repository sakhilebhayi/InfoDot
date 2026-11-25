<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use App\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** R-64 phase 5: progress is only ever drawn from a real figure, and every indicator tells assistive technology what it shows. */
class ProgressComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        view()->share('errors', new ViewErrorBag);
    }

    public function test_percent_is_worked_out_and_never_invented(): void
    {
        $this->assertSame(75, Progress::percent(3, 4));
        $this->assertSame(0, Progress::percent(0, 5));
        $this->assertSame(100, Progress::percent(5, 5));
        $this->assertSame(100, Progress::percent(7, 5), 'past the maximum counts as done, not as 140%');
        $this->assertSame(0, Progress::percent(-2, 5));
        $this->assertSame(33, Progress::percent(1, 3));
        $this->assertNull(Progress::percent(3, 0), 'nothing to measure against: no figure');
        $this->assertNull(Progress::percent(3, -1));
    }

    public function test_steps_are_counted_from_their_done_flags(): void
    {
        $r = Progress::ofSteps([['done' => true], ['done' => false], ['done' => true], ['done' => false], ['done' => false]]);

        $this->assertSame(['done' => 2, 'total' => 5, 'percent' => 40], $r);
        $this->assertSame(['done' => 0, 'total' => 0, 'percent' => null], Progress::ofSteps([]));
    }

    public function test_a_bar_is_a_progressbar_with_values_a_label_and_a_spoken_figure(): void
    {
        $html = Blade::render('<x-progress.bar label="Licences in use" :value="3" :max="4" value-text="3 of 4" />');

        $this->assertStringContainsString('role="progressbar"', $html);
        $this->assertStringContainsString('aria-valuemin="0"', $html);
        $this->assertStringContainsString('aria-valuemax="4"', $html);
        $this->assertStringContainsString('aria-valuenow="3"', $html);
        $this->assertStringContainsString('aria-valuetext="3 of 4 (75%)"', $html);
        $this->assertStringContainsString('aria-labelledby=', $html);
        $this->assertStringContainsString('Licences in use', $html);
        $this->assertStringContainsString('--p: 0.75', $html);
    }

    public function test_a_bar_with_nothing_to_measure_draws_nothing(): void
    {
        $this->assertSame('', trim(Blade::render('<x-progress.bar label="Seats" :value="2" :max="0" />')));
        $this->assertSame('', trim(Blade::render('<x-progress.ring label="Seats" :value="2" :max="0" />')));
        $this->assertSame('', trim(Blade::render('<x-progress.steps :steps="[]" />')));
    }

    public function test_a_bar_clamps_a_value_past_the_maximum_and_can_keep_its_label_for_screen_readers(): void
    {
        $html = Blade::render('<x-progress.bar label="Appointments" :value="9" :max="4" hide-label size="sm" tone="warn" />');

        $this->assertStringContainsString('aria-valuenow="4"', $html);
        $this->assertStringContainsString('--p: 1', $html);
        $this->assertStringContainsString('ix-prog--sm', $html);
        $this->assertStringContainsString('ix-prog--warn', $html);
        $this->assertMatchesRegularExpression('/class="ix-prog__label ix-sr"/', $html);
    }

    public function test_a_live_bar_adds_a_polite_announcement(): void
    {
        $html = Blade::render('<x-progress.bar label="Upload" :value="1" :max="2" live />');

        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('Upload: 50%', $html);
    }

    public function test_a_ring_shows_the_percentage_and_says_it_to_assistive_technology(): void
    {
        $html = Blade::render('<x-progress.ring label="Profile" :value="3" :max="8" :size="96" caption="Profile" />');

        $this->assertStringContainsString('role="progressbar"', $html);
        $this->assertStringContainsString('aria-valuetext="38%"', $html);
        $this->assertStringContainsString('stroke-dasharray="38 100"', $html);
        $this->assertStringContainsString('--ring: 96px', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html, 'the drawing is hidden from screen readers; the value is on the dial');
    }

    public function test_steps_mark_done_current_and_to_do_in_words_and_in_aria(): void
    {
        $html = Blade::render('<x-progress.steps label="Getting started" bar :steps="$steps" />', ['steps' => [
            ['title' => 'Create the company', 'done' => true],
            ['title' => 'Invite a colleague', 'done' => false, 'url' => '/invite'],
            ['title' => 'Switch on a platform', 'done' => false, 'note' => 'Free ones first'],
        ]]);

        $this->assertStringContainsString('<ol class="ix-steps ix-steps--list" aria-label="Getting started">', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
        $this->assertMatchesRegularExpression('/is-current[^>]*aria-current="step"/', $html);
        $this->assertStringContainsString(': done', $html);
        $this->assertStringContainsString(': next', $html);
        $this->assertStringContainsString(': to do', $html);
        $this->assertStringContainsString('<a href="/invite">Invite a colleague</a>', $html);
        $this->assertStringContainsString('1 of 3 done', $html, 'the bar above counts from the same steps');
        $this->assertStringContainsString('Free ones first', $html);
    }

    public function test_milestones_use_the_line_layout(): void
    {
        $html = Blade::render('<x-progress.steps variant="line" :steps="[[\'title\' => \'A\', \'done\' => true]]" />');

        $this->assertStringContainsString('ix-steps--line', $html);
    }

    public function test_home_shows_company_progress_from_the_real_profile(): void
    {
        $owner = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => 'Northwind Finance', 'industry' => 'finance']);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        TeamProfile::forceCreate(['team_id' => $team->id, 'description' => 'We do the books.', 'contact_email' => 'hello@example.test']);

        $html = $this->actingAs($owner->fresh())->get('/home')->assertOk()->getContent();

        $this->assertStringContainsString('data-company-progress', $html);
        $this->assertStringContainsString('Company page essentials', $html);
        $this->assertStringContainsString('3 of 4 in', $html, 'description, industry and contact are in; the logo is not');
        $this->assertStringContainsString('aria-valuenow="3"', $html);
    }

    public function test_the_stylesheet_styles_each_variant_and_the_unstyled_native_progress_is_gone_from_reports(): void
    {
        $css = (string) file_get_contents(resource_path('css/infodot.css'));

        foreach (['.ix-prog__track', '.ix-prog__fill', '.ix-ring__fill', '.ix-steps li.is-done', '.ix-steps--line', '@starting-style'] as $needle) {
            $this->assertStringContainsString($needle, $css, $needle);
        }
        $this->assertStringContainsString('transform: scaleX(var(--p, 0))', $css, 'the fill animates with a transform, not a width');
        $this->assertStringNotContainsString('<progress', (string) file_get_contents(resource_path('views/business/appointments/reports.blade.php')));
    }
}

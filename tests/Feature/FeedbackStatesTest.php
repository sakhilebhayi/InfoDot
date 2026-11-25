<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotice;
use App\Support\Toast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** R-64 phase 10: working, waiting, asking and telling. Success is shown only after the server has said yes; nothing irreversible goes ahead without a question. */
class FeedbackStatesTest extends TestCase
{
    use RefreshDatabase;

    private function person(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_a_confirmed_action_leaves_a_toast_that_appears_on_the_next_page_only(): void
    {
        $me = $this->person();
        $n = UserNotice::create(['user_id' => $me->id, 'kind' => UserNotice::ANSWER_RECEIVED, 'subject_type' => 'question', 'subject_id' => 1, 'ref' => 'f1', 'created_at' => now()]);

        $this->actingAs($me)->post("/notifications/{$n->id}/archive")->assertRedirect()->assertSessionHas('toast.text', 'Put away. It is in the archive.');

        $page = $this->actingAs($me)->withSession(['toast' => ['text' => 'Saved it', 'tone' => 'ok', 'label' => 'Done']])->get('/home')->assertOk()->getContent();
        $this->assertStringContainsString('Saved it', $page);
        $this->assertStringContainsString('flash:', $page);

        $this->actingAs($me)->get('/home')->assertOk()->assertDontSee('Saved it');
    }

    public function test_the_failed_action_leaves_no_toast(): void
    {
        $me = $this->person();
        $other = $this->person();
        $n = UserNotice::create(['user_id' => $other->id, 'kind' => UserNotice::ANSWER_RECEIVED, 'subject_type' => 'question', 'subject_id' => 1, 'ref' => 'f2', 'created_at' => now()]);

        $this->actingAs($me)->post("/notifications/{$n->id}/archive")->assertNotFound()->assertSessionMissing('toast');
    }

    public function test_toast_tones_are_limited_to_the_ones_the_stylesheet_knows(): void
    {
        Toast::flash('x', 'danger-red');
        $this->assertSame('ok', session('toast.tone'));
        Toast::flash('x', 'warn');
        $this->assertSame('warn', session('toast.tone'));
    }

    public function test_a_skeleton_is_labelled_for_screen_readers_and_hidden_from_them_otherwise(): void
    {
        view()->share('errors', new ViewErrorBag);
        $html = Blade::render('<x-ix.skeleton :lines="4" title block label="Loading the chart" />');

        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-busy="true"', $html);
        $this->assertStringContainsString('Loading the chart', $html);
        $this->assertSame(6, substr_count($html, 'class="ix-skel ix-skel') + substr_count($html, 'class="ix-skel"'), 'title, block and four lines');
        $this->assertSame(6, substr_count($html, 'aria-hidden="true"'));
    }

    public function test_no_view_still_uses_the_browsers_own_confirm_box_and_every_one_asks_with_the_same_dialog(): void
    {
        $left = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php') && preg_match('/\bconfirm\(\s*[\'"]/', (string) file_get_contents($file->getPathname()))) {
                $left[] = str_replace(resource_path('views').'/', '', $file->getPathname());
            }
        }
        $this->assertSame([], $left, 'use data-confirm on the form instead of the native confirm()');

        foreach (['business/booking/providers', 'business/booking/types', 'business/verification', 'seminars/_remove-form', 'seminars/show', 'business/appointments/show', 'business/appointments/list', 'my-bookings/index', 'my-bookings/show'] as $view) {
            $this->assertStringContainsString('data-confirm="', (string) file_get_contents(resource_path("views/$view.blade.php")), $view);
        }
    }

    public function test_cancelling_and_rejecting_an_appointment_ask_first_and_say_what_follows(): void
    {
        $show = (string) file_get_contents(resource_path('views/business/appointments/show.blade.php'));

        $this->assertStringContainsString('data-confirm="Cancel this appointment? The customer is told', $show);
        $this->assertStringContainsString('data-confirm="Reject this request?', $show);
        $this->assertStringContainsString('data-confirm-label="Cancel the appointment"', $show);
    }

    public function test_the_feedback_script_blocks_a_second_press_releases_the_button_and_asks_in_a_real_dialog(): void
    {
        $js = (string) file_get_contents(resource_path('js/feedback.js'));

        foreach (["'aria-busy', 'true'", 'form.dataset.sending', 'pageshow', 'BUSY_MS', 'showModal()', 'requestSubmit(', 'button[aria-busy="true"]', 'data-no-busy', 'event.defaultPrevented', 'data-cancel-label', 'dialog?.isConnected', "hasAttribute('x-data')"] as $needle) {
            $this->assertStringContainsString($needle, $js, $needle);
        }
        $this->assertStringNotContainsString('eval(', $js);
        $this->assertStringNotContainsString('innerHTML = form', $js);
        $this->assertStringContainsString("dialog.setAttribute('aria-labelledby'", $js);

        $app = (string) file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("import './feedback';", $app);
        $this->assertStringContainsString(".finally(() => trendChart.classList.remove('is-loading'))", $app, 'the placeholder can never be left pulsing');
    }

    public function test_the_stylesheet_has_the_spinner_skeleton_and_dialog_and_they_use_the_duration_tokens(): void
    {
        $css = (string) file_get_contents(resource_path('css/infodot.css'));

        foreach (['@keyframes ix-spin', '@keyframes ix-pulse', '.ix-btn.is-busy::before', '.ix-skel', '.ix-confirm::backdrop', '.ixa-chart.is-loading'] as $needle) {
            $this->assertStringContainsString($needle, $css, $needle);
        }
        $this->assertStringContainsString('animation: ix-spin var(--ix-dur-scene-slow) linear infinite', $css);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** R-64 phase 3: the form components render the markup the stylesheet and screen readers rely on, and the field CSS keeps every state. */
class FormComponentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The web middleware shares $errors on every real request; a bare render needs it shared by hand.
        view()->share('errors', new ViewErrorBag);
    }

    private function render(string $blade): string
    {
        return Blade::render($blade);
    }

    public function test_a_field_ties_label_control_hint_and_error_together(): void
    {
        $html = $this->render('<x-form.field name="email" label="Email" hint="We reply here."><x-form.input name="email" type="email" /></x-form.field>');

        $this->assertStringContainsString('<label class="ix-label" for="email">Email</label>', $html);
        $this->assertStringContainsString('id="email"', $html);
        $this->assertStringContainsString('aria-describedby="email-msg"', $html);
        $this->assertStringContainsString('id="email-msg"', $html);
        $this->assertStringContainsString('We reply here.', $html);
        $this->assertStringNotContainsString('aria-invalid', $html);
        $this->assertStringContainsString('ix-field__foot', $html, 'the line for messages is always there, so nothing shifts');
    }

    public function test_an_error_replaces_the_hint_marks_the_control_invalid_and_is_announced(): void
    {
        $this->withViewErrors(['email' => 'Enter a valid email address.']);

        $html = $this->render('<x-form.field name="email" label="Email" hint="We reply here."><x-form.input name="email" type="email" /></x-form.field>');

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('<p class="ix-error" role="alert">Enter a valid email address.</p>', $html);
        $this->assertStringNotContainsString('We reply here.', $html);
        $this->assertStringContainsString('has-error', $html);
    }

    public function test_old_input_is_restored_but_never_for_passwords(): void
    {
        request()->setLaravelSession(app('session.store'));
        session()->put('_old_input', ['name' => 'Zodwa', 'secret' => 'hunter2']);

        $html = $this->render('<x-form.input name="name" /><x-form.input name="secret" type="password" />');

        $this->assertStringContainsString('value="Zodwa"', $html);
        $this->assertStringNotContainsString('hunter2', $html);
    }

    public function test_optional_fields_are_marked_in_words(): void
    {
        $html = $this->render('<x-form.field name="note" label="Note" :optional="true"><x-form.textarea name="note" /></x-form.field>');

        $this->assertStringContainsString('<span class="ix-label__flag">Optional</span>', $html);
        $this->assertStringContainsString('class="ix-textarea"', $html);
    }

    public function test_select_marks_the_chosen_option_and_takes_a_placeholder(): void
    {
        $html = $this->render('<x-form.select name="zone" value="b" placeholder="Choose" :options="[\'a\' => \'Alpha\', \'b\' => \'Beta\']" />');

        $this->assertStringContainsString('class="ix-select"', $html);
        $this->assertStringContainsString('<option value="">Choose</option>', $html);
        $this->assertMatchesRegularExpression('/<option value="b"\s+selected/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="a"\s+selected/', $html);
    }

    public function test_a_switch_is_a_real_checkbox_with_role_switch_and_reports_when_off(): void
    {
        $html = $this->render('<x-form.switch name="enabled" label="Taking appointments" :checked="true" hint="Turn off to stop." />');

        $this->assertStringContainsString('role="switch"', $html);
        $this->assertStringContainsString('class="ix-switch"', $html);
        $this->assertStringContainsString('<input type="hidden" name="enabled" value="0">', $html);
        $this->assertMatchesRegularExpression('/value="1"[^>]*checked/', $html);
        $this->assertStringContainsString('<label class="ix-check" for="enabled">', $html, 'the whole row is the target');
    }

    public function test_radio_groups_are_fieldsets_with_a_legend_and_the_chosen_value(): void
    {
        $html = $this->render('<x-form.radio-group name="mode" legend="Mode" value="auto" :options="[\'approval\' => [\'Confirm each\', \'Wait for you.\'], \'auto\' => \'Automatic\']" />');

        $this->assertStringContainsString('<fieldset', $html);
        $this->assertStringContainsString('<legend class="ix-label">Mode</legend>', $html);
        $this->assertMatchesRegularExpression('/value="auto"\s+checked/', $html);
        $this->assertStringContainsString('Wait for you.', $html);
    }

    public function test_the_jetstream_wrappers_no_longer_carry_tailwind_or_inline_styles(): void
    {
        $checkbox = $this->render('<x-jet-checkbox name="remember" id="r" />');
        $input = $this->render('<x-jet-input name="q" />');

        $this->assertStringNotContainsString('style=', $checkbox);
        $this->assertStringNotContainsString('h-4', $checkbox);
        $this->assertStringContainsString('class="ix-input"', $input);
        $this->assertStringNotContainsString('disabled:', $input);
    }

    public function test_the_stylesheet_covers_every_field_state(): void
    {
        $css = (string) file_get_contents(resource_path('css/infodot.css'));

        foreach ([':hover', ':focus-visible', "[aria-invalid='true']", '.is-valid', '[readonly]', ':disabled', "[aria-busy='true']", 'select.ix-input', '.ix-switch:checked', '.ix-field__foot'] as $needle) {
            $this->assertStringContainsString($needle, $css, $needle);
        }
        $this->assertStringContainsString('outline: 2px solid var(--ix-ring)', $css, 'the focus ring is the header search ring');
        $this->assertStringContainsString('--ix-ring: #f0bc2e', $css);
        $this->assertSame(1, preg_match_all('/^\.ix-error \{/m', $css), 'one definition of the error message, not two');
    }

    public function test_the_ring_is_never_taken_off_a_search_field_that_has_no_box_of_its_own(): void
    {
        $css = (string) file_get_contents(resource_path('css/infodot.css'));

        $this->assertStringContainsString(".ix input[type='search']:not(.dsr-input):not(.lp-search__input):focus-visible {\n    outline: 2px solid var(--ix-ring);", $css);
    }
}

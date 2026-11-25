<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** R-64 phase 4: the searchable select, date picker, time list, file drop area and password field render the real controls underneath, so they work without scripts. */
class ComplexControlsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        view()->share('errors', new ViewErrorBag);
    }

    private function render(string $blade): string
    {
        return Blade::render($blade);
    }

    public function test_the_combobox_keeps_a_real_select_that_carries_the_value(): void
    {
        $html = $this->render('<x-form.combobox name="city" value="b" :options="[\'a\' => \'Johannesburg\', \'b\' => \'Cape Town\']" />');

        $this->assertStringContainsString('<select class="ix-select" x-ref="native" name="city" id="city"', $html);
        $this->assertMatchesRegularExpression('/<option value="b"\s+selected/', $html);
        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('role="listbox"', $html);
        $this->assertStringContainsString('aria-describedby="city-msg"', $html);
        $this->assertStringContainsString('ix-combo', $html);
    }

    public function test_a_multiple_combobox_submits_an_array_and_restores_every_chosen_value(): void
    {
        $html = $this->render('<x-form.combobox name="tags" multiple :value="[\'a\', \'c\']" :options="[\'a\' => \'One\', \'b\' => \'Two\', \'c\' => \'Three\']" />');

        $this->assertStringContainsString('name="tags[]"', $html);
        $this->assertStringContainsString(' multiple', $html);
        $this->assertMatchesRegularExpression('/<option value="a"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/<option value="c"\s+selected/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="b"\s+selected/', $html);
        $this->assertStringContainsString('aria-multiselectable', $html);
    }

    public function test_the_combobox_marks_itself_invalid_when_the_field_has_an_error(): void
    {
        $this->withViewErrors(['city' => 'Choose a city.']);

        $html = $this->render('<x-form.combobox name="city" :options="[\'a\' => \'One\']" />');

        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('data-invalid', $html);
    }

    public function test_the_date_field_is_a_native_date_input_with_its_limits(): void
    {
        $html = $this->render('<x-form.date name="when" value="2026-11-25" min="2026-11-10" max="2026-12-20" :disabled-days="[\'2026-11-26\']" />');

        $this->assertStringContainsString('type="date"', $html);
        $this->assertStringContainsString('name="when"', $html);
        $this->assertStringContainsString('value="2026-11-25"', $html);
        $this->assertStringContainsString('min="2026-11-10"', $html);
        $this->assertStringContainsString('max="2026-12-20"', $html);
        $this->assertStringContainsString('2026-11-26', $html, 'the unavailable day reaches the calendar');
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-label="Choose a date from the calendar"', $html);
    }

    public function test_the_time_field_lists_every_step_between_its_limits(): void
    {
        $html = $this->render('<x-form.time name="at" value="09:30" :step="30" from="08:00" to="10:00" />');

        $this->assertSame(6, substr_count($html, '<option value="'), 'an empty choice, then 08:00, 08:30, 09:00, 09:30, 10:00');
        $this->assertMatchesRegularExpression('/<option value="09:30"\s+selected/', $html);
        $this->assertStringContainsString('name="at"', $html);
    }

    public function test_a_stored_time_that_is_not_on_the_list_is_kept_not_replaced(): void
    {
        $odd = $this->render('<x-form.time name="at" value="09:10" :step="15" />');
        $this->assertMatchesRegularExpression('/<option value="09:10"\s+selected/', $odd, 'saving must not move 09:10 to the first time on the list');

        $seconds = $this->render('<x-form.time name="at" value="09:30:00" :step="15" />');
        $this->assertMatchesRegularExpression('/<option value="09:30"\s+selected/', $seconds, 'a database time is cut to HH:MM and found on the list');
        $this->assertStringNotContainsString('value="09:30:00"', $seconds);

        $none = $this->render('<x-form.time name="at" :step="15" />');
        $this->assertStringContainsString('<option value="">', $none, 'an optional time can be left empty');
        $this->assertDoesNotMatchRegularExpression('/<option value="00:00"\s+selected/', $none);

        $required = $this->render('<x-form.time name="at" :step="15" required />');
        $this->assertStringNotContainsString('<option value="">', $required);
    }

    public function test_extra_attributes_reach_the_real_control_not_the_wrapper(): void
    {
        $combo = $this->render('<x-form.combobox name="c" :options="[\'a\' => \'A\']" wire:model="choice" data-x="1" />');
        $this->assertMatchesRegularExpression('/<select[^>]*wire:model="choice"/', $combo);
        $this->assertDoesNotMatchRegularExpression('/<div class="ix-combo"[^>]*wire:model/', $combo);
        $this->assertStringContainsString('wire:ignore', $combo, 'Livewire leaves the drawn control alone');

        $file = $this->render('<x-form.file name="f" wire:model="upload" required />');
        $this->assertMatchesRegularExpression('/<input[^>]*wire:model="upload"[^>]*type="file"|<input[^>]*type="file"[^>]*wire:model="upload"/', $file);
        $this->assertStringContainsString('wire:ignore', $file);
    }

    public function test_a_stored_choice_missing_from_the_options_is_added_and_errors_on_items_mark_the_field(): void
    {
        $this->withViewErrors(['tags.1' => 'Not allowed.']);

        $html = $this->render('<x-form.combobox name="tags" multiple :value="[\'a\', \'gone\']" :options="[\'a\' => \'One\']" />');

        $this->assertMatchesRegularExpression('/<option value="gone"\s+selected/', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertSame(2, substr_count($html, 'aria-invalid="true"'), 'the select and the visible search box both say so');
    }

    public function test_a_required_single_select_has_no_empty_choice_and_an_optional_one_does(): void
    {
        $this->assertStringNotContainsString('<option value="">', $this->render('<x-form.combobox name="c" required :options="[\'a\' => \'A\']" />'));
        $this->assertStringContainsString('<option value="">', $this->render('<x-form.combobox name="c" :options="[\'a\' => \'A\']" />'));
        $this->assertStringNotContainsString('<option value="">', $this->render('<x-form.combobox name="c" multiple :options="[\'a\' => \'A\']" />'));
    }

    public function test_the_calendar_keeps_unavailable_days_reachable_by_keyboard(): void
    {
        $html = $this->render('<x-form.date name="when" />');

        $this->assertStringContainsString('aria-disabled', $html, 'unavailable days are marked, not removed from the tab and arrow path');
        $this->assertStringNotContainsString('x-bind:disabled="d.disabled"', $html);
        $this->assertStringNotContainsString('role="grid"', $html);
        $this->assertStringContainsString('x-on:blur="check()"', $html);
    }

    public function test_the_file_field_keeps_the_native_input_with_its_limits_and_lists_dropped_files(): void
    {
        $html = $this->render('<x-form.file name="doc" accept="image/*,.pdf" :max-mb="2.5" multiple :max="3" />');

        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('name="doc[]"', $html);
        $this->assertStringContainsString('accept="image/*,.pdf"', $html);
        $this->assertStringContainsString('maxBytes: 2621440', $html);
        $this->assertStringContainsString('up to 2.5 MB', $html);
        $this->assertStringContainsString('role="alert"', $html, 'a rejected file is announced');
    }

    public function test_the_password_field_says_what_its_toggle_does_and_never_refills(): void
    {
        request()->setLaravelSession(app('session.store'));
        session()->put('_old_input', ['password' => 'hunter2']);

        $html = $this->render('<x-form.password name="password" strength />');

        $this->assertStringContainsString('aria-pressed="false"', $html);
        $this->assertStringContainsString('Caps Lock is on.', $html);
        $this->assertStringContainsString('ix-strength', $html, 'the advice sits under the field when asked for');
        $this->assertStringNotContainsString('hunter2', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);
    }

    public function test_no_control_uses_an_inline_event_handler(): void
    {
        foreach (['combobox', 'date', 'time', 'file', 'password'] as $name) {
            $source = (string) file_get_contents(resource_path("views/components/form/$name.blade.php"));

            $this->assertDoesNotMatchRegularExpression('/\son(click|change|input|submit|keydown)=/', $source, "$name uses Alpine, not inline handlers (the page's CSP forbids them)");
        }
    }

    public function test_the_stylesheet_styles_every_part_of_the_new_controls(): void
    {
        $css = (string) file_get_contents(resource_path('css/infodot.css'));

        foreach (['.ix-combo__list', '.ix-combo__chip', '.ix-combo__box', '.ix-cal__day', '.ix-cal__day.is-selected', ".ix-cal__day[aria-disabled='true']", '.ix-drop.is-over', '.ix-drop__list', '.ix-pop-in--from'] as $selector) {
            $this->assertStringContainsString($selector, $css, $selector);
        }
        $this->assertStringContainsString('z-index: 70', $css, 'panels sit above menus (60) and dialogs');
    }

    public function test_the_pure_helpers_pass_their_own_tests_under_node(): void
    {
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            $this->markTestSkipped('Node is not installed here.');
        }

        $output = [];
        exec(escapeshellarg($node).' --test '.escapeshellarg(base_path('tests/Js/controls-lib.test.mjs')).' 2>&1', $output, $status);

        $this->assertSame(0, $status, implode("\n", $output));
    }
}

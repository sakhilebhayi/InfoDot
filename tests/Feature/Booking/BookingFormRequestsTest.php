<?php

namespace Tests\Feature\Booking;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-72 (Q-20): the booking setup forms moved to form requests keep the screens' order of answers: feature closed 404, another
 * business's record 404, not a manager 403, and only then the form's own errors.
 */
class BookingFormRequestsTest extends TestCase
{
    use BuildsBookingBusinesses;
    use RefreshDatabase;

    public function test_the_order_of_answers_holds_for_settings_and_appointment_types(): void
    {
        [$team, $owner] = $this->business('available');
        [$other] = $this->business('available', 'Other Freight');
        $member = $this->member($team, 'member');
        $foreign = $this->type($other);
        $base = '/business/'.$team->uuid.'/booking';

        // Not a manager: 403 before the form is looked at.
        $this->actingAs($member)->post($base, [])->assertForbidden();
        $this->actingAs($member)->post($base.'/types', [])->assertForbidden();

        // A manager with an empty form: the form's errors.
        $this->actingAs($owner)->post($base, [])->assertSessionHasErrors(['approval_mode', 'timezone']);
        $this->actingAs($owner)->post($base.'/types', [])->assertSessionHasErrors(['name', 'duration_minutes']);

        // Another business's type through this business's URL: 404, whoever asks.
        $this->actingAs($owner)->post($base.'/types/'.$foreign->id, $this->typeForm())->assertNotFound();

        // The feature closed: 404 before anything else, even for the owner with a valid form.
        $this->feature('hidden');
        $this->actingAs($owner)->post($base.'/types', $this->typeForm())->assertNotFound();
        $this->actingAs($member)->post($base, [])->assertNotFound();
    }
}

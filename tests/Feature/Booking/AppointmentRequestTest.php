<?php

namespace Tests\Feature\Booking;

use App\Mail\EnquiryActivity;
use App\Models\BookingSetting;
use App\Models\Enquiry;
use App\Models\EnquiryBlock;
use App\Models\EnquiryMessage;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use App\Models\UserNotice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * R-66 phase 3: a business with no published calendar can still be asked for an appointment. The
 * request is an enquiry in the business inbox; the business accepts it with a time or declines it
 * with a reason, and the requester is told through the conversation.
 */
class AppointmentRequestTest extends TestCase
{
    use BuildsBookingBusinesses;
    use RefreshDatabase;

    private Team $team;

    private User $owner;

    private User $customer;

    private const ABOUT = 'We would like to talk about moving two containers a month from Durban to Nelspruit.';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:00:00', 'Africa/Johannesburg'));
        [$this->team, $this->owner] = $this->business('available');
        $this->team->profileOrNew()->forceFill(['visibility' => TeamProfile::PUBLIC, 'description' => 'We move freight.'])->save();
        $this->customer = User::factory()->create(['email_verified_at' => now(), 'name' => 'Zodwa Mokoena']);
    }

    /** @return array<string, mixed> */
    private function form(array $over = []): array
    {
        return $over + ['on' => '2026-11-25', 'part_of_day' => 'morning', 'minutes' => 60, 'about' => self::ABOUT];
    }

    private function request(array $over = []): Enquiry
    {
        $this->actingAs($this->customer)->post(route('business.appointment-request.store', $this->team), $this->form($over))->assertRedirect();

        return Enquiry::where('user_id', $this->customer->id)->latest('id')->firstOrFail();
    }

    private function publishCalendar(): void
    {
        BookingSetting::forceCreate(['team_id' => $this->team->id, 'enabled' => true, 'calendar_published' => true, 'approval_mode' => 'auto', 'timezone' => 'Africa/Johannesburg', 'min_notice_minutes' => 0, 'horizon_days' => 365]);
        $this->type($this->team, ['name' => 'Site visit']);
    }

    public function test_a_business_without_a_calendar_offers_a_request_to_everyone_outside_it(): void
    {
        $html = $this->actingAs($this->customer)->get(route('business.show', $this->team))->assertOk()->getContent();

        $this->assertStringContainsString('Request an appointment', $html);
        $this->assertStringContainsString(route('business.appointment-request', $this->team), $html);
        $this->assertStringNotContainsString('Book an appointment', $html);
    }

    public function test_your_own_business_never_offers_you_a_request(): void
    {
        $html = $this->actingAs($this->owner)->get(route('business.show', $this->team))->assertOk()->getContent();

        $this->assertStringNotContainsString('Request an appointment', $html);
        $this->assertStringContainsString('Manage bookings', $html);
    }

    public function test_a_business_with_a_published_calendar_offers_booking_instead(): void
    {
        $this->publishCalendar();

        $html = $this->actingAs($this->customer)->get(route('business.show', $this->team))->assertOk()->getContent();

        $this->assertStringContainsString('Book an appointment', $html);
        $this->assertStringNotContainsString('Request an appointment', $html);
        $this->actingAs($this->customer)->get(route('business.appointment-request', $this->team))->assertOk()->assertSee('has a booking calendar');
    }

    public function test_no_request_is_offered_while_the_feature_is_closed_or_the_business_takes_no_messages(): void
    {
        $this->feature('development');
        $this->actingAs($this->customer)->get(route('business.show', $this->team))->assertOk()->assertDontSee('Request an appointment');
        $this->post(route('business.appointment-request.store', $this->team), $this->form())->assertSessionHasErrors('about');

        $this->feature('available');
        $this->team->profile->forceFill(['accepts_enquiries' => false])->save();
        $this->get(route('business.show', $this->team))->assertOk()->assertDontSee('Request an appointment');
        $this->assertSame(0, Enquiry::count());
    }

    public function test_a_request_lands_in_the_business_inbox_with_what_was_asked_for(): void
    {
        $enquiry = $this->request();

        $this->assertSame(Enquiry::APPOINTMENT, $enquiry->category);
        $this->assertSame(Enquiry::APPOINTMENT_REQUESTED, $enquiry->appointment_state);
        $this->assertSame('2026-11-25', $enquiry->requested_on->toDateString());
        $this->assertSame('morning', $enquiry->requested_time);
        $this->assertSame(60, $enquiry->requested_minutes);
        $this->assertSame($this->team->id, $enquiry->team_id);
        $this->assertSame(self::ABOUT, $enquiry->messages()->first()->body);
        $this->assertDatabaseHas('user_notices', ['user_id' => $this->owner->id, 'kind' => UserNotice::ENQUIRY_RECEIVED]);

        $this->actingAs($this->owner)->get(route('business.inbox', $this->team))->assertOk()->assertSee('an appointment request');
        $this->actingAs($this->owner)->get(route('business.inbox.show', [$this->team, $enquiry]))->assertOk()
            ->assertSee('Wednesday 25 November 2026, morning, about 1 hour')->assertSee('Answer the request');
    }

    public function test_a_set_time_is_kept_and_required_when_chosen(): void
    {
        $this->actingAs($this->customer)->post(route('business.appointment-request.store', $this->team), $this->form(['part_of_day' => 'set']))
            ->assertSessionHasErrors('set_time');

        $enquiry = $this->request(['part_of_day' => 'set', 'set_time' => '14:30']);
        $this->assertSame('14:30', $enquiry->requested_time);
    }

    public function test_bad_dates_lengths_and_short_reasons_are_refused(): void
    {
        $this->actingAs($this->customer);
        $url = route('business.appointment-request.store', $this->team);

        $this->post($url, $this->form(['on' => '2026-11-19']))->assertSessionHasErrors('on');
        $this->post($url, $this->form(['on' => '2028-01-01']))->assertSessionHasErrors('on');
        $this->post($url, $this->form(['minutes' => 7]))->assertSessionHasErrors('minutes');
        $this->post($url, $this->form(['about' => 'Hi']))->assertSessionHasErrors('about');
        $this->assertSame(0, Enquiry::count());
    }

    public function test_members_and_blocked_people_cannot_request(): void
    {
        $this->actingAs($this->owner)->post(route('business.appointment-request.store', $this->team), $this->form())->assertSessionHasErrors('about');

        EnquiryBlock::forceCreate(['team_id' => $this->team->id, 'user_id' => $this->customer->id, 'blocked_by' => $this->owner->id]);
        $this->actingAs($this->customer)->get(route('business.appointment-request', $this->team))->assertOk()->assertSee('not accepting new enquiries');
        $this->post(route('business.appointment-request.store', $this->team), $this->form())->assertSessionHasErrors('about');
        $this->assertSame(0, Enquiry::count());
    }

    public function test_a_request_can_be_made_for_the_persons_own_company(): void
    {
        $company = Team::factory()->create(['user_id' => $this->customer->id, 'personal_team' => false, 'name' => 'Mokoena Logistics']);
        $this->customer->forceFill(['current_team_id' => $company->id])->save();

        $this->actingAs($this->customer->fresh())->get(route('business.appointment-request', $this->team))->assertOk()->assertSee('Booking for');
        $enquiry = $this->request(['for_company' => (string) $company->id]);

        $this->assertSame($company->id, $enquiry->customer_team_id);
    }

    public function test_the_business_accepts_with_a_time_and_the_requester_is_told(): void
    {
        $enquiry = $this->request();

        $this->actingAs($this->owner)->post(route('business.inbox.appointment', [$this->team, $enquiry]), [
            'decision' => 'accept', 'on' => '2026-11-25', 'at' => '10:00', 'minutes' => 90, 'note' => 'Meet at the Mbombela depot gate.',
        ])->assertRedirect();

        $enquiry->refresh();
        $this->assertSame(Enquiry::APPOINTMENT_ACCEPTED, $enquiry->appointment_state);
        // Entered in the business's time zone (Johannesburg), stored in UTC.
        $this->assertSame('2026-11-25 08:00', $enquiry->appointment_at->utc()->format('Y-m-d H:i'));
        $this->assertSame(90, $enquiry->appointment_minutes);
        $this->assertSame(Enquiry::RESOLVED, $enquiry->status);

        $answer = $enquiry->messages()->where('kind', EnquiryMessage::BUSINESS)->firstOrFail();
        $this->assertStringContainsString('accepted your appointment request: Wednesday 25 November 2026 at 10:00, 1 hour 30 minutes', $answer->body);
        $this->assertStringContainsString('Mbombela depot gate', $answer->body);
        $this->assertDatabaseHas('user_notices', ['user_id' => $this->customer->id, 'kind' => UserNotice::ENQUIRY_REPLY]);
        Mail::assertSent(EnquiryActivity::class, fn ($m) => $m->hasTo($this->customer->email));

        $this->actingAs($this->customer)->get(route('inbox.show', $enquiry))->assertOk()
            ->assertSee('Accepted')->assertSee('Wednesday 25 November 2026 at 10:00');

        // Answered once: a second answer is refused.
        $this->actingAs($this->owner)->post(route('business.inbox.appointment', [$this->team, $enquiry]), ['decision' => 'decline', 'reason' => 'Changed our minds.'])
            ->assertSessionHasErrors('decision');
    }

    public function test_the_business_declines_with_a_reason_the_requester_reads(): void
    {
        $enquiry = $this->request();
        $url = route('business.inbox.appointment', [$this->team, $enquiry]);

        $this->actingAs($this->owner)->post($url, ['decision' => 'decline'])->assertSessionHasErrors('reason');
        $this->post($url, ['decision' => 'decline', 'reason' => 'We are fully booked until January.'])->assertRedirect();

        $enquiry->refresh();
        $this->assertSame(Enquiry::APPOINTMENT_DECLINED, $enquiry->appointment_state);
        $this->assertNull($enquiry->appointment_at);
        $this->assertStringContainsString('fully booked until January', $enquiry->messages()->where('kind', EnquiryMessage::BUSINESS)->value('body'));
        $this->assertDatabaseHas('user_notices', ['user_id' => $this->customer->id, 'kind' => UserNotice::ENQUIRY_REPLY]);

        $this->actingAs($this->customer)->get(route('inbox.show', $enquiry))->assertOk()->assertSee('Declined');
    }

    public function test_an_accepted_time_must_not_have_passed(): void
    {
        $enquiry = $this->request();

        $this->actingAs($this->owner)->post(route('business.inbox.appointment', [$this->team, $enquiry]), ['decision' => 'accept', 'on' => '2026-11-20', 'at' => '07:00', 'minutes' => 60])
            ->assertSessionHasErrors('at');
        $this->assertSame(Enquiry::APPOINTMENT_REQUESTED, $enquiry->fresh()->appointment_state);
    }

    public function test_only_the_businesss_handlers_can_answer_and_only_appointment_requests(): void
    {
        $enquiry = $this->request();
        $url = route('business.inbox.appointment', [$this->team, $enquiry]);
        $accept = ['decision' => 'accept', 'on' => '2026-11-25', 'at' => '10:00', 'minutes' => 60];

        $this->actingAs($this->customer)->post($url, $accept)->assertNotFound();
        $editor = $this->member($this->team, 'editor');
        $this->actingAs($editor)->post($url, $accept)->assertNotFound();

        [$other, $otherOwner] = $this->business('available', 'Other Freight');
        $this->actingAs($otherOwner)->post(route('business.inbox.appointment', [$other, $enquiry]), $accept)->assertNotFound();

        $message = Enquiry::forceCreate(['uuid' => (string) Str::uuid(), 'team_id' => $this->team->id, 'user_id' => $this->customer->id, 'subject' => 'A question', 'category' => 'general', 'status' => Enquiry::NEW]);
        $this->actingAs($this->owner)->post(route('business.inbox.appointment', [$this->team, $message]), $accept)->assertNotFound();

        $this->assertSame(Enquiry::APPOINTMENT_REQUESTED, $enquiry->fresh()->appointment_state);
    }

    public function test_the_general_message_form_does_not_offer_the_appointment_category(): void
    {
        $this->actingAs($this->customer)->get(route('business.message', $this->team))->assertOk()->assertDontSee('value="appointment"', false);
        $this->post(route('business.message.store', $this->team), ['subject' => 'Hello there', 'category' => 'appointment', 'body' => self::ABOUT])
            ->assertSessionHasErrors('category');
    }
}

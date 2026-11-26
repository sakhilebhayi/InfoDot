<?php

namespace Tests\Feature\Booking;

use App\Console\Commands\SendBookingReminders;
use App\Mail\BookingActivity;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingSetting;
use App\Models\NotificationPreference;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Models\WorkingHour;
use App\Services\Booking\BookingActions;
use App\Services\Booking\BookingIcs;
use App\Services\Booking\BookingNotifier;
use App\Services\Booking\BookingRefused;
use App\Services\Booking\Bookings;
use App\Services\Knowledge\InAppNotices;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * R-63 phase 5: who is told what, once, and never more than the fact. "Now" is 20 November 2026, 10:00 Johannesburg; Mondays are open 09:00 to 17:00
 * (07:00Z to 15:00Z) and the business must be told about a booking made in advance.
 */
class BookingNotificationsTest extends TestCase
{
    use BuildsBookingBusinesses;
    use RefreshDatabase;

    private Team $team;

    private User $owner;

    private User $admin;

    private User $manager;

    private User $plain;

    private AppointmentType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:00:00', 'UTC'));
        Mail::fake();
        [$this->team, $this->owner] = $this->business('available');
        BookingSetting::forceCreate(['team_id' => $this->team->id, 'enabled' => true, 'calendar_published' => true, 'approval_mode' => 'approval', 'timezone' => 'Africa/Johannesburg', 'min_notice_minutes' => 0, 'horizon_days' => 365, 'cancel_cutoff_hours' => 24]);
        WorkingHour::forceCreate(['team_id' => $this->team->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        $this->type = $this->type($this->team, ['name' => 'Initial consultation', 'meeting_format' => 'online', 'location_note' => 'https://meet.example/secret passcode 4821']);
        $this->admin = $this->member($this->team, 'admin', 'Anele Admin');
        $this->manager = $this->member($this->team, 'manager', 'Mandla Manager');
        $this->plain = $this->member($this->team, 'member', 'Plain Person');
    }

    private function at(string $z): CarbonImmutable
    {
        return CarbonImmutable::parse($z, 'UTC');
    }

    private function customer(?string $email = null): User
    {
        return User::factory()->create(['email_verified_at' => now(), 'name' => 'Zodwa Secret', 'email' => $email ?? 'zodwa.account@example.test']);
    }

    private function book(?User $who = null, string $start = '2026-11-30 08:00:00', string $mode = 'approval'): Booking
    {
        BookingSetting::where('team_id', $this->team->id)->update(['approval_mode' => $mode]);

        return app(Bookings::class)->book($who ?? $this->customer(), $this->team, $this->type, $this->at($start), null, [
            'name' => 'Zodwa Typed', 'email' => 'typed-address@victim.test', 'phone' => '082 000 0000', 'company' => 'Secret Traders', 'reason' => 'A private matter',
        ]);
    }

    private function notices(User $user, ?string $kind = null): int
    {
        return UserNotice::where('user_id', $user->id)->when($kind, fn ($q) => $q->where('kind', $kind))->count();
    }

    private function mails(User $user, ?string $kind = null): int
    {
        return Mail::queued(BookingActivity::class, fn (BookingActivity $m) => $m->hasTo($user->email) && ($kind === null || $m->kind === $kind))->count();
    }

    // ------------------------------------------------------------------------------------------------------------------- a new booking

    public function test_a_new_request_tells_the_customer_it_was_sent_and_the_people_who_run_the_business_it_arrived(): void
    {
        $customer = $this->customer();
        $this->book($customer);

        $this->assertSame([1, 1], [$this->notices($customer, UserNotice::BOOKING_SUBMITTED), $this->mails($customer, UserNotice::BOOKING_SUBMITTED)]);
        foreach ([$this->owner, $this->admin, $this->manager] as $person) {
            $this->assertSame([1, 1], [$this->notices($person, UserNotice::BOOKING_RECEIVED), $this->mails($person, UserNotice::BOOKING_RECEIVED)], $person->name);
        }
        $this->assertSame([0, 0], [$this->notices($this->plain), $this->mails($this->plain)], 'a plain member is not told');
    }

    public function test_an_automatic_confirmation_tells_the_customer_it_is_confirmed_with_a_calendar_file(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer, '2026-11-30 08:00:00', 'auto');

        $this->assertSame(1, $this->notices($customer, UserNotice::BOOKING_CONFIRMED));
        Mail::assertQueued(BookingActivity::class, function (BookingActivity $m) use ($customer, $booking) {
            if (! $m->hasTo($customer->email) || $m->kind !== UserNotice::BOOKING_CONFIRMED) {
                return false;
            }
            $attachments = $m->attachments();

            return count($attachments) === 1 && $booking->reference !== null;
        });
    }

    public function test_mail_goes_to_the_account_email_never_to_the_address_typed_into_the_form_and_carries_no_private_details(): void
    {
        $customer = $this->customer('real.account@example.test');
        $this->book($customer);

        Mail::assertNotQueued(BookingActivity::class, fn (BookingActivity $m) => $m->hasTo('typed-address@victim.test'));
        $this->assertSame(1, $this->mails($customer));

        foreach (Mail::queued(BookingActivity::class) as $mail) {
            $html = $mail->render();
            foreach (['typed-address@victim.test', '082 000 0000', 'Secret Traders', 'A private matter', 'meet.example', 'passcode', 'Zodwa Typed'] as $private) {
                $this->assertStringNotContainsString($private, $html, 'an email never carries '.$private);
            }
            $this->assertStringContainsString('Dlamini Freight', $html);
            $this->assertStringContainsString('Initial consultation', $html);
            $this->assertStringContainsString('Africa/Johannesburg', $html);
        }
    }

    public function test_a_person_with_no_verified_email_gets_the_bell_and_no_mail(): void
    {
        $customer = User::factory()->create(['email_verified_at' => null, 'name' => 'Unverified']);
        $this->book($customer);

        $this->assertSame([1, 0], [$this->notices($customer), $this->mails($customer)]);
    }

    public function test_the_persons_email_choice_for_bookings_decides_whether_mail_goes_as_it_happens_and_the_bell_always_has_it(): void
    {
        foreach ([['instant', 1], ['digest', 0], ['off', 0]] as $i => [$mode, $mails]) {
            $customer = $this->customer($mode.'.customer@example.test');
            NotificationPreference::create(['user_id' => $customer->id, 'category' => 'bookings', 'email' => $mode]);

            $this->book($customer, '2026-11-30 0'.(7 + $i).':00:00');

            $this->assertSame([1, $mails], [$this->notices($customer), $this->mails($customer)], $mode);
        }
    }

    public function test_a_mail_outage_never_fails_or_undoes_the_booking(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        $customer = $this->customer();

        $booking = $this->book($customer);

        $this->assertSame(Booking::PENDING, $booking->fresh()->status);
        $this->assertSame(1, $this->notices($customer, UserNotice::BOOKING_SUBMITTED), 'the bell still has it');
    }

    // ------------------------------------------------------------------------------------------------------------------- who on the business side

    public function test_a_booking_assigned_to_a_provider_tells_that_provider_only(): void
    {
        $thandi = $this->member($this->team, 'member', 'Thandi');
        $provider = $this->provider($this->team, $thandi, 'Thandi');
        $this->type->providers()->attach($provider->id);

        $this->book();

        $this->assertSame([1, 0, 0, 0], [$this->notices($thandi), $this->notices($this->owner), $this->notices($this->admin), $this->notices($this->manager)]);
    }

    public function test_a_provider_who_left_or_is_switched_off_falls_back_to_the_managers(): void
    {
        $thandi = $this->member($this->team, 'member', 'Thandi');
        $provider = $this->provider($this->team, $thandi, 'Thandi');
        $this->type->providers()->attach($provider->id);
        $customer = $this->customer();
        $booking = $this->book($customer, '2026-11-30 08:00:00', 'auto');
        $this->assertSame([1, 0], [$this->notices($thandi, UserNotice::BOOKING_RECEIVED), $this->notices($this->owner, UserNotice::BOOKING_RECEIVED)]);

        $provider->update(['active' => false]); // switched off after the booking was made
        app(Bookings::class)->cancelByCustomer($booking, $customer);

        $this->assertSame([0, 1, 1, 1], [$this->notices($thandi, UserNotice::BOOKING_CANCELLED), $this->notices($this->owner, UserNotice::BOOKING_CANCELLED), $this->notices($this->admin, UserNotice::BOOKING_CANCELLED), $this->notices($this->manager, UserNotice::BOOKING_CANCELLED)]);
    }

    public function test_another_businesss_people_are_never_told(): void
    {
        [, $otherOwner] = $this->business('available', 'Kestrel Mining');

        $this->book();

        $this->assertSame([0, 0], [$this->notices($otherOwner), $this->mails($otherOwner)]);
    }

    // ------------------------------------------------------------------------------------------------------------------- what the business does

    public function test_each_business_decision_tells_the_customer_and_never_the_person_who_made_it(): void
    {
        $customer = $this->customer();
        $accepted = $this->book($customer, '2026-11-30 07:00:00');
        $rejected = $this->book($customer, '2026-11-30 09:00:00');
        $cancelled = $this->book($customer, '2026-11-30 11:00:00');
        $actions = app(BookingActions::class);

        $actions->accept($accepted, $this->owner);
        $actions->reject($rejected, $this->owner, 'Closed that week.');
        $actions->cancel($cancelled, $this->owner, 'Sorry.');

        foreach ([UserNotice::BOOKING_CONFIRMED, UserNotice::BOOKING_REJECTED, UserNotice::BOOKING_CANCELLED] as $kind) {
            $this->assertSame([1, 1], [$this->notices($customer, $kind), $this->mails($customer, $kind)], $kind);
        }
        $this->assertSame(0, $this->notices($this->owner, UserNotice::BOOKING_CONFIRMED), 'nobody is told of their own act');
        $this->assertSame(0, $this->notices($this->owner, UserNotice::BOOKING_REJECTED));
    }

    public function test_a_move_tells_the_customer_with_a_new_calendar_file_and_each_move_is_its_own_notice(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer, '2026-11-30 07:00:00', 'auto');
        $actions = app(BookingActions::class);

        $actions->reschedule($booking, $this->owner, $this->at('2026-11-30 09:00:00'));
        $actions->reschedule($booking->fresh(), $this->owner, $this->at('2026-11-30 10:00:00'));

        $this->assertSame(2, $this->notices($customer, UserNotice::BOOKING_RESCHEDULED));
        Mail::assertQueued(BookingActivity::class, fn (BookingActivity $m) => $m->kind === UserNotice::BOOKING_RESCHEDULED && count($m->attachments()) === 1);
    }

    public function test_declining_a_move_request_and_handing_over_a_booking_tell_the_right_person(): void
    {
        $customer = $this->customer();
        WorkingHour::forceCreate(['team_id' => $this->team->id, 'weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        $booking = $this->book($customer, '2026-12-01 08:00:00', 'auto');
        app(Bookings::class)->requestReschedule($booking, $customer, 'Earlier please');
        $this->assertSame(1, $this->notices($this->owner, UserNotice::BOOKING_RESCHEDULE_REQUESTED));

        app(BookingActions::class)->declineReschedule($booking->fresh(), $this->owner, 'That day is full.');
        $this->assertSame([1, 1], [$this->notices($customer, UserNotice::BOOKING_RESCHEDULE_DECLINED), $this->mails($customer, UserNotice::BOOKING_RESCHEDULE_DECLINED)]);

        $thandi = $this->member($this->team, 'member', 'Thandi');
        $provider = $this->provider($this->team, $thandi, 'Thandi');
        $this->type->providers()->attach($provider->id);
        app(BookingActions::class)->assign($booking->fresh(), $this->owner, $provider->id);
        $this->assertSame(1, $this->notices($thandi, UserNotice::BOOKING_RECEIVED), 'the provider it was handed to is told');
    }

    public function test_a_customer_cancelling_or_asking_to_move_tells_the_business_not_the_customer(): void
    {
        $customer = $this->customer();
        $one = $this->book($customer, '2026-11-30 07:00:00', 'auto');
        $two = $this->book($customer, '2026-11-30 09:00:00', 'auto');

        app(Bookings::class)->requestReschedule($two, $customer, 'Later please');
        app(Bookings::class)->cancelByCustomer($one, $customer);

        $this->assertSame(1, $this->notices($this->manager, UserNotice::BOOKING_CANCELLED));
        $this->assertSame(1, $this->notices($this->manager, UserNotice::BOOKING_RESCHEDULE_REQUESTED));
        $this->assertSame(0, $this->notices($customer, UserNotice::BOOKING_CANCELLED), 'not told of their own act');
    }

    // ------------------------------------------------------------------------------------------------------------------- once only

    public function test_calling_the_notifier_again_never_tells_anyone_twice(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer);

        app(BookingNotifier::class)->submitted($booking);
        app(BookingNotifier::class)->submitted($booking);

        $this->assertSame([1, 1, 1], [$this->notices($customer), $this->mails($customer), $this->notices($this->owner)]);
    }

    public function test_a_closed_account_is_simply_not_told_and_nothing_breaks(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer, '2026-11-30 07:00:00', 'approval');
        $booking->forceFill(['customer_user_id' => null])->save();

        app(BookingActions::class)->accept($booking->fresh(), $this->owner);

        $this->assertSame(Booking::CONFIRMED, $booking->fresh()->status);
        $this->assertSame(0, UserNotice::where('kind', UserNotice::BOOKING_CONFIRMED)->count());
    }

    // ------------------------------------------------------------------------------------------------------------------- the bell page

    public function test_the_bell_describes_each_notice_for_the_side_the_reader_is_on_and_hides_it_from_anyone_else(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer);
        $forCustomer = UserNotice::where('user_id', $customer->id)->sole();
        $forOwner = UserNotice::where('user_id', $this->owner->id)->sole();
        $notices = app(InAppNotices::class);

        $mine = $notices->describe($forCustomer);
        $this->assertStringContainsString('waiting for them to confirm', $mine['text']);
        $this->assertSame(route('my-bookings.show', $booking->reference), $mine['url']);

        $theirs = $notices->describe($forOwner);
        $this->assertStringContainsString('New booking request', $theirs['text']);
        $this->assertSame(route('business.appointments.show', [$this->team, $booking]), $theirs['url']);
        $this->assertStringNotContainsString('Zodwa', $theirs['text']);

        $stranger = $this->customer('stranger@example.test');
        $leaked = UserNotice::create(['user_id' => $stranger->id, 'kind' => UserNotice::BOOKING_CONFIRMED, 'subject_type' => 'booking', 'subject_id' => $booking->id, 'ref' => 'bk:x', 'created_at' => now()]);
        $this->assertStringContainsString('no longer', strtolower($notices->describe($leaked)['text']), "someone else's booking shows as gone");
        $this->assertSame('Bookings', InAppNotices::label(UserNotice::BOOKING_REMINDER));
    }

    public function test_booking_notices_are_not_silenced_by_the_contributions_switch(): void
    {
        $customer = $this->customer();
        $customer->forceFill(['notices_in_app' => false])->save();

        $this->book($customer);

        $this->assertSame(1, $this->notices($customer, UserNotice::BOOKING_SUBMITTED), 'it is about something they booked');
    }

    // ------------------------------------------------------------------------------------------------------------------- the calendar file

    public function test_the_calendar_file_holds_the_appointment_and_nothing_private_and_cannot_be_injected_into(): void
    {
        $evil = $this->type($this->team, ['name' => "Visit; \"x\", y\nATTENDEE:mailto:evil@x.test", 'meeting_format' => 'in_person', 'location_note' => "12 Dock Road, Durban\r\nORGANIZER:evil", 'duration_minutes' => 60]);
        $booking = app(Bookings::class)->book($this->customer(), $this->team, $evil, $this->at('2026-11-30 08:00:00'), null, ['name' => 'Zodwa Typed', 'email' => 'typed-address@victim.test', 'reason' => 'A private matter']);
        $booking->forceFill(['status' => Booking::CONFIRMED])->save();

        $ics = app(BookingIcs::class)->forBooking($booking->fresh());

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString('DTSTART:20261130T080000Z', $ics);
        $this->assertStringContainsString('UID:'.$booking->reference.'@infodot.co.za', $ics);
        $this->assertStringNotContainsString("\nATTENDEE:", $ics);
        $this->assertStringNotContainsString("\nORGANIZER:", $ics);
        $this->assertStringContainsString('LOCATION:12 Dock Road\, Durban\nORGANIZER:evil', $ics);
        foreach (['Zodwa', 'typed-address', 'private matter'] as $private) {
            $this->assertStringNotContainsString($private, $ics);
        }
        foreach (explode("\r\n", rtrim($ics)) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), 'every line is folded to 75 octets');
        }
    }

    public function test_an_online_appointments_calendar_file_has_no_how_to_join_and_a_pending_one_gets_no_file(): void
    {
        $booking = $this->book($this->customer(), '2026-11-30 08:00:00', 'auto');

        $ics = app(BookingIcs::class)->forBooking($booking);
        $this->assertStringNotContainsString('meet.example', $ics);
        $this->assertStringNotContainsString('LOCATION', $ics);

        $pending = $this->book($this->customer('p@example.test'), '2026-11-30 10:00:00', 'approval');
        $mailable = new BookingActivity(UserNotice::BOOKING_CONFIRMED, $pending, $this->customer('q@example.test'), true);
        $this->assertSame([], $mailable->attachments(), 'only a confirmed appointment comes with a file');
    }

    // ------------------------------------------------------------------------------------------------------------------- reminders

    private function remind(): void
    {
        Artisan::call('booking:remind');
    }

    public function test_the_day_before_reminder_goes_once_to_the_customer_and_the_business_and_the_hour_before_only_to_the_customer(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer, '2026-11-30 08:00:00', 'auto'); // 10:00 local Monday 30 Nov = 08:00Z

        $this->travelTo($this->at('2026-11-29 08:20:00')); // 24 hours less 20 minutes ahead: due 20 minutes ago
        $this->remind();
        $this->remind();
        $this->assertSame([1, 1, 1], [$this->notices($customer, UserNotice::BOOKING_REMINDER), $this->notices($this->owner, UserNotice::BOOKING_REMINDER), $this->mails($customer, UserNotice::BOOKING_REMINDER)]);

        $this->travelTo($this->at('2026-11-30 07:10:00')); // ten minutes after the hour-before mark
        $this->remind();
        $this->assertSame(2, $this->notices($customer, UserNotice::BOOKING_REMINDER));
        $this->assertSame(1, $this->notices($this->owner, UserNotice::BOOKING_REMINDER), 'the business is not reminded an hour before');
        $this->assertSame(BookingActions::class, BookingActions::class);
        $this->assertNotNull($booking);
    }

    public function test_no_reminder_for_a_lead_time_that_had_already_passed_when_it_was_booked_or_for_a_missed_one(): void
    {
        $late = $this->customer('late@example.test');
        $this->travelTo($this->at('2026-11-29 12:00:00')); // 20 hours before the appointment
        $this->book($late, '2026-11-30 08:00:00', 'auto');

        $this->travelTo($this->at('2026-11-29 12:05:00'));
        $this->remind();
        $this->assertSame(0, $this->notices($late, UserNotice::BOOKING_REMINDER), 'it was booked after the 24 hour mark');

        $this->travelTo($this->at('2026-11-30 05:00:00')); // three hours before: the hour-before mark is still ahead
        $this->remind();
        $this->assertSame(0, $this->notices($late, UserNotice::BOOKING_REMINDER));
    }

    public function test_a_reminder_missed_by_more_than_an_hour_is_skipped_not_sent_late(): void
    {
        $customer = $this->customer();
        $this->book($customer, '2026-11-30 08:00:00', 'auto'); // booked 10 days ahead

        $this->travelTo($this->at('2026-11-29 09:30:00')); // the 24 hour mark was 08:00Z: an hour and a half ago
        $this->remind();

        $this->assertSame(0, $this->notices($customer, UserNotice::BOOKING_REMINDER));
    }

    public function test_only_confirmed_future_appointments_are_reminded(): void
    {
        $customer = $this->customer();
        $pending = $this->book($customer, '2026-11-30 08:00:00', 'approval');
        $cancelled = $this->book($customer, '2026-11-30 10:00:00', 'auto');
        app(Bookings::class)->cancelByCustomer($cancelled, $customer);

        $this->travelTo($this->at('2026-11-29 08:20:00'));
        $this->remind();

        $this->assertSame(0, $this->notices($customer, UserNotice::BOOKING_REMINDER));
        $this->assertNotNull($pending);
        $this->assertTrue(class_exists(SendBookingReminders::class));
    }

    // ------------------------------------------------------------------------------------------------------------------- expiry and the pending cap

    public function test_an_unanswered_request_expires_frees_the_time_and_tells_the_customer(): void
    {
        $customer = $this->customer();
        $stale = $this->book($customer, '2026-11-30 08:00:00', 'approval');
        $this->travelTo($this->at('2026-11-23 09:00:00')); // 72 hours later
        $fresh = $this->book($this->customer('b@example.test'), '2026-11-30 10:00:00', 'approval');
        $answered = $this->book($this->customer('c@example.test'), '2026-12-07 08:00:00', 'approval');
        WorkingHour::forceCreate(['team_id' => $this->team->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        $this->travelTo($this->at('2026-11-23 10:00:00'));

        Artisan::call('booking:expire-pending');

        $this->assertSame([Booking::EXPIRED, 'There was no answer in time.'], [$stale->fresh()->status, $stale->fresh()->status_note]);
        $this->assertSame(Booking::PENDING, $fresh->fresh()->status, 'a recent request is left alone');
        $this->assertSame(Booking::PENDING, $answered->fresh()->status);
        $this->assertSame([1, 1], [$this->notices($customer, UserNotice::BOOKING_EXPIRED), $this->mails($customer, UserNotice::BOOKING_EXPIRED)]);
        $this->assertDatabaseHas('booking_events', ['type' => 'booking_expired', 'actor_id' => null]);
        $this->assertStringNotContainsString('Zodwa', json_encode(BookingEvent::all()->toArray()));
    }

    public function test_a_request_still_unanswered_when_it_starts_expires_and_a_confirmed_one_never_does(): void
    {
        $customer = $this->customer();
        $pending = $this->book($customer, '2026-11-30 08:00:00', 'approval');
        $confirmed = $this->book($this->customer('d@example.test'), '2026-11-30 10:00:00', 'auto');
        $this->travelTo($this->at('2026-11-30 09:00:00'));

        Artisan::call('booking:expire-pending');

        $this->assertSame(Booking::EXPIRED, $pending->fresh()->status);
        $this->assertSame(Booking::CONFIRMED, $confirmed->fresh()->status);
        $this->assertSame(0, Booking::where('status', Booking::PENDING)->count());
    }

    public function test_an_expired_request_is_final_and_cannot_be_accepted(): void
    {
        $pending = $this->book($this->customer(), '2026-11-30 08:00:00', 'approval');
        $this->travelTo($this->at('2026-11-30 09:00:00'));
        Artisan::call('booking:expire-pending');

        $e = null;
        try {
            app(BookingActions::class)->accept($pending, $this->owner);
        } catch (BookingRefused $e) {
        }
        $this->assertSame(BookingRefused::STATE, $e?->reason);
    }

    public function test_a_business_can_only_hold_so_many_waiting_requests_but_automatic_ones_are_not_counted(): void
    {
        config(['booking.limits.pending_per_business' => 2, 'booking.limits.pending_new_account_share' => 1.0, 'booking.limits.new_per_day' => 50, 'booking.limits.new_per_business_day' => 50, 'booking.limits.active_per_business' => 50]);
        $this->book($this->customer('a@example.test'), '2026-11-30 07:00:00');
        $this->book($this->customer('b@example.test'), '2026-11-30 09:00:00');

        $e = null;
        try {
            $this->book($this->customer('c@example.test'), '2026-11-30 11:00:00');
        } catch (BookingRefused $e) {
        }
        $this->assertSame(BookingRefused::LIMIT, $e?->reason);

        $auto = $this->book($this->customer('d@example.test'), '2026-11-30 13:00:00', 'auto');
        $this->assertSame(Booking::CONFIRMED, $auto->status);
    }

    public function test_the_two_commands_are_scheduled(): void
    {
        $scheduled = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode("\n");

        $this->assertStringContainsString('booking:remind', $scheduled);
        $this->assertStringContainsString('booking:expire-pending', $scheduled);
    }

    // ------------------------------------------------------------------------------------------------------------------- review fixes

    public function test_a_business_cannot_put_links_images_or_headings_into_an_email_from_infodot(): void
    {
        $this->team->forceFill(['name' => 'Evil [Verify your account](https://evil.example/login) <img src=https://evil.example/p.gif>'])->save();
        $this->type->update(['name' => "Type [click](https://evil.example/t) ![x](https://evil.example/i.gif)\nBcc: evil@x.test"]);
        $customer = $this->customer();
        $customer->forceFill(['name' => '[Pay now](https://evil.example/pay)'])->save();

        $booking = $this->book($customer);
        app(BookingActions::class)->reject($booking, $this->owner, 'Click [here](https://evil.example/note) to fix it.');

        $this->assertGreaterThan(1, Mail::queued(BookingActivity::class)->count());
        foreach (Mail::queued(BookingActivity::class) as $mail) {
            $html = $mail->render();
            $this->assertStringNotContainsString('href="https://evil.example', $html);
            $this->assertStringNotContainsString('src="https://evil.example', $html);
            $this->assertStringNotContainsString('<img src=https://evil', $html);
            $this->assertStringNotContainsString('evil.example/note', $html, 'the business note is never put in a mail');
            preg_match_all('/<a href="([^"]+)"/', $html, $links);
            foreach ($links[1] as $href) {
                $this->assertStringStartsWith(config('app.url'), $href, 'every link in the mail is an InfoDot link');
            }
        }
    }

    public function test_the_mail_says_there_is_a_message_without_carrying_it(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer);
        app(BookingActions::class)->reject($booking, $this->owner, 'We are shut that week.');

        $rejected = Mail::queued(BookingActivity::class, fn (BookingActivity $m) => $m->kind === UserNotice::BOOKING_REJECTED)->first();
        $html = $rejected->render();
        $this->assertStringContainsString('left you a message', $html);
        $this->assertStringNotContainsString('shut that week', $html);
    }

    public function test_subjects_are_tidy_single_line_and_short(): void
    {
        $this->team->forceFill(['name' => "Dlamini\u{2028}Freight ".str_repeat('x', 200)])->save();
        $this->type->update(['name' => "Visit\r\nBcc: x"]);
        $this->book();

        foreach (Mail::queued(BookingActivity::class) as $mail) {
            $subject = $mail->envelope()->subject;
            $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x{2028}\x{2029}]/u', $subject);
            $this->assertLessThan(160, mb_strlen($subject));
        }
    }

    public function test_the_calendar_file_carries_a_sequence_that_grows_and_drops_unicode_line_separators(): void
    {
        $booking = $this->book($this->customer(), '2026-11-30 07:00:00', 'auto');
        $first = app(BookingIcs::class)->forBooking($booking->fresh());
        $this->travelTo($this->at('2026-11-20 08:05:00'));
        app(BookingActions::class)->reschedule($booking->fresh(), $this->owner, $this->at('2026-11-30 09:00:00'));
        $second = app(BookingIcs::class)->forBooking($booking->fresh());

        preg_match('/SEQUENCE:(\d+)/', $first, $a);
        preg_match('/SEQUENCE:(\d+)/', $second, $b);
        $this->assertGreaterThan((int) $a[1], (int) $b[1]);
        $this->assertStringContainsString('UID:'.$booking->reference, $second);

        $this->type->update(['name' => "Visit\u{2028}END:VEVENT\u{2029}x", 'meeting_format' => 'in_person', 'location_note' => "Door\u{0085}ORGANIZER:evil"]);
        $ics = app(BookingIcs::class)->forBooking($booking->fresh());
        $this->assertDoesNotMatchRegularExpression('/[\x{2028}\x{2029}\x{0085}]/u', $ics);
    }

    public function test_moving_back_to_an_earlier_time_or_reassigning_to_an_earlier_provider_is_announced_again(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer, '2026-11-30 07:00:00', 'auto');
        $actions = app(BookingActions::class);

        foreach (['2026-11-30 09:00:00', '2026-11-30 07:00:00', '2026-11-30 09:00:00'] as $i => $start) {
            $this->travelTo($this->at('2026-11-20 08:0'.($i + 1).':00'));
            $actions->reschedule($booking->fresh(), $this->owner, $this->at($start));
        }
        $this->assertSame(3, $this->notices($customer, UserNotice::BOOKING_RESCHEDULED), 'the third move back to 11:00 is a new fact');

        $thandi = $this->member($this->team, 'member', 'Thandi');
        $sipho = $this->member($this->team, 'member', 'Sipho');
        $a = $this->provider($this->team, $thandi, 'Thandi');
        $b = $this->provider($this->team, $sipho, 'Sipho');
        $this->type->providers()->attach([$a->id, $b->id]);
        foreach ([$a->id, $b->id, $a->id] as $i => $to) {
            $this->travelTo($this->at('2026-11-20 08:1'.$i.':00'));
            $actions->assign($booking->fresh(), $this->owner, $to);
        }
        $this->assertSame(2, $this->notices($thandi, UserNotice::BOOKING_RECEIVED), 'told twice that it is hers');
    }

    public function test_a_booking_that_is_moved_is_reminded_again_for_its_new_time(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer, '2026-11-30 08:00:00', 'auto');
        $this->travelTo($this->at('2026-11-29 08:20:00'));
        $this->remind();
        $this->assertSame(1, $this->notices($customer, UserNotice::BOOKING_REMINDER));

        WorkingHour::forceCreate(['team_id' => $this->team->id, 'weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        app(BookingActions::class)->reschedule($booking->fresh(), $this->owner, $this->at('2026-12-01 08:00:00'));

        $this->travelTo($this->at('2026-11-30 08:20:00'));
        $this->remind();

        $this->assertSame(2, $this->notices($customer, UserNotice::BOOKING_REMINDER), 'a new reminder for the new time');
    }

    public function test_throwaway_accounts_can_hold_only_part_of_the_waiting_places(): void
    {
        config(['booking.limits.pending_per_business' => 10, 'booking.limits.new_per_day' => 50, 'booking.limits.new_per_business_day' => 50, 'booking.limits.active_per_business' => 50]);
        WorkingHour::forceCreate(['team_id' => $this->team->id, 'weekday' => 2, 'starts_at' => '00:00', 'ends_at' => '23:59']);
        $slots = array_map(fn ($h) => sprintf('2026-12-01 %02d:00:00', $h), range(0, 14));

        for ($i = 0; $i < 5; $i++) {
            $this->book($this->customer("new{$i}@example.test"), $slots[$i]); // five brand-new accounts: half of the ten places
        }

        $e = null;
        try {
            $this->book($this->customer('new5@example.test'), $slots[5]);
        } catch (BookingRefused $e) {
        }
        $this->assertSame(BookingRefused::LIMIT, $e?->reason, 'a sixth new account is refused while real customers still have room');

        $old = User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(5), 'name' => 'Established']);
        for ($i = 5; $i < 10; $i++) {
            $this->book($old, $slots[$i]);
        }
        try {
            $this->book(User::factory()->create(['email_verified_at' => now(), 'created_at' => now()->subDays(9)]), $slots[10]);
            $this->fail('the whole cap is still enforced');
        } catch (BookingRefused $e) {
            $this->assertSame(BookingRefused::LIMIT, $e->reason);
        }
    }

    public function test_past_a_daily_ceiling_only_the_bell_tells_a_person(): void
    {
        config(['booking.mail_per_person_per_day' => 2, 'booking.limits.new_per_day' => 50, 'booking.limits.new_per_business_day' => 50, 'booking.limits.active_per_business' => 50]);

        foreach (['2026-11-30 07:00:00', '2026-11-30 09:00:00', '2026-11-30 11:00:00'] as $i => $start) {
            $this->book($this->customer("ceil{$i}@example.test"), $start);
        }

        $this->assertSame([3, 2], [$this->notices($this->owner, UserNotice::BOOKING_RECEIVED), $this->mails($this->owner, UserNotice::BOOKING_RECEIVED)]);
    }
}

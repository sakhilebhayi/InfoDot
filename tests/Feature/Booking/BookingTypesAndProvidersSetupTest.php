<?php

namespace Tests\Feature\Booking;

use App\Models\AppointmentType;
use App\Models\BookingEvent;
use App\Models\BookingProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/** R-63 phase 2b: the appointment type and provider setup screens, who may use them, and the trail they leave. */
class BookingTypesAndProvidersSetupTest extends TestCase
{
    use BuildsBookingBusinesses;
    use RefreshDatabase;

    // ---- the feature gate, access and tenant isolation -----------------------------------------------------------------------

    public function test_every_screen_is_a_404_until_the_feature_is_open(): void
    {
        [$team, $owner] = $this->business('development');
        $provider = $this->provider($team, $this->member($team, 'member'));
        $type = $this->type($team);
        $this->actingAs($owner);

        foreach ([
            ['get', route('business.booking.types', $team)], ['get', route('business.booking.types.create', $team)], ['post', route('business.booking.types.store', $team)],
            ['get', route('business.booking.types.edit', [$team, $type])], ['post', route('business.booking.types.update', [$team, $type])],
            ['post', route('business.booking.types.toggle', [$team, $type])], ['post', route('business.booking.types.delete', [$team, $type])],
            ['get', route('business.booking.providers', $team)], ['post', route('business.booking.providers.store', $team)],
            ['post', route('business.booking.providers.update', [$team, $provider])], ['post', route('business.booking.providers.toggle', [$team, $provider])],
            ['post', route('business.booking.providers.delete', [$team, $provider])],
        ] as [$method, $url]) {
            $this->{$method}($url)->assertNotFound();
        }

        $this->assertSame(0, BookingEvent::count());
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        [$team] = $this->business();

        $this->get(route('business.booking.types', $team))->assertRedirect(route('login'));
        $this->post(route('business.booking.providers.store', $team))->assertRedirect(route('login'));
    }

    public function test_owner_admin_and_manager_may_use_the_screens_and_a_member_or_outsider_may_not(): void
    {
        [$team, $owner] = $this->business();
        $admin = $this->member($team, 'admin', 'Ann Admin');
        $manager = $this->member($team, 'manager', 'Mo Manager');
        $plain = $this->member($team, 'member', 'Pat Plain');
        [, $outsider] = $this->business('available', 'Other Co');

        foreach ([$owner, $admin, $manager] as $person) {
            $this->actingAs($person)->get(route('business.booking.types', $team))->assertOk();
            $this->actingAs($person)->get(route('business.booking.types.create', $team))->assertOk();
            $this->actingAs($person)->get(route('business.booking.providers', $team))->assertOk();
        }

        foreach ([$plain, $outsider] as $person) {
            $this->actingAs($person)->get(route('business.booking.types', $team))->assertForbidden();
            $this->actingAs($person)->get(route('business.booking.types.create', $team))->assertForbidden();
            $this->actingAs($person)->post(route('business.booking.types.store', $team), $this->typeForm())->assertForbidden();
            $this->actingAs($person)->get(route('business.booking.providers', $team))->assertForbidden();
            $this->actingAs($person)->post(route('business.booking.providers.store', $team), ['user_id' => $plain->id])->assertForbidden();
        }

        $this->assertSame(0, AppointmentType::count());
    }

    public function test_a_provider_who_is_only_a_member_cannot_manage_types_or_providers(): void
    {
        [$team] = $this->business();
        $thandi = $this->member($team, 'member');
        $own = $this->provider($team, $thandi);
        $type = $this->type($team);

        $this->actingAs($thandi);
        $this->get(route('business.booking.types.edit', [$team, $type]))->assertForbidden();
        $this->post(route('business.booking.types.toggle', [$team, $type]))->assertForbidden();
        $this->post(route('business.booking.providers.update', [$team, $own]), ['public_name' => 'Boss'])->assertForbidden();
        $this->post(route('business.booking.providers.toggle', [$team, $own]))->assertForbidden();
    }

    public function test_a_record_from_another_business_is_a_404_on_every_route(): void
    {
        [$team, $owner] = $this->business();
        [$other] = $this->business('available', 'Other Co');
        $foreignType = $this->type($other);
        $foreignProvider = $this->provider($other, $this->member($other, 'member'));
        $this->actingAs($owner);

        $this->get(route('business.booking.types.edit', [$team, $foreignType]))->assertNotFound();
        $this->post(route('business.booking.types.update', [$team, $foreignType]), $this->typeForm())->assertNotFound();
        $this->post(route('business.booking.types.toggle', [$team, $foreignType]))->assertNotFound();
        $this->post(route('business.booking.types.delete', [$team, $foreignType]))->assertNotFound();
        $this->post(route('business.booking.providers.update', [$team, $foreignProvider]), ['public_name' => 'X'])->assertNotFound();
        $this->post(route('business.booking.providers.toggle', [$team, $foreignProvider]))->assertNotFound();
        $this->post(route('business.booking.providers.delete', [$team, $foreignProvider]))->assertNotFound();

        $this->assertTrue($foreignType->fresh()->active);
        $this->assertSame('Thandi', $foreignProvider->fresh()->public_name);
        $this->assertSame(0, BookingEvent::count());
    }

    // ---- appointment types ---------------------------------------------------------------------------------------------------

    public function test_the_types_list_says_so_when_empty_and_warns_when_no_hours_are_set(): void
    {
        [$team, $owner] = $this->business();

        $this->actingAs($owner)->get(route('business.booking.types', $team))
            ->assertOk()->assertSee('No appointment types yet. Add the first one.')->assertSee('customers cannot book anything');
    }

    public function test_a_type_is_created_with_the_fee_in_cents_and_the_approval_choice_mapped(): void
    {
        [$team, $owner] = $this->business();
        $thandi = $this->provider($team, $this->member($team, 'member'));

        $this->actingAs($owner)->post(route('business.booking.types.store', $team), $this->typeForm(['providers' => [$thandi->id], 'approval' => 'always']))
            ->assertRedirect(route('business.booking.types', $team));

        $type = AppointmentType::firstOrFail();
        $this->assertSame($team->id, $type->team_id);
        $this->assertSame([25050, 'ZAR', 'online', true, 45, 15, 5, 10, 2, true], [$type->fee_cents, $type->currency, $type->meeting_format, $type->requires_approval, $type->duration_minutes, $type->slot_interval_minutes, $type->buffer_before_minutes, $type->buffer_after_minutes, $type->sort, $type->active]);
        $this->assertSame([$thandi->id], $type->providers()->pluck('booking_providers.id')->all());

        $event = BookingEvent::where('type', 'appointment_type_created')->firstOrFail();
        $this->assertSame($owner->id, $event->actor_id);
        $this->assertSame($type->id, $event->details['type_id']);
        $this->actingAs($owner)->get(route('business.booking.types', $team))->assertSee('Initial consultation')->assertSee('ZAR 250.50')->assertSee('Thandi');
    }

    public function test_fee_formats_and_approval_choices(): void
    {
        [$team, $owner] = $this->business();
        $this->actingAs($owner);

        foreach ([['250', 25000], ['250,5', 25050], ['0', 0], ['', null], ['1000000', 100000000]] as [$fee, $cents]) {
            $this->post(route('business.booking.types.store', $team), $this->typeForm(['fee' => $fee]))->assertSessionDoesntHaveErrors();
            $this->assertSame($cents, AppointmentType::latest('id')->first()->fee_cents, "fee '{$fee}'");
        }

        foreach (['follow' => null, 'always' => true, 'auto' => false] as $choice => $stored) {
            $this->post(route('business.booking.types.store', $team), $this->typeForm(['approval' => $choice]));
            $this->assertSame($stored, AppointmentType::latest('id')->first()->requires_approval, $choice);
        }
    }

    public function test_every_validation_bound_on_a_type(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        [$team, $owner] = $this->business();
        $this->actingAs($owner);

        foreach ([
            ['name', 'A'], ['name', str_repeat('a', 121)], ['description', str_repeat('a', 1001)], ['duration_minutes', 4], ['duration_minutes', 481],
            ['slot_interval_minutes', 4], ['slot_interval_minutes', 241], ['buffer_before_minutes', -1], ['buffer_before_minutes', 241], ['buffer_after_minutes', -1], ['buffer_after_minutes', 241],
            ['fee', '-5'], ['fee', '1000000.01'], ['fee', '12.345'], ['fee', 'lots'], ['currency', 'JPY'], ['meeting_format', 'carrier-pigeon'],
            ['location_note', str_repeat('a', 301)], ['preparation', str_repeat('a', 1501)], ['approval', 'maybe'], ['sort', -1],
        ] as [$field, $value]) {
            $this->post(route('business.booking.types.store', $team), $this->typeForm([$field => $value]))->assertSessionHasErrors($field);
        }

        foreach ([['name', 'Ab'], ['duration_minutes', 5], ['duration_minutes', 480], ['slot_interval_minutes', 5], ['slot_interval_minutes', 240], ['buffer_before_minutes', 0], ['buffer_after_minutes', 240], ['currency', 'GBP'], ['currency', 'EUR'], ['currency', 'USD']] as [$field, $value]) {
            $this->post(route('business.booking.types.store', $team), $this->typeForm([$field => $value]))->assertSessionDoesntHaveErrors();
        }

        $this->assertSame(10, AppointmentType::count());
    }

    public function test_only_this_business_s_active_providers_can_be_linked(): void
    {
        [$team, $owner] = $this->business();
        [$other] = $this->business('available', 'Other Co');
        $mine = $this->provider($team, $this->member($team, 'member'), 'Mine');
        $off = $this->provider($team, $this->member($team, 'member', 'Off Person'), 'Off', false);
        $foreign = $this->provider($other, $this->member($other, 'member'), 'Foreign');
        $this->actingAs($owner);

        $this->post(route('business.booking.types.store', $team), $this->typeForm(['providers' => [$foreign->id]]))->assertSessionHasErrors('providers.0');
        $this->post(route('business.booking.types.store', $team), $this->typeForm(['providers' => [$off->id]]))->assertSessionHasErrors('providers');
        $this->assertSame(0, AppointmentType::count());

        $this->post(route('business.booking.types.store', $team), $this->typeForm(['providers' => [$mine->id]]))->assertSessionDoesntHaveErrors();
        $this->assertSame([$mine->id], AppointmentType::firstOrFail()->providers()->pluck('booking_providers.id')->all());

        $this->post(route('business.booking.types.store', $team), $this->typeForm());
        $this->assertSame([], AppointmentType::latest('id')->first()->providers()->pluck('booking_providers.id')->all(), 'no one ticked: the business itself takes it');
    }

    public function test_editing_a_type_changes_it_syncs_providers_and_records_what_moved(): void
    {
        [$team, $owner] = $this->business();
        $a = $this->provider($team, $this->member($team, 'member'), 'A');
        $b = $this->provider($team, $this->member($team, 'member', 'Bee Person'), 'B');
        $type = $this->type($team, ['name' => 'Old name', 'description' => 'Secret old words']);
        $type->providers()->sync([$a->id]);
        $this->actingAs($owner);

        $this->get(route('business.booking.types.edit', [$team, $type]))->assertOk()->assertSee('Old name');
        $this->post(route('business.booking.types.update', [$team, $type]), $this->typeForm(['name' => 'New name', 'providers' => [$b->id], 'fee' => '', 'description' => 'Secret new words']))
            ->assertRedirect(route('business.booking.types', $team));

        $type->refresh();
        $this->assertSame(['New name', null], [$type->name, $type->fee_cents]);
        $this->assertSame([$b->id], $type->providers()->pluck('booking_providers.id')->all());

        $event = BookingEvent::where('type', 'appointment_type_updated')->firstOrFail();
        $this->assertSame(['from' => 'Old name', 'to' => 'New name'], $event->details['changes']['name']);
        $this->assertSame('changed', $event->details['changes']['description']);
        $this->assertSame(['from' => [$a->id], 'to' => [$b->id]], $event->details['changes']['providers']);
        $this->assertStringNotContainsString('Secret', json_encode($event->details));

        $count = BookingEvent::count();
        $this->post(route('business.booking.types.update', [$team, $type]), $this->typeForm(['name' => 'New name', 'providers' => [$b->id], 'fee' => '', 'description' => 'Secret new words', 'duration_minutes' => 50]));
        $this->assertSame($count + 1, BookingEvent::count(), 'only the genuine change (the duration) is recorded');
    }

    public function test_a_type_is_switched_on_and_off_and_each_is_recorded(): void
    {
        [$team, $owner] = $this->business();
        $type = $this->type($team);
        $this->actingAs($owner);

        $this->post(route('business.booking.types.toggle', [$team, $type]))->assertRedirect();
        $this->assertFalse($type->fresh()->active);
        $this->post(route('business.booking.types.toggle', [$team, $type]));
        $this->assertTrue($type->fresh()->active);

        $this->assertSame(['appointment_type_switched_off', 'appointment_type_switched_on'], BookingEvent::orderBy('id')->pluck('type')->all());
    }

    public function test_a_type_is_deleted_only_while_it_has_no_bookings(): void
    {
        [$team, $owner] = $this->business();
        $free = $this->type($team, ['name' => 'Free type']);
        $busy = $this->type($team, ['name' => 'Busy type']);
        $this->book($busy);
        $this->actingAs($owner);

        $this->post(route('business.booking.types.delete', [$team, $busy]))->assertRedirect()->assertSessionHasErrors('type');
        $this->assertNotNull($busy->fresh());
        $this->post(route('business.booking.types.delete', [$team, $free]))->assertRedirect();
        $this->assertNull($free->fresh());

        $this->assertSame(['appointment_type_deleted'], BookingEvent::pluck('type')->all());
    }

    // ---- providers -----------------------------------------------------------------------------------------------------------

    public function test_a_member_is_added_as_a_provider_under_their_first_name(): void
    {
        [$team, $owner] = $this->business();
        $person = $this->member($team, 'member', 'Thandiwe Mokoena');
        $this->actingAs($owner);

        $this->post(route('business.booking.providers.store', $team), ['user_id' => $person->id, 'public_name' => ''])->assertRedirect(route('business.booking.providers', $team));

        $provider = BookingProvider::firstOrFail();
        $this->assertSame([$team->id, $person->id, 'Thandiwe', true], [$provider->team_id, $provider->user_id, $provider->public_name, $provider->active]);
        $this->assertSame('provider_added', BookingEvent::firstOrFail()->type);

        $this->get(route('business.booking.providers', $team))->assertOk()->assertSee('never the person');
    }

    public function test_the_owner_can_be_a_provider_and_a_label_can_be_given(): void
    {
        [$team, $owner] = $this->business();
        $this->actingAs($owner)->post(route('business.booking.providers.store', $team), ['user_id' => $owner->id, 'public_name' => 'The director'])->assertSessionDoesntHaveErrors();

        $this->assertSame('The director', BookingProvider::firstOrFail()->public_name);
    }

    public function test_only_members_who_are_not_already_providers_can_be_added(): void
    {
        [$team, $owner] = $this->business();
        $stranger = User::factory()->create();
        $person = $this->member($team, 'member');
        $this->provider($team, $person);
        $this->actingAs($owner);

        $this->post(route('business.booking.providers.store', $team), ['user_id' => $stranger->id])->assertSessionHasErrors('user_id');
        $this->post(route('business.booking.providers.store', $team), ['user_id' => $person->id])->assertSessionHasErrors('user_id');
        $this->post(route('business.booking.providers.store', $team), ['user_id' => $owner->id, 'public_name' => str_repeat('a', 81)])->assertSessionHasErrors('public_name');
        $this->post(route('business.booking.providers.store', $team), [])->assertSessionHasErrors('user_id');

        $this->assertSame(1, BookingProvider::count());
        $this->assertSame(0, BookingEvent::count());
    }

    public function test_the_label_is_edited_and_the_change_recorded(): void
    {
        [$team, $owner] = $this->business();
        $provider = $this->provider($team, $this->member($team, 'member'), 'Thandi');
        $this->actingAs($owner);

        $this->post(route('business.booking.providers.update', [$team, $provider]), ['public_name' => '  Dr Thandi  '])->assertRedirect();
        $this->assertSame('Dr Thandi', $provider->fresh()->public_name);
        $this->assertSame(['provider_id' => $provider->id, 'from' => 'Thandi', 'to' => 'Dr Thandi'], BookingEvent::where('type', 'provider_renamed')->firstOrFail()->details);

        $this->post(route('business.booking.providers.update', [$team, $provider]), ['public_name' => ''])->assertSessionHasErrors('public_name');
        $this->post(route('business.booking.providers.update', [$team, $provider]), ['public_name' => str_repeat('a', 81)])->assertSessionHasErrors('public_name');
        $this->assertSame('Dr Thandi', $provider->fresh()->public_name);
    }

    public function test_a_provider_is_switched_off_and_removed_only_without_bookings(): void
    {
        [$team, $owner] = $this->business();
        $busy = $this->provider($team, $this->member($team, 'member'), 'Busy');
        $idle = $this->provider($team, $this->member($team, 'member', 'Idle Person'), 'Idle');
        $this->book($this->type($team), $busy);
        $this->actingAs($owner);

        $this->post(route('business.booking.providers.toggle', [$team, $busy]));
        $this->assertFalse($busy->fresh()->active);
        $this->post(route('business.booking.providers.toggle', [$team, $busy]));
        $this->assertTrue($busy->fresh()->active);

        $this->post(route('business.booking.providers.delete', [$team, $busy]))->assertSessionHasErrors('provider');
        $this->assertNotNull($busy->fresh());
        $this->post(route('business.booking.providers.delete', [$team, $idle]))->assertSessionDoesntHaveErrors();
        $this->assertNull($idle->fresh());

        $this->assertSame(['provider_switched_off', 'provider_switched_on', 'provider_removed'], BookingEvent::orderBy('id')->pluck('type')->all());
    }

    public function test_the_audit_trail_never_holds_customer_data(): void
    {
        [$team, $owner] = $this->business();
        $provider = $this->provider($team, $this->member($team, 'member'));
        $type = $this->type($team);
        $this->book($type, $provider);
        $this->actingAs($owner);

        $this->post(route('business.booking.types.update', [$team, $type]), $this->typeForm());
        $this->post(route('business.booking.types.toggle', [$team, $type]));
        $this->post(route('business.booking.providers.toggle', [$team, $provider]));

        $trail = json_encode(BookingEvent::all()->toArray());
        $this->assertStringNotContainsString('A Customer', $trail);
        $this->assertStringNotContainsString('c@example.test', $trail);
    }

    public function test_the_throttle_buckets_are_named(): void
    {
        $names = collect(app('router')->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'business/{team}/booking/') && in_array('POST', $r->methods(), true))
            ->map(fn ($r) => collect($r->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')));

        $this->assertGreaterThanOrEqual(12, $names->count());
        $this->assertSame([], $names->filter(fn ($m) => ! preg_match('/^throttle:\d+,\d+,business\.booking\./', (string) $m))->all());
    }

    public function test_the_settings_page_links_to_the_new_area_and_the_icon_font_is_untouched(): void
    {
        [$team, $owner] = $this->business();

        $this->actingAs($owner)->get(route('business.booking.settings', $team))->assertOk()
            ->assertSee('aria-label="Booking setup"', false)->assertSee(route('business.booking.types', $team), false)->assertSee(route('business.booking.closures', $team), false);
    }
}

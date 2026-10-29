<?php

namespace Tests\Feature\Seminars;

use App\Models\Seminar;
use App\Models\SeminarFeedback;
use App\Models\SeminarRegistration;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Seminars\SeminarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** R-13 seminars: free, hosted by anyone verified, the host's own link, registration, attendance, reminders, ratings. */
class SeminarsTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $name = 'Person'): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => Carbon::now()]);
    }

    private function data(array $over = []): array
    {
        return array_merge([
            'title' => 'Pricing your first product', 'summary' => 'A practical hour on setting a price that pays.',
            'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'duration_minutes' => 60, 'join_url' => 'https://meet.example.test/room-1',
        ], $over);
    }

    private function seminar(User $host, array $over = [], bool $publish = true): Seminar
    {
        return app(SeminarService::class)->save($host, null, $this->data($over), $publish);
    }

    public function test_any_verified_person_can_host_without_a_tier_or_a_company(): void
    {
        $host = $this->person('Host');
        $this->actingAs($host)->post('/seminars', $this->data() + ['publish' => 1])->assertRedirect();

        $s = Seminar::first();
        $this->assertSame([Seminar::PUBLISHED, $host->id], [$s->status, $s->host_id]);
    }

    public function test_a_draft_is_visible_to_the_host_only(): void
    {
        $host = $this->person();
        $s = $this->seminar($host, [], false);

        $this->actingAs($host)->get('/seminars/'.$s->id)->assertOk()->assertSee('Draft: only you can see this');
        $this->actingAs($this->person())->get('/seminars/'.$s->id)->assertNotFound();
        $this->actingAs($this->person())->get('/seminars')->assertOk()->assertDontSee($s->title);
    }

    public function test_it_must_start_in_the_future_and_the_link_must_be_https(): void
    {
        $host = $this->person();
        $this->actingAs($host)->post('/seminars', $this->data(['starts_at' => now()->addMinutes(10)->format('Y-m-d\TH:i')]) + ['publish' => 1])->assertSessionHasErrors('starts_at');
        $this->actingAs($host)->post('/seminars', $this->data(['join_url' => 'http://meet.example.test/x']) + ['publish' => 1])->assertSessionHasErrors('join_url');
        $this->actingAs($host)->post('/seminars', $this->data(['join_url' => 'javascript:alert(1)']) + ['publish' => 1])->assertSessionHasErrors('join_url');
        $this->assertSame(0, Seminar::count());
    }

    public function test_a_host_cannot_flood_the_list(): void
    {
        config(['seminars.max_upcoming_per_host' => 2]);
        $host = $this->person();
        $this->seminar($host);
        $this->seminar($host);

        $this->expectException(ValidationException::class);
        $this->seminar($host);
    }

    public function test_the_join_link_is_never_in_the_page_and_opens_only_for_the_registered_inside_the_window(): void
    {
        $host = $this->person();
        $guest = $this->person('Guest');
        $stranger = $this->person('Stranger');
        $s = $this->seminar($host);

        $page = $this->actingAs($guest)->post('/seminars/'.$s->id.'/register')->assertRedirect();
        $this->actingAs($guest)->get('/seminars/'.$s->id)->assertOk()->assertDontSee('meet.example.test');

        // too early
        $this->actingAs($guest)->get('/seminars/'.$s->id.'/join')->assertRedirect(route('seminars.show', $s));
        $this->assertNull(SeminarRegistration::first()->attended_at);

        $this->travelTo($s->starts_at->copy()->subMinutes(5));
        $this->actingAs($stranger)->get('/seminars/'.$s->id.'/join')->assertRedirect(route('seminars.show', $s));
        $this->actingAs($guest)->get('/seminars/'.$s->id.'/join')->assertRedirect('https://meet.example.test/room-1');
        $this->assertNotNull(SeminarRegistration::first()->attended_at, 'opening the link records attendance');

        $this->travelTo($s->endsAt()->addMinute());
        $this->actingAs($guest)->get('/seminars/'.$s->id.'/join')->assertRedirect(route('seminars.show', $s));
    }

    public function test_the_last_place_goes_to_one_person_the_next_waits_and_is_moved_up(): void
    {
        $host = $this->person();
        $s = $this->seminar($host, ['capacity' => 1]);
        [$a, $b] = [$this->person('A'), $this->person('B')];
        $svc = app(SeminarService::class);

        $this->assertSame('registered', $svc->register($s, $a));
        $this->assertSame('waitlist', $svc->register($s, $b));
        $this->assertSame('registered', $svc->register($s, $a), 'registering twice changes nothing');

        $svc->unregister($s, $a);

        $this->assertSame('registered', SeminarRegistration::where('user_id', $b->id)->value('status'));
        $this->assertSame([UserNotice::SEMINAR_SPOT_OPEN], UserNotice::where('user_id', $b->id)->pluck('kind')->all());
    }

    public function test_the_host_cannot_register_for_their_own_seminar(): void
    {
        $host = $this->person();
        $s = $this->seminar($host);

        $this->actingAs($host)->post('/seminars/'.$s->id.'/register')->assertSessionHasErrors('seminar');
        $this->assertSame(0, SeminarRegistration::count());
    }

    public function test_only_the_host_edits_cancels_or_marks_attendance(): void
    {
        $host = $this->person();
        $other = $this->person();
        $s = $this->seminar($host);

        $this->actingAs($other)->get('/seminars/'.$s->id.'/edit')->assertForbidden();
        $this->actingAs($other)->put('/seminars/'.$s->id, $this->data())->assertForbidden();
        $this->actingAs($other)->post('/seminars/'.$s->id.'/cancel')->assertForbidden();
        $this->actingAs($other)->post('/seminars/'.$s->id.'/attendance/'.$other->id, ['attended' => 1])->assertForbidden();
    }

    public function test_cancelling_tells_everyone_who_registered_once(): void
    {
        $host = $this->person();
        $s = $this->seminar($host);
        $a = $this->person();
        app(SeminarService::class)->register($s, $a);

        $this->actingAs($host)->post('/seminars/'.$s->id.'/cancel')->assertRedirect();
        $this->actingAs($host)->post('/seminars/'.$s->id.'/cancel');

        $this->assertSame([UserNotice::SEMINAR_CANCELLED], UserNotice::where('user_id', $a->id)->pluck('kind')->all());
        $this->assertSame([], UserNotice::where('user_id', $host->id)->pluck('kind')->all());
        $this->actingAs($this->person())->post('/seminars/'.$s->id.'/register')->assertNotFound();
    }

    public function test_reminders_go_to_registered_people_once_per_window(): void
    {
        $host = $this->person();
        $s = $this->seminar($host, ['starts_at' => now()->addHours(20)->format('Y-m-d\TH:i')]);
        [$a, $waiting] = [$this->person(), $this->person()];
        $svc = app(SeminarService::class);
        $svc->register($s, $a);
        SeminarRegistration::create(['seminar_id' => $s->id, 'user_id' => $waiting->id, 'status' => 'waitlist']);

        $this->assertSame(1, $svc->sendReminders());
        $this->assertSame(0, $svc->sendReminders(), 'running again sends nothing new');

        $this->travelTo($s->starts_at->copy()->subMinutes(30));
        $this->assertSame(1, $svc->sendReminders(), 'the hour-before reminder is its own notice');
        $this->assertSame(2, UserNotice::where('user_id', $a->id)->count());
        $this->assertSame(0, UserNotice::where('user_id', $waiting->id)->count());

        $page = $this->actingAs($a)->get('/notifications')->assertOk()->getContent();
        $this->assertStringContainsString('Reminder: you registered for the seminar', $page);
    }

    public function test_only_people_who_attended_can_rate_once_and_the_average_waits_for_enough_ratings(): void
    {
        $host = $this->person('Host');
        $s = $this->seminar($host);
        $svc = app(SeminarService::class);
        $people = collect(range(1, 4))->map(fn ($i) => $this->person("P$i"));
        foreach ($people as $p) {
            $svc->register($s, $p);
        }
        $absent = $people[3];
        $this->travelTo($s->endsAt()->addMinute());
        foreach ($people->take(3) as $p) {
            $svc->setAttended($s, $p, true);
        }

        try {
            $svc->rate($s, $absent, 5, null);
            $this->fail('someone who did not attend must not rate');
        } catch (ValidationException) {
        }

        $svc->rate($s, $people[0], 5, 'Very useful');
        $svc->rate($s, $people[0], 4, 'Very useful');
        $this->assertSame(1, SeminarFeedback::count(), 'one rating per person');
        $this->assertNull($svc->hostStats($host)['average'], 'one rating is not shown as an average');

        $svc->rate($s, $people[1], 4, null);
        $svc->rate($s, $people[2], 3, null);
        $stats = $svc->hostStats($host);
        $this->assertSame([1, 3, 3, 3.7], [$stats['hosted'], $stats['attendees'], $stats['ratings'], $stats['average']]);

        $page = $this->actingAs($this->person())->get('/seminars/'.$s->id)->assertOk()->getContent();
        $this->assertStringContainsString('rated 3.7 out of 5 by 3 people', $page);
        $this->assertStringNotContainsString('Very useful', $page, 'comments are for the host only');
        $this->actingAs($host)->get('/seminars/'.$s->id)->assertSee('Very useful');
    }

    public function test_seminars_are_found_in_the_list_and_the_nav_and_need_no_reputation(): void
    {
        $host = $this->person();
        $s = $this->seminar($host);

        $html = $this->actingAs($this->person())->get('/seminars')->assertOk()->getContent();
        $this->assertStringContainsString($s->title, $html);
        $this->assertStringContainsString('data-area="knowledge"', $html);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/seminars')->assertRedirect();
        $this->post('/seminars/1/register')->assertRedirect();
    }
}

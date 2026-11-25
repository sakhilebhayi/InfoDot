<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsBooking;
use App\Models\AppointmentType;
use App\Models\Attachment;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Services\Booking\Availability;
use App\Services\Booking\BookingFeature;
use App\Services\Booking\BookingRefused;
use App\Services\Booking\Bookings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * The customer side of booking (R-63 phase 3): anyone can look at a business's calendar, a signed-in person confirms a time and books it.
 *
 * Every date, month and instant here is worked out by the availability engine; this controller only asks it and draws the answer. The page never
 * reads a booking: not who made one, not how many exist. Everything is a 404 until the `bookings` feature is available, and a business that is
 * not visible to the person is a 404 exactly as its business page is.
 */
class BookingPublicController extends Controller
{
    use GuardsBooking;

    /** How many months ahead the calendar can be turned (the business's own horizon still decides which dates are bookable). */
    private const MONTHS_AHEAD = 14;

    private const START_FORMAT = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';

    public function __construct(private readonly Availability $availability, private readonly Bookings $bookings) {}

    public function show(Request $request, Team $team): View
    {
        $user = $request->user();
        $this->visible($team, $request);

        $setting = BookingSetting::forTeam($team);
        $closed = $this->bookings->whyNot(null, $team);
        $view = [
            'team' => $team, 'setting' => $setting, 'zone' => $setting->timezone, 'closed' => $closed, 'types' => collect(), 'type' => null, 'month' => null, 'weeks' => [], 'date' => null, 'slots' => collect(), 'dayStatus' => null,
            'memberNote' => null, 'prevMonth' => null, 'nextMonth' => null, 'logo' => Attachment::shown($team->currentMedia('logo')),
        ];

        if ($closed !== null) {
            return view('business.book.show', $view);
        }

        $view['memberNote'] = $user === null ? null : $this->bookings->whyNot($user, $team);
        $view['types'] = $team->appointmentTypes()->active()->orderBy('sort')->orderBy('name')->get();
        $view['type'] = $view['types']->firstWhere('id', $request->integer('type'));

        if ($view['type'] === null) {
            return view('business.book.show', $view);
        }

        $zone = $setting->zone();
        $first = CarbonImmutable::now($zone)->startOfMonth();
        $last = $first->addMonthsNoOverflow(self::MONTHS_AHEAD);
        $date = $this->date($request->query('date'), $zone, $first, $last);
        $month = $date !== null ? $date->startOfMonth() : $this->month($request->query('month'), $zone, $first, $last);

        $statuses = $this->kept($team, 'month:'.$view['type']->getKey().':'.$month->format('Y-m').':'.$view['type']->updated_at?->timestamp, fn () => $this->availability->monthStatuses($view['type'], (int) $month->year, (int) $month->month));

        $view['month'] = $month;
        $view['weeks'] = $this->weeks($month, $statuses);
        $view['prevMonth'] = $month->gt($first) ? $month->subMonthNoOverflow() : null;
        $view['nextMonth'] = $month->lt($last) ? $month->addMonthNoOverflow() : null;

        if ($date !== null) {
            $view['date'] = $date;
            $view['dayStatus'] = $statuses[$date->format('Y-m-d')] ?? Availability::OUTSIDE_WINDOW;
            $view['slots'] = $this->kept($team, 'day:'.$view['type']->getKey().':'.$date->format('Y-m-d').':'.$view['type']->updated_at?->timestamp, fn () => $this->availability->slots($view['type'], $date->format('Y-m-d')))->map(fn ($slot) => [
                'start' => $slot->start, 'local' => $slot->start->setTimezone($zone),
                'url' => route('business.book.confirm', ['team' => $team, 'type' => $view['type']->getKey(), 'start' => $this->stamp($slot->start)]),
            ]);
        }

        return view('business.book.show', $view);
    }

    /**
     * A calendar answer kept for a few seconds. Only what the public page shows is kept (statuses and open slots, never a booking), and a booking is always
     * re-checked under the business's lock, so an answer a few seconds old can show a time that has just gone but can never double-book it.
     *
     * @template T
     *
     * @param  callable(): T  $make
     * @return T
     */
    private function kept(Team $team, string $key, callable $make): mixed
    {
        $seconds = (int) config('booking.public.cache_seconds');

        // Only an answer that has to be worked out (a miss) counts against the business's allowance, so a crowd re-reading the same page costs nothing
        // and cannot lock real customers out; it takes many different pages to reach the ceiling.
        $work = function () use ($team, $make) {
            abort_unless(RateLimiter::attempt('book-view:'.$team->getKey(), (int) config('booking.public.team_views_per_minute'), fn () => true, 60), 429, 'This calendar is very busy. Try again in a minute.');

            return $make();
        };

        return $seconds > 0 ? Cache::remember('booking-public:'.$key, $seconds, $work) : $work();
    }

    public function confirm(Request $request, Team $team): View|RedirectResponse
    {
        $user = $this->user();
        $this->visible($team, $request);

        $setting = BookingSetting::forTeam($team);
        $closed = $this->bookings->whyNot($user, $team);
        $type = $this->type($team, $request->integer('type'));
        // A query value can arrive as an array (?start[]=x): anything that is not a plain string is not a time.
        $start = $this->instant(is_string($request->query('start')) ? $request->query('start') : '');

        abort_if($start === null, 404);

        $zone = $setting->zone();
        $local = $start->setTimezone($zone);

        if ($closed === null && $this->availability->find($type, $start, null, now()) === null) {
            return redirect($this->dateUrl($team, $type, $local))->withErrors(['booking' => 'That time has just been taken or is no longer available. Choose another time.']);
        }

        return view('business.book.confirm', [
            'team' => $team, 'setting' => $setting, 'zone' => $setting->timezone, 'type' => $type, 'start' => $start, 'local' => $local,
            'closed' => $closed, 'dateUrl' => $this->dateUrl($team, $type, $local), 'user' => $user,
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        $user = $this->user();
        $this->visible($team, $request);

        $setting = BookingSetting::forTeam($team);
        $hasTerms = filled($setting->terms);

        $data = $request->validate([
            'type' => ['required', 'integer'],
            'start' => ['required', 'string', 'regex:'.self::START_FORMAT],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:160'],
            'reason' => ['nullable', 'string', 'max:1500'],
            'terms_accepted' => $hasTerms ? ['accepted'] : ['nullable'],
        ], ['terms_accepted.accepted' => 'Please accept '.$team->name.'’s terms to book.']);

        $type = $this->type($team, (int) $data['type']);
        $start = $this->instant($data['start']);
        abort_if($start === null, 404);

        $local = $start->setTimezone($setting->zone());

        try {
            // Which provider takes the appointment is the engine's choice (the lowest free); a customer cannot probe or pick one by id.
            $booking = $this->bookings->book($user, $team, $type, $start, null, [
                'name' => trim($data['name']), 'email' => trim($data['email']), 'phone' => $data['phone'] ?? null, 'company' => $data['company'] ?? null, 'reason' => $data['reason'] ?? null, 'terms_accepted' => $hasTerms,
            ]);
        } catch (BookingRefused $refused) {
            // A time that is gone sends the person to that date, where the refreshed list is; anything else keeps them on the form with what they typed.
            if ($refused->reason === BookingRefused::SLOT_TAKEN) {
                return redirect($this->dateUrl($team, $type, $local))->withErrors(['booking' => $refused->getMessage()]);
            }

            return redirect()->back()->withInput()->withErrors(['booking' => $refused->getMessage()]);
        }

        return redirect()->route('my-bookings.show', $booking->reference)->with('status', 'booked');
    }

    // ------------------------------------------------------------------------------------------------------------------------------ helpers

    /** The feature is open and the person may see this business: otherwise there is nothing here. */
    private function visible(Team $team, Request $request): void
    {
        abort_unless(app(BookingFeature::class)->available(), 404);
        abort_unless($team->isVisibleTo($request->user()), 404);
    }

    /** An active type of this business, or a 404. */
    private function type(Team $team, int $id): AppointmentType
    {
        return $team->appointmentTypes()->active()->whereKey($id)->firstOrFail();
    }

    private function instant(string $value): ?CarbonImmutable
    {
        if (preg_match(self::START_FORMAT, $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'UTC')->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function stamp(CarbonImmutable $instant): string
    {
        return $instant->utc()->format('Y-m-d\TH:i:s\Z');
    }

    private function dateUrl(Team $team, AppointmentType $type, CarbonImmutable $local): string
    {
        return route('business.book', ['team' => $team, 'type' => $type->getKey(), 'date' => $local->format('Y-m-d')]);
    }

    /** The month asked for as its first day in the business zone, held between this month and the furthest month ahead. */
    private function month(mixed $value, \DateTimeZone $zone, CarbonImmutable $first, CarbonImmutable $last): CarbonImmutable
    {
        $asked = is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1 ? CarbonImmutable::createFromFormat('!Y-m', $value, $zone) : null;

        if (! $asked instanceof CarbonImmutable) {
            return $first;
        }

        return $asked->lt($first) ? $first : ($asked->gt($last) ? $last : $asked);
    }

    /** The date asked for, only when it is a real date inside the months the calendar can show. */
    private function date(mixed $value, \DateTimeZone $zone, CarbonImmutable $first, CarbonImmutable $last): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $zone);

        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value || $date->lt($first) || $date->gt($last->endOfMonth())) {
            return null;
        }

        return $date;
    }

    /**
     * The month as Monday-first weeks of seven cells; a cell outside the month is null.
     *
     * @param  array<string, string>  $statuses
     * @return list<list<array{date: string, day: int, status: string}|null>>
     */
    private function weeks(CarbonImmutable $month, array $statuses): array
    {
        $cells = array_fill(0, $month->dayOfWeekIso - 1, null);

        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $date = $month->setDate((int) $month->year, (int) $month->month, $day)->format('Y-m-d');
            $cells[] = ['date' => $date, 'day' => $day, 'status' => $statuses[$date] ?? Availability::OUTSIDE_WINDOW];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return array_chunk($cells, 7);
    }
}

<?php

namespace App\Services\Booking;

use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingClosure;
use App\Models\BookingProvider;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Collection;

/**
 * What is bookable, worked out on the server and nowhere else (R-63). The public calendar, the slot list and booking creation all ask this class.
 *
 * Time: every date is a date in the BUSINESS's time zone and every instant is held in UTC. Recurring hours are local times, turned into instants for
 * the date asked about, so daylight-saving days come out right (the day a clock jumps forward simply has fewer hours). Slots step in real elapsed
 * minutes from the start of each stretch of working hours.
 *
 * What makes a start time bookable, for one provider (or for the business itself when the type has no providers): the whole appointment lies inside
 * working hours (a provider's own hours count only where they fall inside the business's; a provider with no hours of their own works the business's);
 * it does not touch a closure of the business or of that provider; there is at least the least-notice before it and it is not beyond the furthest-ahead
 * date; and its time, with the type's buffers on both sides, does not overlap the time held by another pending or confirmed booking of that provider.
 * Buffers matter only between bookings; they may run past the end of the working day. Intervals are half-open, so back-to-back appointments are fine.
 *
 * Nothing here looks at whether the business has switched bookings on: that is the caller's rule. Nothing here ever reads who booked.
 */
class Availability
{
    public const AVAILABLE = 'available';

    public const FULL = 'full';

    public const UNAVAILABLE = 'unavailable';

    public const OUTSIDE_WINDOW = 'outside_window';

    public const NOT_OFFERED = 'not_offered';

    /**
     * The bookable start times on one date.
     *
     * @param  string|CarbonInterface  $date  a date in the business's zone ('Y-m-d'), or an instant (taken in the business's zone)
     * @return Collection<int, Slot> in time order; where several providers are free at the same time, the one with the lowest id
     */
    public function slots(AppointmentType $type, string|CarbonInterface $date, ?int $providerId = null, ?CarbonInterface $now = null, ?int $ignoreBookingId = null, bool $forUpdate = false, bool $ignoreNotice = false): Collection
    {
        $tz = $this->zone($type);
        $local = $this->localDate($tz, $date);

        return $this->daySlots($this->load($type, $tz, $local, 0, $providerId, $ignoreBookingId, $forUpdate, $ignoreNotice), $local, $this->instant($now))['open'];
    }

    /**
     * The status of one date: available, full, unavailable (a holiday or block), outside_window (past, too soon or too far ahead) or not_offered
     * (no appointments of this type on that weekday). Only a status: never a name or a count.
     */
    public function dayStatus(AppointmentType $type, string|CarbonInterface $date, ?int $providerId = null, ?CarbonInterface $now = null): string
    {
        $tz = $this->zone($type);
        $local = $this->localDate($tz, $date);

        return $this->daySlots($this->load($type, $tz, $local, 0, $providerId), $local, $this->instant($now))['status'];
    }

    /**
     * The status of every date in a month ('Y-m-d' => status), from one load of the schedule.
     *
     * @return array<string, string>
     */
    public function monthStatuses(AppointmentType $type, int $year, int $month, ?int $providerId = null, ?CarbonInterface $now = null): array
    {
        $tz = $this->zone($type);
        $first = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $tz)->format('Y-m-d');
        $days = CarbonImmutable::parse($first)->daysInMonth;
        $world = $this->load($type, $tz, $first, $days, $providerId);
        $at = $this->instant($now);
        $out = [];

        for ($i = 0; $i < $days; $i++) {
            $date = CarbonImmutable::parse($first)->addDays($i)->format('Y-m-d');
            $out[$date] = $this->daySlots($world, $date, $at)['status'];
        }

        return $out;
    }

    /**
     * The slot starting exactly at this instant, or null when it is not bookable. This is the check a booking is made on: call it inside the
     * transaction that holds the business's lock, so what it saw cannot change before the booking is written. `$forUpdate` makes the read of the held
     * bookings a locking read, which on InnoDB sees the latest committed rows whatever the transaction's snapshot is. `$ignoreNotice` is for the
     * business moving or assigning an appointment itself: the least-notice rule protects the business from customers, not from its own decisions.
     */
    public function find(AppointmentType $type, CarbonInterface $start, ?int $providerId = null, ?CarbonInterface $now = null, ?int $ignoreBookingId = null, bool $forUpdate = false, bool $ignoreNotice = false): ?Slot
    {
        $wanted = CarbonImmutable::instance($start)->utc()->getTimestamp();

        foreach ($this->slots($type, $start, $providerId, $now, $ignoreBookingId, $forUpdate, $ignoreNotice) as $slot) {
            if ($slot->start->getTimestamp() === $wanted) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * The minutes the business offered between two local dates, both included: the working hours of every active provider (their own hours inside the
     * business's, or the business's when they have none) plus the business's own hours when it has an active appointment type no provider offers, less the
     * business's closures and each provider's own. This is the "available" side of utilisation, worked out with the same hours and closure rules the
     * calendar uses. `$onlyProviderIds` narrows it to those providers (and leaves out the business's own hours); an empty list is no capacity at all.
     *
     * @param  list<int>|null  $onlyProviderIds
     */
    public function capacityMinutes(Team $team, string $fromDate, string $toDate, ?array $onlyProviderIds = null): int
    {
        if ($onlyProviderIds === []) {
            return 0;
        }

        $tz = BookingSetting::forTeam($team)->zone();
        $first = CarbonImmutable::parse($fromDate, $tz)->startOfDay();
        $last = CarbonImmutable::parse($toDate, $tz)->startOfDay();
        $rows = WorkingHour::query()->where('team_id', $team->getKey())->get(['booking_provider_id', 'weekday', 'starts_at', 'ends_at']);
        $business = $this->weekly($rows->whereNull('booking_provider_id'));

        $resources = [];

        foreach (BookingProvider::query()->where('team_id', $team->getKey())->where('active', true)->when($onlyProviderIds !== null, fn ($q) => $q->whereIn('id', $onlyProviderIds))->get(['id']) as $provider) {
            $own = $this->weekly($rows->where('booking_provider_id', $provider->id));
            $resources[] = ['id' => (int) $provider->id, 'hours' => $own === [] ? $business : $this->intersect($own, $business)];
        }

        if ($onlyProviderIds === null && AppointmentType::query()->where('team_id', $team->getKey())->where('active', true)->whereDoesntHave('providers')->exists()) {
            $resources[] = ['id' => null, 'hours' => $business];
        }

        $closures = BookingClosure::query()->where('team_id', $team->getKey())->where('starts_at', '<', $last->addDay()->utc())->where('ends_at', '>', $first->utc())->get()
            ->map(fn (BookingClosure $c) => ['provider' => $c->booking_provider_id, 'from' => $c->starts_at->getTimestamp(), 'to' => $c->ends_at->getTimestamp()])->all();

        $seconds = 0;

        for ($day = $first; $day->lte($last); $day = $day->addDay()) {
            $date = $day->format('Y-m-d');
            $weekday = $day->dayOfWeekIso;

            foreach ($resources as $resource) {
                foreach ($resource['hours'][$weekday] ?? [] as [$a, $b]) {
                    $free = [[$this->at($tz, $date, $a)->getTimestamp(), $this->at($tz, $date, $b)->getTimestamp()]];

                    foreach ($closures as $closure) {
                        if ($closure['provider'] === null || $closure['provider'] === $resource['id']) {
                            $free = $this->minus($free, $closure['from'], $closure['to']);
                        }
                    }

                    foreach ($free as [$from, $to]) {
                        $seconds += $to - $from;
                    }
                }
            }
        }

        return intdiv($seconds, 60);
    }

    /**
     * Free stretches with one blocked stretch taken out.
     *
     * @param  list<array{0: int, 1: int}>  $free
     * @return list<array{0: int, 1: int}>
     */
    private function minus(array $free, int $from, int $to): array
    {
        $out = [];

        foreach ($free as [$a, $b]) {
            if ($to <= $a || $from >= $b) {
                $out[] = [$a, $b];

                continue;
            }
            if ($from > $a) {
                $out[] = [$a, $from];
            }
            if ($to < $b) {
                $out[] = [$to, $b];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------------------------------------------------------------------ the day

    /**
     * @param  array<string, mixed>  $w  the loaded schedule
     * @return array{status: string, open: Collection<int, Slot>}
     */
    private function daySlots(array $w, string $date, CarbonImmutable $now): array
    {
        $tz = $w['tz'];
        $today = $now->setTimezone($tz)->startOfDay();
        $day = CarbonImmutable::parse($date, $tz)->startOfDay();
        $lastDay = $today->addDays($w['horizon_days']);

        // A date in the past or beyond the furthest-ahead date is outside the window whatever else is true of it.
        if ($day->lt($today) || $day->gt($lastDay)) {
            return ['status' => self::OUTSIDE_WINDOW, 'open' => collect()];
        }

        $earliest = $now->addMinutes($w['notice']);
        $offered = false;
        $beforeNotice = false;
        $open = collect();
        $notClosed = false;

        foreach ($w['resources'] as $resource) {
            foreach ($this->grid($w, $resource, $date) as [$start, $end]) {
                $offered = true;

                if ($this->touchesClosure($w, $resource, $start, $end)) {
                    continue;
                }
                $notClosed = true;

                if ($start->lt($earliest) || $start->lte($now)) {
                    continue;
                }
                $beforeNotice = true;

                if ($this->touchesBooking($w, $resource, $start, $end)) {
                    continue;
                }

                $open->push(new Slot($start, $end, $resource['id']));
            }
        }

        $status = match (true) {
            ! $offered => self::NOT_OFFERED,
            ! $notClosed => self::UNAVAILABLE,
            ! $beforeNotice => self::OUTSIDE_WINDOW,
            $open->isEmpty() => self::FULL,
            default => self::AVAILABLE,
        };

        // One start time once, taken by the provider with the lowest id who is free at that time.
        $unique = $open->sortBy(fn (Slot $s) => [$s->start->getTimestamp(), $s->providerId ?? 0])->unique(fn (Slot $s) => $s->start->getTimestamp())->values();

        return ['status' => $status, 'open' => $unique];
    }

    /**
     * Every start time the working hours allow on a date for one resource, before closures, notice and bookings: [start, end] pairs in UTC.
     *
     * @param  array<string, mixed>  $w
     * @param  array{id: int|null, hours: array<int, list<array{0: int, 1: int}>>}  $resource
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function grid(array $w, array $resource, string $date): array
    {
        $weekday = CarbonImmutable::parse($date)->dayOfWeekIso;
        $type = $w['type'];
        $out = [];

        foreach ($resource['hours'][$weekday] ?? [] as [$from, $to]) {
            $windowStart = $this->at($w['tz'], $date, $from);
            $windowEnd = $this->at($w['tz'], $date, $to);
            $start = $windowStart;

            while ($start->addMinutes($type->duration_minutes)->lte($windowEnd)) {
                $out[] = [$start, $start->addMinutes($type->duration_minutes)];
                $start = $start->addMinutes(max(1, $type->slot_interval_minutes));
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $w @param  array{id: int|null}  $resource */
    private function touchesClosure(array $w, array $resource, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        foreach ($w['closures'] as $closure) {
            if (($closure['provider'] === null || $closure['provider'] === $resource['id']) && $start->lt($closure['to']) && $closure['from']->lt($end)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $w @param  array{id: int|null}  $resource */
    private function touchesBooking(array $w, array $resource, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        $type = $w['type'];
        $from = $start->subMinutes($type->buffer_before_minutes);
        $until = $end->addMinutes($type->buffer_after_minutes);

        foreach ($w['bookings'] as $held) {
            if ($held['provider'] === $resource['id'] && $from->lt($held['until']) && $held['from']->lt($until)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------------------------------------------------------------------ loading

    /**
     * Everything the day maths needs, read once for the range: the business's settings and zone, the resources that can take this type with their
     * weekly hours, the closures and the held bookings that touch the range. All scoped to the business that owns the type.
     *
     * @return array<string, mixed>
     */
    private function load(AppointmentType $type, DateTimeZone $tz, string $firstDate, int $days, ?int $providerId, ?int $ignoreBookingId = null, bool $forUpdate = false, bool $ignoreNotice = false): array
    {
        $team = Team::query()->findOrFail($type->team_id);
        $setting = BookingSetting::forTeam($team);

        $from = CarbonImmutable::parse($firstDate, $tz)->startOfDay()->subDay()->utc();
        $to = CarbonImmutable::parse($firstDate, $tz)->startOfDay()->addDays(max(1, $days) + 1)->utc();

        $rows = WorkingHour::query()->where('team_id', $team->id)->get(['booking_provider_id', 'weekday', 'starts_at', 'ends_at']);
        $business = $this->weekly($rows->whereNull('booking_provider_id'));

        // A link to a provider of another business is never used, and a type whose only links are foreign offers nothing: it does not fall back
        // to the business's own hours.
        $everyLink = $type->providers()->get();
        $linked = $everyLink->where('team_id', $team->id);
        $resources = [];

        if ($everyLink->isEmpty()) {
            if ($providerId === null) {
                $resources[] = ['id' => null, 'hours' => $business];
            }
        } else {
            foreach ($linked->where('active', true)->sortBy('id') as $provider) {
                if ($providerId !== null && $provider->id !== $providerId) {
                    continue;
                }
                $own = $this->weekly($rows->where('booking_provider_id', $provider->id));
                $resources[] = ['id' => $provider->id, 'hours' => $own === [] ? $business : $this->intersect($own, $business)];
            }
        }

        $closures = BookingClosure::query()->where('team_id', $team->id)->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get()
            ->map(fn (BookingClosure $c) => ['provider' => $c->booking_provider_id, 'from' => CarbonImmutable::instance($c->starts_at)->utc(), 'to' => CarbonImmutable::instance($c->ends_at)->utc()])->all();

        $bookings = Booking::query()->where('team_id', $team->id)->holding()
            ->where('blocks_from', '<', $to)->where('blocks_until', '>', $from)
            ->when($ignoreBookingId !== null, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            // A booking made under the lock must read the LATEST committed bookings, not a snapshot older than the lock (InnoDB repeatable read).
            ->when($forUpdate, fn ($q) => $q->lockForUpdate())->get()
            ->map(fn (Booking $b) => ['provider' => $b->booking_provider_id, 'from' => CarbonImmutable::instance($b->blocks_from)->utc(), 'until' => CarbonImmutable::instance($b->blocks_until)->utc()])->all();

        return [
            'type' => $type, 'tz' => $tz, 'resources' => $resources, 'closures' => $closures, 'bookings' => $bookings,
            'notice' => $ignoreNotice ? 0 : $setting->min_notice_minutes, 'horizon_days' => $setting->horizon_days,
        ];
    }

    /**
     * Weekly hours as minutes since local midnight, merged: weekday => list of [from, to].
     *
     * @param  Collection<int, WorkingHour>  $rows
     * @return array<int, list<array{0: int, 1: int}>>
     */
    private function weekly(Collection $rows): array
    {
        $byDay = [];

        foreach ($rows as $row) {
            $from = $this->minutes((string) $row->starts_at);
            $to = $this->minutes((string) $row->ends_at);

            if ($to > $from) {
                $byDay[(int) $row->weekday][] = [$from, $to];
            }
        }

        return array_map(fn (array $list) => $this->merge($list), $byDay);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $list
     * @return list<array{0: int, 1: int}>
     */
    private function merge(array $list): array
    {
        usort($list, fn (array $a, array $b) => $a[0] <=> $b[0]);
        $merged = [];

        foreach ($list as [$from, $to]) {
            $last = count($merged) - 1;

            if ($last >= 0 && $from <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $to);
            } else {
                $merged[] = [$from, $to];
            }
        }

        return $merged;
    }

    /**
     * The parts of a provider's own hours that fall inside the business's, weekday by weekday.
     *
     * @param  array<int, list<array{0: int, 1: int}>>  $own
     * @param  array<int, list<array{0: int, 1: int}>>  $business
     * @return array<int, list<array{0: int, 1: int}>>
     */
    private function intersect(array $own, array $business): array
    {
        $out = [];

        foreach ($own as $weekday => $mine) {
            foreach ($mine as [$a, $b]) {
                foreach ($business[$weekday] ?? [] as [$c, $d]) {
                    $from = max($a, $c);
                    $to = min($b, $d);

                    if ($to > $from) {
                        $out[$weekday][] = [$from, $to];
                    }
                }
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------------------------------------------------------------------ time

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);

        return $h * 60 + $m;
    }

    /** A local date and minutes since local midnight as a UTC instant (1440 is the next midnight). */
    private function at(DateTimeZone $tz, string $date, int $minutes): CarbonImmutable
    {
        $day = CarbonImmutable::parse($date, $tz)->startOfDay();

        return CarbonImmutable::create($day->year, $day->month, $day->day, intdiv($minutes, 60) % 24, $minutes % 60, 0, $tz)->addDays(intdiv($minutes, 1440))->utc();
    }

    private function zone(AppointmentType $type): DateTimeZone
    {
        return BookingSetting::forTeam(Team::query()->findOrFail($type->team_id))->zone();
    }

    private function localDate(DateTimeZone $tz, string|CarbonInterface $date): string
    {
        return is_string($date) ? $date : CarbonImmutable::instance($date)->setTimezone($tz)->format('Y-m-d');
    }

    private function instant(?CarbonInterface $now): CarbonImmutable
    {
        return CarbonImmutable::instance($now ?? now())->utc();
    }
}

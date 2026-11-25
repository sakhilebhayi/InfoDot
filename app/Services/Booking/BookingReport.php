<?php

namespace App\Services\Booking;

use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The booking reports (R-63 phase 6): counts, popular types, a trend, utilisation and the no-show rate, worked out from what was really recorded and
 * nothing else. Built from a BookingBoard, so a provider's numbers are only their own appointments and a manager's are the business's (or the
 * provider they narrowed to). A figure that cannot be worked out honestly is null, and the screen says so rather than showing a made-up zero.
 */
class BookingReport
{
    /** The most rows one export carries. */
    public const EXPORT_LIMIT = 5000;

    /** Statuses whose time was really taken from the calendar. */
    private const TOOK_TIME = [Booking::PENDING, Booking::CONFIRMED, Booking::COMPLETED, Booking::NO_SHOW];

    public function __construct(private readonly Availability $availability) {}

    /**
     * @return array{
     *     from: string, to: string, days: int, total: int, counts: array<string, int>, types: list<array{name: string, count: int}>,
     *     trend: array{unit: string, rows: list<array{label: string, made: int, held: int}>}, utilisation: ?array{percent: int, booked: int, offered: int},
     *     no_show_rate: ?int, tracked: int
     * }
     */
    public function build(BookingBoard $board, Team $team, string $from, string $to, ?int $typeId = null): array
    {
        $zone = BookingSetting::forTeam($team)->zone();
        $first = CarbonImmutable::parse($from, $zone)->startOfDay();
        $last = CarbonImmutable::parse($to, $zone)->startOfDay();
        $days = (int) round($first->diffInDays($last)) + 1;

        $counts = array_fill_keys(Booking::STATUSES, 0);
        foreach ($this->inRange($board, $first, $last, $typeId)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status') as $status => $n) {
            $counts[(string) $status] = (int) $n;
        }

        $types = $this->inRange($board, $first, $last, $typeId)->selectRaw('appointment_type_id, count(*) as n')->groupBy('appointment_type_id')->orderByDesc('n')->limit(8)->pluck('n', 'appointment_type_id');
        $names = AppointmentType::query()->where('team_id', $team->getKey())->whereIn('id', $types->keys())->pluck('name', 'id');

        return [
            'from' => $first->format('Y-m-d'), 'to' => $last->format('Y-m-d'), 'days' => $days,
            'total' => array_sum($counts), 'counts' => $counts,
            'types' => $types->map(fn ($n, $id) => ['name' => (string) ($names[$id] ?? 'Removed type'), 'count' => (int) $n])->values()->all(),
            'trend' => $this->trend($board, $first, $last, $typeId, $zone->getName(), $days),
            'utilisation' => $this->utilisation($board, $team, $first, $last, $typeId),
            'no_show_rate' => $this->noShowRate($counts),
            'tracked' => $counts[Booking::COMPLETED] + $counts[Booking::NO_SHOW],
        ];
    }

    /**
     * One row per booking for the export, oldest first, never more than EXPORT_LIMIT. Only what the business needs to follow up: no reasons, answers or notes.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(BookingBoard $board, Team $team, string $from, string $to, ?int $typeId = null): Generator
    {
        $zone = BookingSetting::forTeam($team)->zone();
        $first = CarbonImmutable::parse($from, $zone)->startOfDay();
        $last = CarbonImmutable::parse($to, $zone)->startOfDay();

        // The ids first (cheap, ordered, capped), then the rows in groups, so the order is kept and the cap is real.
        $ids = $this->inRange($board, $first, $last, $typeId)->orderBy('starts_at')->orderBy('id')->limit(self::EXPORT_LIMIT)->pluck('id')->all();

        foreach (array_chunk($ids, 500) as $group) {
            $rows = Booking::query()->whereIn('id', $group)->with(['appointmentType:id,name', 'provider:id,public_name'])->get()->keyBy('id');

            foreach ($group as $id) {
                $b = $rows->get($id);
                if ($b === null) {
                    continue;
                }

                yield [
                    'reference' => $b->reference, 'status' => $b->status, 'appointment' => $b->appointmentType->name ?? '', 'provider' => $b->provider->public_name ?? 'Not assigned',
                    'starts' => $b->starts_at->copy()->setTimezone($zone)->format('Y-m-d H:i'), 'ends' => $b->ends_at->copy()->setTimezone($zone)->format('Y-m-d H:i'),
                    'minutes' => (int) $b->starts_at->diffInMinutes($b->ends_at), 'created' => $b->created_at?->copy()->setTimezone($zone)->format('Y-m-d H:i') ?? '',
                    'customer' => $b->customer_name, 'email' => $b->customer_email, 'phone' => $b->customer_phone ?? '', 'company' => $b->customer_company ?? '',
                ];
            }
        }
    }

    /** How many bookings a range holds, for the export's own refusal of an empty file. */
    public function count(BookingBoard $board, Team $team, string $from, string $to, ?int $typeId = null): int
    {
        $zone = BookingSetting::forTeam($team)->zone();

        return min(self::EXPORT_LIMIT, $this->inRange($board, CarbonImmutable::parse($from, $zone)->startOfDay(), CarbonImmutable::parse($to, $zone)->startOfDay(), $typeId)->count());
    }

    /**
     * @return Builder<Booking>
     */
    private function inRange(BookingBoard $board, CarbonImmutable $first, CarbonImmutable $last, ?int $typeId): Builder
    {
        $query = $board->query()->where('starts_at', '>=', $first->utc())->where('starts_at', '<', $last->addDay()->startOfDay()->utc());

        return $typeId === null ? $query : $query->where('appointment_type_id', $typeId);
    }

    /**
     * Appointments by day (or by week for a long range), counted two ways: requests made in the period, and appointments that fall in it (those that
     * took time from the calendar).
     *
     * @return array{unit: string, rows: list<array{label: string, made: int, held: int}>}
     */
    private function trend(BookingBoard $board, CarbonImmutable $first, CarbonImmutable $last, ?int $typeId, string $zone, int $days): array
    {
        $weekly = $days > 62;
        $key = fn (CarbonImmutable $d): string => ($weekly ? $d->startOfWeek() : $d)->format('Y-m-d');

        $rows = [];
        for ($d = $first; $d->lte($last); $d = $d->addDay()) {
            $rows[$key($d)] ??= ['label' => $key($d), 'made' => 0, 'held' => 0];
        }

        $held = $this->inRange($board, $first, $last, $typeId)->whereIn('status', self::TOOK_TIME)->select(['id', 'starts_at']);
        foreach ($held->lazyById(1000) as $b) {
            $rows[$key(CarbonImmutable::instance($b->starts_at)->setTimezone($zone)->startOfDay())]['held']++;
        }

        $made = $board->query()->where('created_at', '>=', $first->utc())->where('created_at', '<', $last->addDay()->startOfDay()->utc())->select(['id', 'created_at']);
        if ($typeId !== null) {
            $made->where('appointment_type_id', $typeId);
        }
        foreach ($made->lazyById(1000) as $b) {
            $k = $key(CarbonImmutable::instance($b->created_at)->setTimezone($zone)->startOfDay());
            if (isset($rows[$k])) {
                $rows[$k]['made']++;
            }
        }

        return ['unit' => $weekly ? 'week' : 'day', 'rows' => array_values($rows)];
    }

    /**
     * Booked time against offered time. Null where the comparison would not mean anything: nothing offered, a single appointment type chosen (the offered
     * time is shared between types), or a view of the unassigned ones.
     *
     * @return array{percent: int, booked: int, offered: int}|null
     */
    private function utilisation(BookingBoard $board, Team $team, CarbonImmutable $first, CarbonImmutable $last, ?int $typeId): ?array
    {
        $providers = $board->capacityProviderIds();
        if ($typeId !== null || $providers === []) {
            return null;
        }

        $offered = $this->availability->capacityMinutes($team, $first->format('Y-m-d'), $last->format('Y-m-d'), $providers);
        if ($offered <= 0) {
            return null;
        }

        $booked = 0;
        foreach ($this->inRange($board, $first, $last, null)->whereIn('status', self::TOOK_TIME)->select(['id', 'starts_at', 'ends_at'])->lazyById(1000) as $b) {
            $booked += (int) $b->starts_at->diffInMinutes($b->ends_at);
        }

        return ['percent' => (int) min(100, round($booked / $offered * 100)), 'booked' => $booked, 'offered' => $offered];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function noShowRate(array $counts): ?int
    {
        $tracked = $counts[Booking::COMPLETED] + $counts[Booking::NO_SHOW];

        return $tracked > 0 ? (int) round($counts[Booking::NO_SHOW] / $tracked * 100) : null;
    }
}

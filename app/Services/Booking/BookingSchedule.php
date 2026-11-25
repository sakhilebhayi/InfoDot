<?php

namespace App\Services\Booking;

use App\Models\BookingClosure;
use App\Models\BookingProvider;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves working hours and closures for the setup screens (R-63) and writes the audit trail. Hours are local times in the business's zone; closures
 * are stored as UTC instants worked out from the business's zone, end exclusive.
 */
class BookingSchedule
{
    public const MAX_STRETCHES_PER_DAY = 6;

    public const MAX_CLOSURE_DAYS = 366;

    public function __construct(private readonly BookingAudit $audit) {}

    /**
     * Read and check the submitted week. Rows with both times blank are ignored.
     *
     * @param  mixed  $input  the `hours` field: weekday => list of {from, to}
     * @return list<array{weekday: int, starts_at: string, ends_at: string}>
     *
     * @throws ValidationException
     */
    public function parseWeek(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $rows = [];
        $errors = [];

        foreach (WorkingHour::DAYS as $weekday => $dayName) {
            $stretches = [];
            $day = is_array($input[$weekday] ?? null) ? $input[$weekday] : [];

            foreach ($day as $row) {
                $from = is_array($row) ? trim((string) ($row['from'] ?? '')) : '';
                $to = is_array($row) ? trim((string) ($row['to'] ?? '')) : '';

                if ($from === '' && $to === '') {
                    continue;
                }

                if (! $this->isTime($from) || ! $this->isTime($to)) {
                    $errors[] = "{$dayName}: give both a start and an end time, like 08:30.";

                    continue;
                }

                if ($to <= $from) {
                    $errors[] = "{$dayName}: {$from} to {$to} ends before it starts. A stretch must end the same day, after it starts.";

                    continue;
                }

                $stretches[] = ['from' => $from, 'to' => $to];
            }

            if (count($stretches) > self::MAX_STRETCHES_PER_DAY) {
                $errors[] = "{$dayName}: at most ".self::MAX_STRETCHES_PER_DAY.' stretches a day.';

                continue;
            }

            usort($stretches, fn (array $a, array $b) => strcmp($a['from'], $b['from']));

            foreach ($stretches as $i => $s) {
                if ($i > 0 && $s['from'] < $stretches[$i - 1]['to']) {
                    $errors[] = "{$dayName}: {$stretches[$i - 1]['from']} to {$stretches[$i - 1]['to']} overlaps {$s['from']} to {$s['to']}.";

                    continue 2;
                }

                $rows[] = ['weekday' => $weekday, 'starts_at' => $s['from'], 'ends_at' => $s['to']];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['hours' => $errors]);
        }

        return $rows;
    }

    /**
     * Replace one scope's week (the business's own when $provider is null) in one transaction, with one audit event.
     *
     * @param  list<array{weekday: int, starts_at: string, ends_at: string}>  $rows
     */
    public function replaceWeek(Team $team, User $actor, ?BookingProvider $provider, array $rows): void
    {
        DB::transaction(function () use ($team, $actor, $provider, $rows) {
            WorkingHour::query()->where('team_id', $team->getKey())->where('booking_provider_id', $provider?->getKey())->delete();

            foreach ($rows as $row) {
                WorkingHour::forceCreate($row + ['team_id' => $team->getKey(), 'booking_provider_id' => $provider?->getKey()]);
            }

            $this->audit->record($team, $actor, 'working_hours_replaced', [
                'scope' => $provider === null ? 'business' : 'provider', 'provider_id' => $provider?->getKey(), 'stretches' => count($rows),
            ]);
        });
    }

    /**
     * Work out the UTC instants of a closure from what the form sent, in the business's zone.
     *
     * @param  array{kind: string, starts_on: string, ends_on?: ?string, from?: ?string, to?: ?string}  $data
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     *
     * @throws ValidationException
     */
    public function closureInstants(BookingSetting $setting, array $data): array
    {
        $tz = $setting->zone();
        $startDay = CarbonImmutable::createFromFormat('!Y-m-d', $data['starts_on'], $tz);
        $endDay = CarbonImmutable::createFromFormat('!Y-m-d', ($data['ends_on'] ?? null) ?: $data['starts_on'], $tz);

        if ($data['kind'] === BookingClosure::HOLIDAY) {
            $start = $startDay->startOfDay();
            $end = $endDay->addDay()->startOfDay();   // the end day is included, so the instant is the start of the next day
        } else {
            $from = (string) ($data['from'] ?? '');
            $to = (string) ($data['to'] ?? '');

            if (! $this->isTime($from) || ! $this->isTime($to)) {
                throw ValidationException::withMessages(['from' => 'Give the time the block starts and ends, like 08:30.']);
            }

            $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $startDay->format('Y-m-d').' '.$from, $tz);
            $end = CarbonImmutable::createFromFormat('!Y-m-d H:i', $endDay->format('Y-m-d').' '.$to, $tz);
        }

        if ($end->lte($start)) {
            throw ValidationException::withMessages(['to' => 'The end must be after the start.']);
        }

        if ($startDay->diffInDays($endDay) >= self::MAX_CLOSURE_DAYS) {
            throw ValidationException::withMessages(['ends_on' => 'A closure can cover at most '.self::MAX_CLOSURE_DAYS.' days.']);
        }

        if ($end->utc()->lte(now())) {
            throw ValidationException::withMessages(['starts_on' => 'That time has already passed.']);
        }

        return [$start->utc(), $end->utc()];
    }

    /** @param  array{kind: string, starts_on: string, ends_on?: ?string, from?: ?string, to?: ?string, reason?: ?string}  $data */
    public function addClosure(Team $team, User $actor, ?BookingProvider $provider, array $data): BookingClosure
    {
        [$start, $end] = $this->closureInstants(BookingSetting::forTeam($team), $data);

        return DB::transaction(function () use ($team, $actor, $provider, $data, $start, $end) {
            $closure = BookingClosure::forceCreate([
                'team_id' => $team->getKey(), 'booking_provider_id' => $provider?->getKey(), 'kind' => $data['kind'],
                'starts_at' => $start, 'ends_at' => $end, 'reason' => filled($data['reason'] ?? null) ? trim($data['reason']) : null,
            ]);

            $this->audit->record($team, $actor, 'closure_added', [
                'closure_id' => $closure->getKey(), 'kind' => $closure->kind, 'provider_id' => $provider?->getKey(),
                'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(),
            ]);

            return $closure;
        });
    }

    public function removeClosure(Team $team, User $actor, BookingClosure $closure): void
    {
        DB::transaction(function () use ($team, $actor, $closure) {
            $this->audit->record($team, $actor, 'closure_removed', [
                'closure_id' => $closure->getKey(), 'kind' => $closure->kind, 'provider_id' => $closure->booking_provider_id,
                'starts_at' => $closure->starts_at->toIso8601String(), 'ends_at' => $closure->ends_at->toIso8601String(),
            ]);
            $closure->delete();
        });
    }

    private function isTime(string $value): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value);
    }
}

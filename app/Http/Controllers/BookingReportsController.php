<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsBooking;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Services\Booking\BookingAudit;
use App\Services\Booking\BookingBoard;
use App\Services\Booking\BookingCsv;
use App\Services\Booking\BookingReport;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The booking reports and their export (R-63 phase 6). Reports follow the appointments screens' visibility (a provider sees only their own); the
 * export carries customers' contact details, so it is for people who manage the business only, and the audit trail records that it happened
 * (range and count, never who was in it).
 */
class BookingReportsController extends Controller
{
    use GuardsBooking;

    private const MAX_DAYS = 366;

    public function __construct(private readonly BookingReport $report, private readonly BookingAudit $audit) {}

    public function show(Request $request, Team $team): View
    {
        $board = $this->board($request, $team);
        [$from, $to, $typeId, $zone, $clamped] = $this->range($request, $team);

        return view('business.appointments.reports', [
            'tab' => 'reports', 'team' => $team, 'zone' => $zone, 'board' => $board, 'pending' => $board->pendingCount(),
            'providers' => $board->manages() ? $team->bookingProviders()->orderBy('public_name')->get(['id', 'public_name']) : collect(),
            'types' => $team->appointmentTypes()->orderBy('name')->get(['id', 'name']),
            'typeId' => $typeId, 'clamped' => $clamped, 'report' => $this->report->build($board, $team, $from, $to, $typeId),
            'maxDays' => self::MAX_DAYS,
        ]);
    }

    public function export(Request $request, Team $team): StreamedResponse
    {
        $board = $this->board($request, $team);
        abort_unless($board->manages(), 403);
        [$from, $to, $typeId] = $this->range($request, $team);

        $count = $this->report->count($board, $team, $from, $to, $typeId);
        $this->audit->record($team, $this->user(), 'bookings_exported', ['from' => $from, 'to' => $to, 'count' => $count, 'provider' => $board->filter(), 'type' => $typeId]);

        return response()->streamDownload(function () use ($board, $team, $from, $to, $typeId): void {
            $out = fopen('php://output', 'w');
            BookingCsv::write($out, $this->report->rows($board, $team, $from, $to, $typeId));
            fclose($out);
        }, 'appointments-'.$from.'-to-'.$to.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function board(Request $request, Team $team): BookingBoard
    {
        $this->featureOpen();
        $provider = $request->query('provider');
        $board = BookingBoard::for($this->user(), $team, is_string($provider) ? $provider : null);
        abort_unless($board->canUse(), 403);

        return $board;
    }

    /**
     * The range asked for, made safe: real dates in the business's zone, never before two years back or after today plus a year, never longer than a year.
     *
     * @return array{0: string, 1: string, 2: ?int, 3: DateTimeZone, 4: bool}
     */
    private function range(Request $request, Team $team): array
    {
        $zone = BookingSetting::forTeam($team)->zone();
        $today = CarbonImmutable::now($zone)->startOfDay();
        $min = $today->subYears(2);
        $max = $today->addYear();

        $to = $this->date($request->query('to'), $zone) ?? $today;
        $from = $this->date($request->query('from'), $zone) ?? $to->subDays(29);
        $clamped = false;

        $clamp = function (CarbonImmutable $d) use ($min, $max, &$clamped): CarbonImmutable {
            $fixed = $d->lt($min) ? $min : ($d->gt($max) ? $max : $d);
            $clamped = $clamped || ! $fixed->equalTo($d);

            return $fixed;
        };
        $from = $clamp($from);
        $to = $clamp($to);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if (round($from->diffInDays($to)) >= self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS - 1);
            $clamped = true;
        }

        $type = $request->query('type');
        $typeId = is_string($type) && ctype_digit($type) && $team->appointmentTypes()->whereKey((int) $type)->exists() ? (int) $type : null;

        return [$from->format('Y-m-d'), $to->format('Y-m-d'), $typeId, $zone, $clamped];
    }

    private function date(mixed $value, DateTimeZone $zone): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $zone);

        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $value ? $date : null;
    }
}

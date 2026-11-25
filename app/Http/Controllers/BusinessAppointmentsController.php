<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsBooking;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\User;
use App\Services\Booking\Availability;
use App\Services\Booking\BookingActions;
use App\Services\Booking\BookingBoard;
use App\Services\Booking\BookingRefused;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The business's appointments (R-63 phase 4): the lists, the calendar, one appointment and what is done to it.
 *
 * Who sees what is decided by BookingBoard (the lists, the calendar and the counts) and BookingActions::canManage (one appointment); what may be
 * done is decided by BookingActions, which refuses anything not allowed. This controller only asks them and draws the answer. Order of checks on every
 * route: the feature is open (404), the appointment belongs to this business (404), then the person's permission (403).
 */
class BusinessAppointmentsController extends Controller
{
    /** The most appointments one calendar view loads: a busy month stays cheap, and the rest are a click away on the day view. */
    private const CALENDAR_ROWS = 1500;

    use GuardsBooking;

    private const START_FORMAT = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';

    private const PER_PAGE = 25;

    /** Plain words for the audit trail. */
    public const EVENT_WORDS = [
        'booking_created' => 'Booked by the customer',
        'booking_accepted' => 'Accepted',
        'booking_rejected' => 'Rejected',
        'booking_cancelled_by_business' => 'Cancelled by the business',
        'booking_cancelled_by_customer' => 'Cancelled by the customer',
        'booking_completed' => 'Marked as done',
        'booking_no_show' => 'Marked as a no-show',
        'booking_rescheduled' => 'Moved to another time',
        'booking_provider_assigned' => 'Handed to another provider',
        'booking_note_updated' => 'Internal note changed',
        'booking_reschedule_requested' => 'Customer asked to move it',
        'booking_reschedule_declined' => 'Move request declined',
    ];

    public function __construct(private readonly BookingActions $actions, private readonly Availability $availability) {}

    // ------------------------------------------------------------------------------------------------------------------------------ lists

    public function requests(Request $request, Team $team): View
    {
        return $this->list($request, $team, 'requests', fn ($q) => $q->where('status', Booking::PENDING)->where('starts_at', '>', now())->orderBy('starts_at')->orderBy('id'));
    }

    public function upcoming(Request $request, Team $team): View
    {
        return $this->list($request, $team, 'upcoming', fn ($q) => $q->where('status', Booking::CONFIRMED)->where('starts_at', '>', now())->orderBy('starts_at')->orderBy('id'));
    }

    /** Closed ones, and anything that has started without being settled: a confirmed appointment not yet marked, a request nobody answered. */
    public function past(Request $request, Team $team): View
    {
        return $this->list($request, $team, 'past', fn ($q) => $q->where(fn ($w) => $w
            ->whereNotIn('status', Booking::HOLDING)
            ->orWhere('starts_at', '<=', now()))->orderByDesc('starts_at')->orderByDesc('id'));
    }

    /** @param  Closure(Builder<Booking>): mixed  $shape */
    private function list(Request $request, Team $team, string $tab, Closure $shape): View
    {
        $board = $this->board($request, $team);
        $query = $board->query();
        $shape($query);
        $rows = $query->with(['appointmentType:id,name,team_id', 'provider:id,public_name'])->paginate(self::PER_PAGE)->withQueryString();

        return view('business.appointments.list', ['tab' => $tab] + $this->frame($team, $board) + ['rows' => $rows]);
    }

    // ------------------------------------------------------------------------------------------------------------------------------ calendar

    public function calendar(Request $request, Team $team): View
    {
        $board = $this->board($request, $team);
        $zone = BookingSetting::forTeam($team)->zone();
        $today = CarbonImmutable::now($zone)->startOfDay();

        $view = in_array($request->query('view'), ['day', 'week', 'month'], true) ? (string) $request->query('view') : 'week';
        $min = $today->subYears(2);
        $max = $today->addYears(2);
        $date = $this->date($request->query('date'), $zone) ?? $today;
        $date = $date->lt($min) ? $min : ($date->gt($max) ? $max : $date);

        [$from, $to] = match ($view) {
            'day' => [$date, $date->addDay()],
            'week' => [$date->startOfWeek(), $date->startOfWeek()->addDays(7)],
            default => [$date->startOfMonth(), $date->startOfMonth()->addMonthNoOverflow()],
        };

        $showClosed = $request->boolean('closed');
        $query = $board->query()->where('starts_at', '>=', $from->utc())->where('starts_at', '<', $to->utc());
        if (! $showClosed) {
            $query->whereIn('status', Booking::HOLDING);
        }
        $bookings = $query->with(['appointmentType:id,name,team_id', 'provider:id,public_name'])->orderBy('starts_at')->orderBy('id')->limit(self::CALENDAR_ROWS)->get();
        $closures = $board->closures($from->utc(), $to->utc());

        $days = [];
        for ($d = $from; $d->lt($to); $d = $d->addDay()) {
            $end = $d->addDay();
            $days[$d->format('Y-m-d')] = [
                'date' => $d,
                'bookings' => $bookings->filter(fn (Booking $b) => $b->starts_at->gte($d->utc()) && $b->starts_at->lt($end->utc()))->values(),
                'closures' => $closures->filter(fn ($c) => $c->starts_at->lt($end->utc()) && $c->ends_at->gt($d->utc()))->map(fn ($c) => [
                    'label' => $c->kind === 'holiday' ? 'Closed' : 'Blocked '.$c->starts_at->copy()->setTimezone($zone)->max($d)->format('H:i').' to '.$c->ends_at->copy()->setTimezone($zone)->min($end)->format('H:i'),
                    'who' => data_get($c, 'provider.public_name', 'whole business'),
                    'reason' => $c->reason,
                ])->values()->all(),
            ];
        }

        $keep = array_filter(['provider' => $board->filter(), 'closed' => $showClosed ? '1' : null]);
        $url = fn (string $v, CarbonImmutable $on) => route('business.appointments.calendar', ['team' => $team, 'view' => $v, 'date' => $on->format('Y-m-d')] + $keep);

        [$prev, $next] = match ($view) {
            'day' => [$date->subDay(), $date->addDay()],
            'week' => [$date->subWeek(), $date->addWeek()],
            default => [$date->startOfMonth()->subMonthNoOverflow(), $date->startOfMonth()->addMonthNoOverflow()],
        };

        return view('business.appointments.calendar', $this->frame($team, $board) + [
            'tab' => 'calendar', 'view' => $view, 'date' => $date, 'days' => $days, 'zone' => $zone, 'showClosed' => $showClosed, 'today' => $today,
            'prevUrl' => $prev->gte($view === 'month' ? $min->startOfMonth() : $min) ? $url($view, $prev) : null, 'nextUrl' => $next->lte($view === 'month' ? $max->endOfMonth() : $max) ? $url($view, $next) : null,
            'todayUrl' => $url($view, $today), 'url' => $url, 'keep' => $keep,
            'closedUrl' => route('business.appointments.calendar', ['team' => $team, 'view' => $view, 'date' => $date->format('Y-m-d')] + array_filter(['provider' => $board->filter()]) + ($showClosed ? [] : ['closed' => '1'])),
            'leading' => $view === 'month' ? $from->dayOfWeekIso - 1 : 0,
        ]);
    }

    // ------------------------------------------------------------------------------------------------------------------------------ one appointment

    public function show(Request $request, Team $team, Booking $booking): View
    {
        $this->mine($team, $booking);
        $zone = BookingSetting::forTeam($team)->zone();
        $manages = Gate::allows('manageBookings', $team);
        $booking->load(['appointmentType', 'provider:id,public_name']);
        $type = $booking->appointmentType;
        $holding = in_array($booking->status, Booking::HOLDING, true);
        $started = $booking->starts_at->lte(now());

        $events = BookingEvent::query()->where('team_id', $team->getKey())->where('booking_id', $booking->getKey())->with('actor:id,name')->orderBy('created_at')->orderBy('id')->get();

        $move = $this->date($request->query('move'), $zone);
        $slots = collect();
        if ($move !== null && $holding && ! $started) {
            $slots = $this->availability->slots($type, $move->format('Y-m-d'), $booking->booking_provider_id, null, $booking->getKey(), false, true)
                ->map(fn ($slot) => ['stamp' => $slot->start->utc()->format('Y-m-d\TH:i:s\Z'), 'local' => $slot->start->setTimezone($zone)]);
        }

        return view('business.appointments.show', [
            'team' => $team, 'booking' => $booking, 'type' => $type, 'zone' => $zone, 'events' => $events, 'manages' => $manages,
            'can' => [
                'accept' => $booking->status === Booking::PENDING && ! $started,
                'reject' => $booking->status === Booking::PENDING,
                'cancel' => $holding && ! $started,
                'finish' => $booking->status === Booking::CONFIRMED && $started,
                'move' => $holding && ! $started,
                'assign' => $manages && $holding && ! $started,
                'decline' => $booking->reschedule_requested_at !== null && $holding,
            ],
            'providers' => $manages ? $type->providers()->active()->orderBy('public_name')->get() : collect(),
            'move' => $move, 'slots' => $slots, 'words' => self::EVENT_WORDS,
        ]);
    }

    // ------------------------------------------------------------------------------------------------------------------------------ actions

    public function accept(Team $team, Booking $booking): RedirectResponse
    {
        return $this->run($team, $booking, 'Accepted. The customer will see it on their booking page.', fn ($b, $u) => $this->actions->accept($b, $u));
    }

    public function reject(Request $request, Team $team, Booking $booking): RedirectResponse
    {
        $this->mine($team, $booking);
        $note = $this->reason($request);

        return $this->run($team, $booking, 'Rejected. The customer will see it on their booking page.', fn ($b, $u) => $this->actions->reject($b, $u, $note));
    }

    public function cancel(Request $request, Team $team, Booking $booking): RedirectResponse
    {
        $this->mine($team, $booking);
        $note = $this->reason($request);

        return $this->run($team, $booking, 'Cancelled. The time is free again.', fn ($b, $u) => $this->actions->cancel($b, $u, $note));
    }

    public function complete(Team $team, Booking $booking): RedirectResponse
    {
        return $this->run($team, $booking, 'Marked as done.', fn ($b, $u) => $this->actions->complete($b, $u));
    }

    public function noShow(Team $team, Booking $booking): RedirectResponse
    {
        return $this->run($team, $booking, 'Marked as a no-show.', fn ($b, $u) => $this->actions->noShow($b, $u));
    }

    public function reschedule(Request $request, Team $team, Booking $booking): RedirectResponse
    {
        $this->mine($team, $booking);
        $data = $request->validate(['start' => ['required', 'string', 'regex:'.self::START_FORMAT]]);
        $start = $this->instant($data['start']);
        abort_if($start === null, 404);

        return $this->run($team, $booking, 'Moved to the new time.', fn ($b, $u) => $this->actions->reschedule($b, $u, $start));
    }

    public function assign(Request $request, Team $team, Booking $booking): RedirectResponse
    {
        $this->mine($team, $booking);
        $data = $request->validate(['provider' => ['required', 'string', 'max:20']]);
        $providerId = null;

        if ($data['provider'] !== 'any') {
            $valid = ctype_digit($data['provider']) && $booking->appointmentType->providers()->active()->whereKey((int) $data['provider'])->exists();
            abort_unless($valid, 422);
            $providerId = (int) $data['provider'];
        }

        return $this->run($team, $booking, 'Handed over.', fn ($b, $u) => $this->actions->assign($b, $u, $providerId));
    }

    public function note(Request $request, Team $team, Booking $booking): RedirectResponse
    {
        $this->mine($team, $booking);
        $data = $request->validate(['internal_notes' => ['nullable', 'string', 'max:2000']]);

        return $this->run($team, $booking, 'Note saved. Only your team can see it.', fn ($b, $u) => $this->actions->note($b, $u, $data['internal_notes'] ?? null));
    }

    public function declineReschedule(Request $request, Team $team, Booking $booking): RedirectResponse
    {
        $this->mine($team, $booking);
        $note = $this->reason($request);

        return $this->run($team, $booking, 'The request to move is declined. The appointment stays as it is.', fn ($b, $u) => $this->actions->declineReschedule($b, $u, $note));
    }

    // ------------------------------------------------------------------------------------------------------------------------------ helpers

    /** Feature, then the business's own record, then the person's right to this one. */
    private function mine(Team $team, Booking $booking): void
    {
        $this->featureOpen();
        $this->belongs($team, $booking);
        abort_unless(BookingActions::canManage($this->user(), $booking), 403);
    }

    /** @param  Closure(Booking, User): mixed  $do */
    private function run(Team $team, Booking $booking, string $done, Closure $do): RedirectResponse
    {
        $this->mine($team, $booking);

        try {
            $do($booking, $this->user());
        } catch (BookingRefused $refused) {
            abort_if($refused->reason === BookingRefused::NOT_ALLOWED, 403);

            return redirect()->route('business.appointments.show', [$team, $booking])->withErrors(['action' => $refused->getMessage()]);
        }

        return redirect()->route('business.appointments.show', [$team, $booking])->with('status', $done);
    }

    private function reason(Request $request): ?string
    {
        return $request->validate(['note' => ['nullable', 'string', 'max:300']])['note'] ?? null;
    }

    private function board(Request $request, Team $team): BookingBoard
    {
        $this->featureOpen();
        $provider = $request->query('provider');
        $board = BookingBoard::for($this->user(), $team, is_string($provider) ? $provider : null);
        abort_unless($board->canUse(), 403);

        return $board;
    }

    /** What every appointments screen needs around its list. @return array<string, mixed> */
    private function frame(Team $team, BookingBoard $board): array
    {
        return [
            'team' => $team, 'zone' => BookingSetting::forTeam($team)->zone(), 'board' => $board, 'pending' => $board->pendingCount(),
            'providers' => $board->manages() ? $team->bookingProviders()->orderBy('public_name')->get(['id', 'public_name']) : collect(),
        ];
    }

    /** A real date (Y-m-d) in the business's zone, or null. */
    private function date(mixed $value, DateTimeZone $zone): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $zone);

        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $value ? $date : null;
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
}

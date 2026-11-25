<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardsBooking;
use App\Models\AppointmentType;
use App\Models\BookingSetting;
use App\Models\Team;
use App\Models\WorkingHour;
use App\Services\Booking\BookingAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The appointment types a business offers (R-63 phase 2b): length, spacing, buffers, an optional fee shown as information, format, who offers it.
 * Owner, administrators and managers only. A type with bookings is switched off, never deleted.
 */
class BookingTypesController extends Controller
{
    use GuardsBooking;

    /** The approval choices as the form shows them, and what each stores in `requires_approval`. */
    private const APPROVAL = ['follow' => null, 'always' => true, 'auto' => false];

    private const TEXT_FIELDS = ['description', 'location_note', 'preparation'];

    public function __construct(private readonly BookingAudit $audit) {}

    public function index(Team $team): View
    {
        $this->manage($team);

        return view('business.booking.types', [
            'team' => $team,
            'types' => $team->appointmentTypes()->with('providers:id,public_name')->orderBy('sort')->orderBy('name')->get(),
            'hasHours' => WorkingHour::query()->where('team_id', $team->getKey())->exists(),
        ]);
    }

    public function create(Team $team): View
    {
        $this->manage($team);

        return $this->form($team, new AppointmentType(['duration_minutes' => 30, 'slot_interval_minutes' => 30, 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0, 'currency' => 'ZAR', 'meeting_format' => 'in_person', 'active' => true, 'sort' => 0]));
    }

    public function edit(Team $team, AppointmentType $type): View
    {
        $this->manage($team);
        $this->belongs($team, $type);

        return $this->form($team, $type);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->manage($team);
        $values = $this->values($request, $team);

        $type = DB::transaction(function () use ($team, $values) {
            $type = new AppointmentType($values['fields']);
            $type->team_id = $team->getKey();
            $type->save();
            $type->providers()->sync($values['providers']);
            $this->audit->record($team, $this->user(), 'appointment_type_created', ['type_id' => $type->getKey(), 'name' => $type->name, 'providers' => $values['providers']]);

            return $type;
        });

        return redirect()->route('business.booking.types', $team)->with('status', "{$type->name} is added.");
    }

    public function update(Request $request, Team $team, AppointmentType $type): RedirectResponse
    {
        $this->manage($team);
        $this->belongs($team, $type);
        $values = $this->values($request, $team, $type);

        DB::transaction(function () use ($team, $type, $values) {
            $before = $type->only(array_keys($values['fields']));
            $oldProviders = $type->providers()->pluck('booking_providers.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $type->fill($values['fields'])->save();
            $type->providers()->sync($values['providers']);

            $changes = $this->audit->changes($before, $values['fields'], self::TEXT_FIELDS);
            $newProviders = collect($values['providers'])->sort()->values()->all();

            if ($oldProviders !== $newProviders) {
                $changes['providers'] = ['from' => $oldProviders, 'to' => $newProviders];
            }

            if ($changes !== []) {
                $this->audit->record($team, $this->user(), 'appointment_type_updated', ['type_id' => $type->getKey(), 'name' => $type->name, 'changes' => $changes]);
            }
        });

        return redirect()->route('business.booking.types', $team)->with('status', "{$type->name} is saved.");
    }

    public function toggle(Team $team, AppointmentType $type): RedirectResponse
    {
        $this->manage($team);
        $this->belongs($team, $type);

        $type->forceFill(['active' => ! $type->active])->save();
        $this->audit->record($team, $this->user(), $type->active ? 'appointment_type_switched_on' : 'appointment_type_switched_off', ['type_id' => $type->getKey(), 'name' => $type->name]);

        return redirect()->route('business.booking.types', $team)->with('status', $type->active ? "{$type->name} is on: customers can book it." : "{$type->name} is off: customers cannot book it.");
    }

    public function destroy(Team $team, AppointmentType $type): RedirectResponse
    {
        $this->manage($team);
        $this->belongs($team, $type);

        if ($type->bookings()->exists()) {
            return redirect()->route('business.booking.types', $team)->withErrors(['type' => "{$type->name} has appointments, so it cannot be deleted. Switch it off instead; the appointments stay on record."]);
        }

        DB::transaction(function () use ($team, $type) {
            $this->audit->record($team, $this->user(), 'appointment_type_deleted', ['type_id' => $type->getKey(), 'name' => $type->name]);
            $type->delete();
        });

        return redirect()->route('business.booking.types', $team)->with('status', "{$type->name} is deleted.");
    }

    private function form(Team $team, AppointmentType $type): View
    {
        return view('business.booking.type-form', [
            'team' => $team,
            'type' => $type,
            'providers' => $team->bookingProviders()->orderBy('public_name')->get(),
            'linked' => $type->exists ? $type->providers()->pluck('booking_providers.id')->map(fn ($id) => (int) $id)->all() : [],
            'approval' => $type->requires_approval === null ? 'follow' : ($type->requires_approval ? 'always' : 'auto'),
            'currencies' => config('booking.currencies'),
            'approvalMode' => BookingSetting::forTeam($team)->approval_mode,
        ]);
    }

    /**
     * Validate the form and turn it into what is stored.
     *
     * @return array{fields: array<string, mixed>, providers: list<int>}
     */
    private function values(Request $request, Team $team, ?AppointmentType $type = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'min:'.AppointmentType::MIN_DURATION, 'max:'.AppointmentType::MAX_DURATION],
            'slot_interval_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'buffer_before_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'buffer_after_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'fee' => ['nullable', 'string', 'max:20'],
            'currency' => ['required', Rule::in(array_keys(config('booking.currencies')))],
            'meeting_format' => ['required', Rule::in(array_keys(AppointmentType::FORMATS))],
            'location_note' => ['nullable', 'string', 'max:300'],
            'preparation' => ['nullable', 'string', 'max:1500'],
            'approval' => ['required', Rule::in(array_keys(self::APPROVAL))],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'providers' => ['nullable', 'array', 'max:200'],
            'providers.*' => ['integer', Rule::exists('booking_providers', 'id')->where('team_id', $team->getKey())],
        ], [
            'duration_minutes.min' => 'An appointment must be at least '.AppointmentType::MIN_DURATION.' minutes long.',
            'duration_minutes.max' => 'An appointment can be at most '.AppointmentType::MAX_DURATION.' minutes (8 hours).',
            'slot_interval_minutes.min' => 'Start times can be at most every 5 minutes apart or less often.',
            'slot_interval_minutes.max' => 'Start times can be at most 4 hours (240 minutes) apart.',
            'providers.*.exists' => 'Choose people from this business only.',
        ]);

        $allowed = $team->bookingProviders()->active()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $picked = collect($data['providers'] ?? [])->map(fn ($id) => (int) $id);

        // A person who was switched off after being linked stays linked until someone unlinks them deliberately; a new link needs an active person.
        $existing = $type?->providers()->pluck('booking_providers.id')->map(fn ($id) => (int) $id)->all() ?? [];
        $bad = $picked->reject(fn (int $id) => in_array($id, $allowed, true) || in_array($id, $existing, true));

        if ($bad->isNotEmpty()) {
            throw ValidationException::withMessages(['providers' => 'Only people who are switched on can be given appointments.']);
        }

        return [
            'fields' => [
                'name' => trim($data['name']),
                'description' => $this->text($data['description'] ?? null),
                'duration_minutes' => (int) $data['duration_minutes'],
                'slot_interval_minutes' => (int) $data['slot_interval_minutes'],
                'buffer_before_minutes' => (int) $data['buffer_before_minutes'],
                'buffer_after_minutes' => (int) $data['buffer_after_minutes'],
                'fee_cents' => $this->cents($data['fee'] ?? null),
                'currency' => $data['currency'],
                'meeting_format' => $data['meeting_format'],
                'location_note' => $this->text($data['location_note'] ?? null),
                'preparation' => $this->text($data['preparation'] ?? null),
                'requires_approval' => self::APPROVAL[$data['approval']],
                'sort' => (int) ($data['sort'] ?? 0),
                'active' => $request->has('active') ? $request->boolean('active') : ($type->active ?? true),
            ],
            'providers' => $picked->unique()->values()->all(),
        ];
    }

    private function text(?string $value): ?string
    {
        return filled($value) ? trim($value) : null;
    }

    /** "250", "250.5" or "250,50" to cents; blank means no fee. */
    private function cents(?string $fee): ?int
    {
        $fee = trim((string) $fee);

        if ($fee === '') {
            return null;
        }

        $fee = str_replace([' ', ','], ['', '.'], $fee);

        if (! preg_match('/^\d{1,7}(\.\d{1,2})?$/', $fee) || (float) $fee > 1000000) {
            throw ValidationException::withMessages(['fee' => 'Give the fee as an amount from 0 to 1 000 000, like 250 or 250.50, or leave it blank for no fee.']);
        }

        return (int) round(((float) $fee) * 100);
    }
}

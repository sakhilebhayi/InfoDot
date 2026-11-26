<?php

namespace App\Http\Requests\Booking;

use App\Models\AppointmentType;
use Illuminate\Validation\Rule;

/**
 * R-72 (Q-20): the appointment-type form (add and edit). BookingFormRequest checks the feature, that the type is this business's,
 * and that the person manages its bookings, in that order, before the form is validated.
 */
class AppointmentTypeRequest extends BookingFormRequest
{
    /** How "Confirmation" maps onto the stored setting: follow the business, always ask, or confirm automatically. */
    public const APPROVAL = ['follow' => null, 'always' => true, 'auto' => false];

    protected array $owned = ['type'];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
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
            'providers.*' => ['integer', Rule::exists('booking_providers', 'id')->where('team_id', $this->team()->getKey())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'duration_minutes.min' => 'An appointment must be at least '.AppointmentType::MIN_DURATION.' minutes long.',
            'duration_minutes.max' => 'An appointment can be at most '.AppointmentType::MAX_DURATION.' minutes (8 hours).',
            'slot_interval_minutes.min' => 'Start times can be at most every 5 minutes apart or less often.',
            'slot_interval_minutes.max' => 'Start times can be at most 4 hours (240 minutes) apart.',
            'providers.*.exists' => 'Choose people from this business only.',
        ];
    }
}

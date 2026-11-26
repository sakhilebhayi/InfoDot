<?php

namespace App\Http\Requests\Booking;

use App\Models\BookingSetting;
use DateTimeZone;
use Illuminate\Validation\Rule;

/** R-72 (Q-20): the business's booking settings form. */
class BookingSettingsRequest extends BookingFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'calendar_published' => ['sometimes', 'boolean'],
            'approval_mode' => ['required', Rule::in(BookingSetting::APPROVAL_MODES)],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'min_notice_hours' => ['required', 'integer', 'min:0', 'max:336'],
            'horizon_days' => ['required', 'integer', 'min:1', 'max:365'],
            'cancel_cutoff_hours' => ['required', 'integer', 'min:0', 'max:336'],
            'instructions' => ['nullable', 'string', 'max:1500'],
            'terms' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'timezone.in' => 'Choose a time zone from the list.',
            'min_notice_hours.max' => 'Notice can be at most 14 days (336 hours).',
            'horizon_days.max' => 'Customers can book at most a year ahead.',
        ];
    }
}

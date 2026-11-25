<?php

namespace App\Services\Privacy;

use App\Models\Booking;
use App\Models\BookingProvider;
use App\Models\User;

/**
 * Everything the appointment booking holds about one person, for them to keep (POPIA section 23): the appointments they booked with the details they
 * gave, and, if they take appointments for a business, their provider records. Only what is about them: a business's internal notes are the business's
 * own record and are not included.
 */
class BookingDataExporter
{
    private const LIMIT = 2000;

    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        $iso = fn ($v) => $v?->toIso8601String();

        $bookings = Booking::query()->where('customer_user_id', $user->getKey())->with(['team:id,name', 'appointmentType:id,name', 'provider:id,public_name'])->orderBy('id')->limit(self::LIMIT)->get();

        return [
            'person' => $user->email,
            'exported_at' => now()->toIso8601String(),
            'note' => 'Everything InfoDot appointment booking holds about you. A business\'s own notes about an appointment belong to that business and are not included. Businesses remove your details from appointments after '.config('booking.retention_months').' months.',
            'appointments_booked' => $bookings->map(fn (Booking $b) => [
                'reference' => $b->reference, 'business' => $b->team->name ?? null, 'appointment' => $b->appointmentType->name ?? null, 'with' => $b->provider->public_name ?? null,
                'starts_at' => $iso($b->starts_at), 'ends_at' => $iso($b->ends_at), 'status' => $b->status, 'requested_at' => $iso($b->created_at), 'cancelled_at' => $iso($b->cancelled_at),
                'terms_accepted_at' => $iso($b->terms_accepted_at), 'name_given' => $b->customer_name, 'email_given' => $b->customer_email, 'phone_given' => $b->customer_phone,
                'company_given' => $b->customer_company, 'reason_given' => $b->reason, 'answers_given' => $b->answers, 'message_from_business' => $b->status_note, 'asked_to_move_at' => $iso($b->reschedule_requested_at), 'move_note_given' => $b->reschedule_note,
            ])->all(),
            'provider_records' => BookingProvider::query()->where('user_id', $user->getKey())->with('team:id,name')->orderBy('id')->get()
                ->map(fn (BookingProvider $p) => ['business' => $p->team->name ?? null, 'public_name' => $p->public_name, 'active' => (bool) $p->active])->all(),
        ];
    }
}

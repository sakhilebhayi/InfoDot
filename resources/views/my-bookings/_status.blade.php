@php
    $words = [
        'pending' => 'Waiting for '.$booking->team->name.' to confirm',
        'confirmed' => 'Confirmed',
        'rejected' => $booking->team->name.' could not take this appointment',
        'cancelled_by_customer' => 'Cancelled by you',
        'cancelled_by_business' => 'Cancelled by '.$booking->team->name,
        'completed' => 'Completed',
        'no_show' => 'Marked as not attended',
    ];
@endphp
{{ $words[$booking->status] ?? ucfirst(str_replace('_', ' ', $booking->status)) }}

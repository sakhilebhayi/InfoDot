@php
    $map = [
        'pending' => ['Waiting for you', 'open'],
        'confirmed' => ['Confirmed', 'done'],
        'rejected' => ['Rejected', null],
        'cancelled_by_customer' => ['Cancelled by the customer', null],
        'cancelled_by_business' => ['Cancelled by the business', null],
        'completed' => ['Completed', 'done'],
        'no_show' => ['No-show', null],
    ];
    [$words, $tone] = $map[$booking->status] ?? [ucfirst(str_replace('_', ' ', $booking->status)), null];
@endphp
<span class="ix-chip @if ($tone) ix-chip--{{ $tone }} @endif">{{ $words }}</span>

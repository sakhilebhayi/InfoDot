@php
    $titles = ['requests' => 'Requests', 'upcoming' => 'Upcoming', 'past' => 'Past and closed'];
    $empty = [
        'requests' => 'No requests are waiting. New ones appear here as customers ask for a time.',
        'upcoming' => 'Nothing confirmed is coming up.',
        'past' => 'No past or closed appointments yet.',
    ];
@endphp
<x-app-layout :title="'Appointments: '.$titles[$tab]">
    @include('business.appointments._head')

    @if ($rows->isEmpty())
        <section class="ix-panel" aria-label="{{ $titles[$tab] }}"><div class="ix-panel__body"><p>{{ $empty[$tab] }}</p></div></section>
    @else
        <section class="ix-panel ix-panel--clip" aria-label="{{ $titles[$tab] }}">
            <div class="ix-tablewrap">
                <table class="ix-table">
                    <thead><tr><th>When</th><th>Reference</th><th>Customer</th><th>Appointment</th><th>Who</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($rows as $b)
                            @php
                                $local = $b->starts_at->copy()->setTimezone($zone);
                                $started = $b->starts_at->lte(now());
                            @endphp
                            <tr>
                                <td class="title"><a href="{{ route('business.appointments.show', [$team, $b]) }}">{{ $local->format('D j M Y, H:i') }}</a></td>
                                <td data-label="Reference" class="muted"><span class="ix-ref">{{ $b->reference }}</span></td>
                                <td data-label="Customer">{{ $b->customer_name }}</td>
                                <td data-label="Appointment">{{ $b->appointmentType->name }}</td>
                                <td data-label="Who" class="muted">{{ $b->provider?->public_name ?? 'Not assigned' }}</td>
                                <td data-label="Status">
                                    @include('business.appointments._status', ['booking' => $b])
                                    @if ($b->reschedule_requested_at && in_array($b->status, \App\Models\Booking::HOLDING, true))<span class="ix-chip ix-chip--info">Asked to move</span>@endif
                                    @if ($b->status === 'confirmed' && $started)<span class="ix-hint">Mark done or no-show</span>@endif
                                    @if ($b->status === 'pending' && $started)<span class="ix-hint">Not answered in time</span>@endif
                                </td>
                                <td>
                                    <div class="ix-actions">
                                        @if ($tab === 'requests')
                                            <form method="post" action="{{ route('business.appointments.accept', [$team, $b]) }}">@csrf<button type="submit" class="ix-btn ix-btn--sm ix-btn--primary">Accept</button></form>
                                            <form method="post" action="{{ route('business.appointments.reject', [$team, $b]) }}" data-confirm="Reject this request? The customer is told it could not be taken." data-confirm-label="Reject">@csrf<button type="submit" class="ix-btn ix-btn--sm ix-btn--danger">Reject</button></form>
                                        @endif
                                        <a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ route('business.appointments.show', [$team, $b]) }}">{{ $b->status === 'confirmed' && $started ? 'Mark done or no-show' : 'Open' }}</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @include('partials.pager', ['pager' => $rows, 'label' => $titles[$tab]])
    @endif
</x-app-layout>

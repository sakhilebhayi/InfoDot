@php
    $manages = \Illuminate\Support\Facades\Gate::allows('manageBookings', $team);
    $ownProvider = $manages ? null : $team->bookingProviders()->where('user_id', auth()->id())->first();
    $tabs = $manages
        ? ['settings' => ['Settings', route('business.booking.settings', $team)], 'types' => ['Appointment types', route('business.booking.types', $team)], 'providers' => ['Providers', route('business.booking.providers', $team)], 'hours' => ['Hours', route('business.booking.hours', $team)], 'closures' => ['Closures', route('business.booking.closures', $team)]]
        : array_filter(['myhours' => $ownProvider ? ['My hours', route('business.booking.providers.hours', [$team, $ownProvider])] : null, 'closures' => $ownProvider ? ['Closures', route('business.booking.closures', $team)] : null]);
@endphp
<nav class="ix-mod__tabs" aria-label="Booking setup">
    @foreach ($tabs as $key => [$label, $url])
        <a href="{{ $url }}" @if (($active ?? '') === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>

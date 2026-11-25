@php
    $zoneName = $zone.' time ('.now($setting->zone())->format('T').')';
    $statusText = ['available' => 'Open', 'full' => 'Full', 'unavailable' => 'Closed', 'outside_window' => '', 'not_offered' => 'Not offered'];
    $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $base = $type ? ['type' => $type->id] : [];
@endphp
<x-app-layout :title="'Book an appointment with '.$team->name">
    @push('meta')<meta name="robots" content="noindex">@endpush

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Book an appointment</h1>
            <p class="ix-lede">
                @if ($logo)<img src="{{ route('attachments.show', $logo) }}" alt="" width="32" height="32" style="vertical-align: middle; border-radius: 4px;"> @endif
                <a href="{{ route('business.show', $team) }}">{{ $team->name }}</a>
            </p>
        </div>
        <div class="ix-actions">
            @auth<a href="{{ route('my-bookings') }}" class="ix-btn ix-btn--ghost">My bookings</a>@endauth
            <a href="{{ route('business.show', $team) }}" class="ix-btn ix-btn--ghost"><span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>Back to the business</a>
        </div>
    </div>

    @if ($errors->has('booking'))
        <div class="ix-error" role="alert"><p>{{ $errors->first('booking') }}</p></div>
    @endif

    @if ($closed)
        <p class="ix-alert" role="status">{{ $closed }}</p>
    @else
        @if ($setting->instructions)
            <section class="ix-panel" aria-label="Booking instructions"><div class="ix-panel__body"><p class="ix-biz__prose">{!! nl2br(e($setting->instructions)) !!}</p></div></section>
        @endif

        <p class="ix-hint">Times are shown in {{ $zoneName }}, the business’s own time zone. Where your device is in another zone, your own time is shown too. Fees are for information only; nothing is charged through InfoDot.</p>

        @if ($memberNote)<p class="ix-alert" role="note">{{ $memberNote }}</p>@endif

        @if ($types->isEmpty())
            <section class="ix-empty"><h2>No appointments to book yet</h2><p>{{ $team->name }} has not set up any appointment types. Please check back later.</p></section>
        @elseif (! $type)
            <section class="ix-panel" aria-label="Appointment types">
                <div class="ix-panel__body">
                    <h2 class="ix-h2">Choose an appointment</h2>
                    @foreach ($types as $t)
                        <article aria-labelledby="type-{{ $t->id }}">
                            <h3 id="type-{{ $t->id }}">{{ $t->name }}</h3>
                            @if ($t->description)<p class="ix-biz__prose">{!! nl2br(e($t->description)) !!}</p>@endif
                            @include('business.book._facts', ['type' => $t])
                            <p><a class="ix-btn ix-btn--primary" href="{{ route('business.book', [$team, 'type' => $t->id]) }}" aria-label="Choose {{ $t->name }}">Choose</a></p>
                        </article>
                        @unless ($loop->last)<hr>@endunless
                    @endforeach
                </div>
            </section>
        @else
            <section class="ix-panel" aria-label="Chosen appointment">
                <div class="ix-panel__body">
                    <h2 class="ix-h2">{{ $type->name }}</h2>
                    @if ($type->description)<p class="ix-biz__prose">{!! nl2br(e($type->description)) !!}</p>@endif
                    @include('business.book._facts', ['type' => $type])
                    @if ($types->count() > 1)<p style="margin-top: 12px;"><a href="{{ route('business.book', $team) }}">Choose a different appointment</a></p>@endif
                </div>
            </section>

            <section class="ix-panel" aria-labelledby="cal-h">
                <div class="ix-panel__body">
                    <h2 class="ix-h2" id="cal-h">{{ $month->format('F Y') }}</h2>
                    <p class="ix-actions">
                        @if ($prevMonth)<a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ route('business.book', [$team] + $base + ['month' => $prevMonth->format('Y-m')]) }}">Earlier month</a>@endif
                        @if ($nextMonth)<a class="ix-btn ix-btn--sm ix-btn--ghost" href="{{ route('business.book', [$team] + $base + ['month' => $nextMonth->format('Y-m')]) }}">Later month</a>@endif
                    </p>
                    <table aria-labelledby="cal-h" style="width: 100%; table-layout: fixed; border-collapse: collapse; text-align: center;">
                        <thead><tr>@foreach ($dayNames as $n)<th scope="col" style="padding: 6px 2px;">{{ $n }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($weeks as $week)
                                <tr>
                                    @foreach ($week as $cell)
                                        @if ($cell === null)
                                            <td style="padding: 6px 2px;"></td>
                                        @else
                                            @php($chosen = $date && $date->format('Y-m-d') === $cell['date'])
                                            <td style="padding: 6px 2px; border: 1px solid var(--ix-rule); @if($cell['status'] === 'outside_window') opacity: .5; @endif" @if($chosen) aria-current="date" @endif>
                                                @if ($cell['status'] === 'available')
                                                    <a href="{{ route('business.book', [$team] + $base + ['date' => $cell['date']]) }}" @if($chosen) style="font-weight: 700;" @endif><b>{{ $cell['day'] }}</b><br><small style="font-size: var(--dot-text-caption-size); text-decoration: underline;">Open</small></a>
                                                @else
                                                    <b>{{ $cell['day'] }}</b><br><small style="font-size: var(--dot-text-caption-size);">{{ $statusText[$cell['status']] }}@if($cell['status'] === 'outside_window')<span class="ix-sr"> not open for booking</span>@endif</small>
                                                @endif
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <ul class="ix-hint">
                        <li><b>Open</b>: has times you can book (a link).</li>
                        <li><b>Full</b>: every time is taken.</li>
                        <li><b>Closed</b>: the business is closed that day.</li>
                        <li><b>Not offered</b>: this appointment is not offered on that weekday.</li>
                        <li>Greyed dates are in the past, too soon or too far ahead.</li>
                    </ul>
                </div>
            </section>

            @if ($date)
                <section class="ix-panel" aria-labelledby="slots-h">
                    <div class="ix-panel__body">
                        <h2 class="ix-h2" id="slots-h">{{ $date->format('l j F Y') }}</h2>
                        @if ($slots->isEmpty())
                            <p>{{ match ($dayStatus) {
                                'full' => 'Every time on this date is taken. Choose another date.',
                                'unavailable' => $team->name.' is closed on this date.',
                                'not_offered' => 'This appointment is not offered on this weekday.',
                                default => 'No times can be booked on this date. Choose another date.',
                            } }}</p>
                        @else
                            <p class="ix-hint">Choose a time. You will be asked to sign in, then to check the details before the booking is made.</p>
                            <x-booking-zone :zone="$zone">
                                <ul class="ix-actions" style="list-style: none; padding: 0;">
                                    @foreach ($slots as $slot)
                                        <li>
                                            <a class="ix-btn ix-btn--primary" href="{{ $slot['url'] }}" aria-label="Book {{ $slot['local']->format('H:i') }} on {{ $date->format('l j F') }}">
                                                <span class="material-symbols-rounded" aria-hidden="true">task_alt</span>Book
                                                <time datetime="{{ $slot['start']->utc()->format('Y-m-d\TH:i:s\Z') }}" data-show="time">{{ $slot['local']->format('H:i') }}</time>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </x-booking-zone>
                        @endif
                    </div>
                </section>
            @endif
        @endif

        @if ($setting->terms)
            <section class="ix-panel" aria-label="Terms"><div class="ix-panel__body"><h2 class="ix-h2">Terms</h2><p class="ix-biz__prose">{!! nl2br(e($setting->terms)) !!}</p></div></section>
        @endif
    @endif
</x-app-layout>

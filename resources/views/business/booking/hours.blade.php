@php
    $title = $provider ? ($provider->public_name.': hours') : 'Business hours';
    $action = $provider ? route('business.booking.providers.hours.update', [$team, $provider]) : route('business.booking.hours.update', $team);
    $submitted = old('hours');
@endphp
<x-app-layout :title="$title">
    @include('business.booking._nav', ['team' => $team, 'active' => $provider ? (auth()->user()->can('manageBookings', $team) ? 'providers' : 'myhours') : 'hours'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $provider ? 'Hours for '.$provider->public_name : 'Business hours' }}</h1>
            <p class="ix-lede">
                @if ($provider)
                    The weekly hours {{ $provider->public_name }} takes appointments, in {{ str_replace('_', ' ', $zone) }} time. These count only where they fall inside the business hours.
                @else
                    The weekly hours {{ $team->name }} takes appointments, in {{ str_replace('_', ' ', $zone) }} time. <a href="{{ route('business.booking.settings', $team) }}">Change the time zone</a>.
                @endif
            </p>
        </div>
    </div>

    @if (session('status'))
        <p class="ix-alert" role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="ix-error" role="alert">
            @foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endif

    @if (! $businessHasHours)
        <p class="ix-alert" role="note">
            @if ($provider)
                The business has no hours yet, so customers cannot book anyone until it does.
            @else
                No hours are set yet, so customers cannot book until you set them.
            @endif
        </p>
    @endif

    <section class="ix-panel" aria-labelledby="week-h">
        <div class="ix-panel__body">
            <h2 id="week-h" class="ix-h2">The week</h2>
            <p class="ix-hint">
                Give each day you are open one or more stretches, like 08:00 to 17:00 (24-hour times). Two stretches on one day leave a break between them, for example 08:00 to 12:00 and 13:00 to 17:00. Leave a day empty to be closed.
                @if ($provider)
                    Leave the whole week empty and {{ $provider->public_name }} works the business hours.
                @endif
            </p>

            <form method="post" action="{{ $action }}" class="ix-form">
                @csrf
                @foreach (\App\Models\WorkingHour::DAYS as $weekday => $dayName)
                    @php
                        $rows = is_array($submitted) ? array_values(array_filter($submitted[$weekday] ?? [], 'is_array')) : $week[$weekday];
                        $count = min(6, max(2, count($rows) + 1));
                    @endphp
                    <fieldset class="ix-choice">
                        <legend class="ix-label">{{ $dayName }}</legend>
                        @for ($i = 0; $i < $count; $i++)
                            <div class="ix-form__row">
                                <div>
                                    <label class="ix-label" for="from-{{ $weekday }}-{{ $i }}">From</label>
                                    <input class="ix-input" id="from-{{ $weekday }}-{{ $i }}" type="time" name="hours[{{ $weekday }}][{{ $i }}][from]" value="{{ $rows[$i]['from'] ?? '' }}">
                                </div>
                                <div>
                                    <label class="ix-label" for="to-{{ $weekday }}-{{ $i }}">To</label>
                                    <input class="ix-input" id="to-{{ $weekday }}-{{ $i }}" type="time" name="hours[{{ $weekday }}][{{ $i }}][to]" value="{{ $rows[$i]['to'] ?? '' }}">
                                </div>
                            </div>
                        @endfor
                    </fieldset>
                @endforeach
                <div><button type="submit" class="ix-btn ix-btn--primary">Save the hours</button></div>
            </form>
        </div>
    </section>

    @unless ($provider)
        @if ($providers->isNotEmpty())
            <section class="ix-panel" aria-labelledby="prov-h">
                <div class="ix-panel__body">
                    <h2 id="prov-h" class="ix-h2">Each provider's own hours</h2>
                    <p class="ix-hint">A provider with no hours of their own works the business hours.</p>
                    <ul>
                        @foreach ($providers as $p)
                            <li><a href="{{ route('business.booking.providers.hours', [$team, $p]) }}">{{ $p->public_name }}</a> <span class="ix-hint">{{ $p->working_hours_count > 0 ? 'has own hours' : 'works the business hours' }}</span></li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
    @endunless
</x-app-layout>

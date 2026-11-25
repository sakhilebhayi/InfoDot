@php
    $fmtDay = fn ($instant) => $instant->copy()->setTimezone($zone)->format('D j M Y');
    $fmtTime = fn ($instant) => $instant->copy()->setTimezone($zone)->format('H:i');
@endphp
<x-app-layout title="Closures">
    @include('business.booking._nav', ['team' => $team, 'active' => 'closures'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Closures</h1>
            <p class="ix-lede">Holidays and time you cannot be booked, shown in {{ str_replace('_', ' ', $zone->getName()) }} time. Nothing can be booked inside them.</p>
        </div>
    </div>

    @if (session('status'))
        <p class="ix-alert" role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    @if ($manages && ! $hasHours)
        <p class="ix-alert" role="note">No business hours are set yet, so customers cannot book anything. <a href="{{ route('business.booking.hours', $team) }}">Set the hours</a>.</p>
    @endif

    @if ($closures->isEmpty())
        <section class="ix-panel" aria-label="Upcoming closures">
            <div class="ix-panel__body"><p>No upcoming closures. Add a holiday or block some time below.</p></div>
        </section>
    @else
        <section class="ix-panel ix-panel--clip" aria-label="Upcoming closures">
            <div class="ix-tablewrap">
                <table class="ix-table">
                    <thead><tr><th>What</th><th>From</th><th>Until</th><th>Applies to</th><th>Note</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($closures as $closure)
                            @php
                                $whole = $closure->kind === 'holiday';
                                $lastDay = $closure->ends_at->copy()->setTimezone($zone)->subSecond();
                            @endphp
                            <tr>
                                <td class="title">{{ $whole ? 'Holiday' : 'Blocked time' }}</td>
                                <td data-label="From">{{ $fmtDay($closure->starts_at) }}@unless ($whole) {{ $fmtTime($closure->starts_at) }}@endunless</td>
                                <td data-label="Until">{{ $whole ? $lastDay->format('D j M Y').' (whole day)' : $fmtDay($closure->ends_at).' '.$fmtTime($closure->ends_at) }}</td>
                                <td data-label="Applies to">{{ $closure->provider?->public_name ?? 'The whole business' }}</td>
                                <td class="muted" data-label="Note">{{ $closure->reason }}</td>
                                <td>
                                    <form method="post" action="{{ route('business.booking.closures.delete', [$team, $closure]) }}">@csrf
                                        <button type="submit" class="ix-btn ix-btn--sm ix-btn--danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="ix-panel" aria-labelledby="add-h">
        <div class="ix-panel__body">
            <h2 id="add-h" class="ix-h2">Add a closure</h2>
            <p class="ix-hint">A holiday closes whole days. Blocked time closes part of a day, or runs across days by giving a last day.</p>

            <form method="post" action="{{ route('business.booking.closures.store', $team) }}" class="ix-form">
                @csrf
                <fieldset class="ix-choice">
                    <legend class="ix-label">What kind</legend>
                    <label class="ix-choice__opt">
                        <input type="radio" name="kind" value="holiday" @checked(old('kind', 'holiday') === 'holiday')>
                        <span><b>A holiday</b><span class="ix-hint">One or more whole days. The last day is included.</span></span>
                    </label>
                    <label class="ix-choice__opt">
                        <input type="radio" name="kind" value="block" @checked(old('kind') === 'block')>
                        <span><b>Blocked time</b><span class="ix-hint">From a time to a time. Fill in both times below.</span></span>
                    </label>
                </fieldset>

                <div class="ix-form__row">
                    <div>
                        <label class="ix-label" for="scope">Applies to</label>
                        <select class="ix-select" id="scope" name="scope">
                            @if ($manages)
                                <option value="business" @selected(old('scope', 'business') === 'business')>The whole business</option>
                            @endif
                            @foreach ($providers as $p)
                                <option value="{{ $p->id }}" @selected((string) old('scope') === (string) $p->id)>{{ $p->public_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="ix-form__row">
                    <div>
                        <label class="ix-label" for="starts_on">First day</label>
                        <x-form.date name="starts_on" required />
                    </div>
                    <div>
                        <label class="ix-label" for="ends_on">Last day <span class="ix-hint">leave blank for one day</span></label>
                        <x-form.date name="ends_on" />
                    </div>
                </div>

                <div class="ix-form__row">
                    <div>
                        <label class="ix-label" for="from">From <span class="ix-hint">blocked time only</span></label>
                        <input class="ix-input" id="from" name="from" type="time" value="{{ old('from') }}">
                    </div>
                    <div>
                        <label class="ix-label" for="to">To <span class="ix-hint">blocked time only</span></label>
                        <input class="ix-input" id="to" name="to" type="time" value="{{ old('to') }}">
                    </div>
                </div>

                <div class="ix-form__row">
                    <div>
                        <label class="ix-label" for="reason">Note <span class="ix-hint">only your team sees this</span></label>
                        <input class="ix-input" id="reason" name="reason" type="text" maxlength="160" value="{{ old('reason') }}">
                    </div>
                </div>

                <div><button type="submit" class="ix-btn ix-btn--primary">Add the closure</button></div>
            </form>
        </div>
    </section>
</x-app-layout>

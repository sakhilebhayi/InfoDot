<x-app-layout title="Booking settings">
    @include('business.booking._nav', ['team' => $team, 'active' => 'settings'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Appointment booking</h1>
            <p class="ix-lede">How <b>{{ $team->name }}</b> takes appointments. <a href="{{ route('business.show', $team) }}">Open the business page</a>.</p>
        </div>
    </div>

    @if (session('status'))
        <p class="ix-alert" role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="ix-error" role="alert">
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    <section class="ix-panel" aria-labelledby="booking-h">
        <div class="ix-panel__body">
            <h2 id="booking-h" class="ix-h2">Booking settings</h2>
            <p class="ix-hint">Nothing here is shown to customers until you switch bookings on.</p>

                <form method="post" action="{{ route('business.booking.settings.update', $team) }}" class="ix-form">
                    @csrf

                    <input type="hidden" name="enabled" value="0">
                    <input type="hidden" name="calendar_published" value="0">
                    <fieldset class="ix-choice">
                        <legend class="ix-label">Taking appointments</legend>
                        <label class="ix-choice__opt">
                            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $setting->enabled))>
                            <span><b>Let people request appointments with {{ $team->name }}</b><span class="ix-hint">Switch this off to stop new requests. Existing appointments stay on your side.</span></span>
                        </label>
                        <label class="ix-choice__opt">
                            <input type="checkbox" name="calendar_published" value="1" @checked(old('calendar_published', $setting->calendar_published))>
                            <span><b>Show when we are free and fully booked</b><span class="ix-hint">On the business page. Only the status of each day and time, never who has booked.</span></span>
                        </label>
                    </fieldset>

                    <fieldset class="ix-choice">
                        <legend class="ix-label">When someone books</legend>
                        <label class="ix-choice__opt">
                            <input type="radio" name="approval_mode" value="approval" @checked(old('approval_mode', $setting->approval_mode) === 'approval')>
                            <span><b>We confirm each one</b><span class="ix-hint">The customer is told the appointment is pending until you accept it.</span></span>
                        </label>
                        <label class="ix-choice__opt">
                            <input type="radio" name="approval_mode" value="auto" @checked(old('approval_mode', $setting->approval_mode) === 'auto')>
                            <span><b>Confirm automatically</b><span class="ix-hint">A free time is booked and confirmed straight away.</span></span>
                        </label>
                    </fieldset>

                    <div class="ix-form__row">
                        <label class="ix-label" for="timezone">Time zone <span class="ix-hint">every date and time is worked out here; customers see their own</span></label>
                        <select class="ix-input" id="timezone" name="timezone">
                            @foreach ($timezones as $zone)
                                <option value="{{ $zone }}" @selected(old('timezone', $setting->timezone) === $zone)>{{ str_replace('_', ' ', $zone) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ix-form__row">
                        <label class="ix-label" for="min_notice_hours">Least notice <span class="ix-hint">hours before the appointment a customer can still book</span></label>
                        <input class="ix-input" id="min_notice_hours" name="min_notice_hours" type="number" inputmode="numeric" min="0" max="336" step="1" value="{{ old('min_notice_hours', intdiv($setting->min_notice_minutes, 60)) }}">
                    </div>

                    <div class="ix-form__row">
                        <label class="ix-label" for="horizon_days">Furthest ahead <span class="ix-hint">days from today a customer can book</span></label>
                        <input class="ix-input" id="horizon_days" name="horizon_days" type="number" inputmode="numeric" min="1" max="365" step="1" value="{{ old('horizon_days', $setting->horizon_days) }}">
                    </div>

                    <div class="ix-form__row">
                        <label class="ix-label" for="cancel_cutoff_hours">Cancellation cut-off <span class="ix-hint">hours before the appointment after which a customer cannot cancel themselves</span></label>
                        <input class="ix-input" id="cancel_cutoff_hours" name="cancel_cutoff_hours" type="number" inputmode="numeric" min="0" max="336" step="1" value="{{ old('cancel_cutoff_hours', $setting->cancel_cutoff_hours) }}">
                    </div>

                    <div class="ix-form__row">
                        <label class="ix-label" for="instructions">Booking instructions <span class="ix-hint">shown to the customer before they book</span></label>
                        <textarea class="ix-textarea" id="instructions" name="instructions" rows="4" maxlength="1500">{{ old('instructions', $setting->instructions) }}</textarea>
                    </div>

                    <div class="ix-form__row">
                        <label class="ix-label" for="terms">Terms <span class="ix-hint">for example late arrival, fees or what to bring</span></label>
                        <textarea class="ix-textarea" id="terms" name="terms" rows="5" maxlength="4000">{{ old('terms', $setting->terms) }}</textarea>
                    </div>

                    <button type="submit" class="ix-btn ix-btn--primary">Save the booking settings</button>
            </form>
        </div>
    </section>
</x-app-layout>

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

                <div class="ix-choice">
                    <span class="ix-label">Taking appointments</span>
                    <x-form.switch name="enabled" :checked="$setting->enabled" :label="'Let people request appointments with '.$team->name" hint="Switch this off to stop new requests. Existing appointments stay on your side." />
                    <x-form.switch name="calendar_published" :checked="$setting->calendar_published" label="Show when we are free and fully booked" hint="On the business page. Only the status of each day and time, never who has booked." />
                </div>

                <x-form.radio-group name="approval_mode" legend="When someone books" :value="$setting->approval_mode" :options="[
                    'approval' => ['We confirm each one', 'The customer is told the appointment is pending until you accept it.'],
                    'auto' => ['Confirm automatically', 'A free time is booked and confirmed straight away.'],
                ]" />

                <x-form.field name="timezone" label="Time zone" hint="Every date and time is worked out here; customers see their own.">
                    <x-form.select name="timezone">
                        @foreach ($timezones as $zone)
                            <option value="{{ $zone }}" @selected(old('timezone', $setting->timezone) === $zone)>{{ str_replace('_', ' ', $zone) }}</option>
                        @endforeach
                    </x-form.select>
                </x-form.field>

                <x-form.field name="min_notice_hours" label="Least notice" hint="Hours before the appointment a customer can still book.">
                    <x-form.input name="min_notice_hours" type="number" inputmode="numeric" min="0" max="336" step="1" :value="intdiv($setting->min_notice_minutes, 60)" />
                </x-form.field>

                <x-form.field name="horizon_days" label="Furthest ahead" hint="Days from today a customer can book.">
                    <x-form.input name="horizon_days" type="number" inputmode="numeric" min="1" max="365" step="1" :value="$setting->horizon_days" />
                </x-form.field>

                <x-form.field name="cancel_cutoff_hours" label="Cancellation cut-off" hint="Hours before the appointment after which a customer cannot cancel themselves.">
                    <x-form.input name="cancel_cutoff_hours" type="number" inputmode="numeric" min="0" max="336" step="1" :value="$setting->cancel_cutoff_hours" />
                </x-form.field>

                <x-form.field name="instructions" label="Booking instructions" hint="Shown to the customer before they book." :optional="true">
                    <x-form.textarea name="instructions" rows="4" maxlength="1500" :value="$setting->instructions" />
                </x-form.field>

                <x-form.field name="terms" label="Terms" hint="For example late arrival, fees or what to bring." :optional="true">
                    <x-form.textarea name="terms" rows="5" maxlength="4000" :value="$setting->terms" />
                </x-form.field>

                <button type="submit" class="ix-btn ix-btn--primary">Save the booking settings</button>
            </form>
        </div>
    </section>
</x-app-layout>

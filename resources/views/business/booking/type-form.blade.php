@php
    $editing = $type->exists;
    $fee = old('fee', $type->fee_cents === null ? '' : number_format($type->fee_cents / 100, 2, '.', ''));
    $ticked = collect(old('providers', $linked))->map(fn ($id) => (int) $id)->all();
    $action = $editing ? route('business.booking.types.update', [$team, $type]) : route('business.booking.types.store', $team);
@endphp
<x-app-layout :title="$editing ? 'Edit appointment type' : 'Add an appointment type'">
    @include('business.booking._nav', ['team' => $team, 'active' => 'types'])

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">{{ $editing ? 'Edit '.$type->name : 'Add an appointment type' }}</h1>
            <p class="ix-lede"><a href="{{ route('business.booking.types', $team) }}">Back to the appointment types</a></p>
        </div>
    </div>

    @if ($errors->any())
        <div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>
    @endif

    <section class="ix-panel" aria-label="Appointment type">
        <div class="ix-panel__body">
            <form method="post" action="{{ $action }}" class="ix-form">
                @csrf

                <div class="ix-form__row">
                    <div>
                        <label class="ix-label" for="name">Name <span class="ix-hint">what the customer picks, for example Initial consultation</span></label>
                        <input class="ix-input" id="name" name="name" type="text" required minlength="2" maxlength="120" value="{{ old('name', $type->name) }}">
                    </div>
                </div>

                <div class="ix-form__row">
                    <div>
                        <label class="ix-label" for="description">Description <span class="ix-hint">what the appointment is for</span></label>
                        <textarea class="ix-textarea" id="description" name="description" rows="3" maxlength="1000">{{ old('description', $type->description) }}</textarea>
                    </div>
                </div>

                <div class="ix-form__row">
                    <div>
                        <label class="ix-label" for="duration_minutes">Length <span class="ix-hint">minutes, 5 to 480</span></label>
                        <input class="ix-input" id="duration_minutes" name="duration_minutes" type="number" inputmode="numeric" min="5" max="480" step="1" required value="{{ old('duration_minutes', $type->duration_minutes) }}">
                    </div>
                    <div>
                        <label class="ix-label" for="slot_interval_minutes">Start times every <span class="ix-hint">minutes, 5 to 240</span></label>
                        <input class="ix-input" id="slot_interval_minutes" name="slot_interval_minutes" type="number" inputmode="numeric" min="5" max="240" step="1" required value="{{ old('slot_interval_minutes', $type->slot_interval_minutes) }}">
                    </div>
                </div>

                <div class="ix-form__row">
                    <div>
                        <label class="ix-label" for="buffer_before_minutes">Gap before <span class="ix-hint">minutes kept free before it</span></label>
                        <input class="ix-input" id="buffer_before_minutes" name="buffer_before_minutes" type="number" inputmode="numeric" min="0" max="240" step="1" required value="{{ old('buffer_before_minutes', $type->buffer_before_minutes) }}">
                    </div>
                    <div>
                        <label class="ix-label" for="buffer_after_minutes">Gap after <span class="ix-hint">minutes kept free after it</span></label>
                        <input class="ix-input" id="buffer_after_minutes" name="buffer_after_minutes" type="number" inputmode="numeric" min="0" max="240" step="1" required value="{{ old('buffer_after_minutes', $type->buffer_after_minutes) }}">
                    </div>
                </div>

                <div class="ix-form__row">
                    <div>
                        <label class="ix-label" for="fee">Fee <span class="ix-hint">shown to the customer as information; nothing is charged here. Leave blank for no fee</span></label>
                        <input class="ix-input" id="fee" name="fee" type="text" inputmode="decimal" maxlength="20" value="{{ $fee }}" placeholder="250.00">
                    </div>
                    <div>
                        <label class="ix-label" for="currency">Currency</label>
                        <select class="ix-select" id="currency" name="currency">
                            @foreach ($currencies as $code => $label)
                                <option value="{{ $code }}" @selected(old('currency', $type->currency) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="ix-form__row">
                    <div>
                        <label class="ix-label" for="meeting_format">Format</label>
                        <select class="ix-select" id="meeting_format" name="meeting_format">
                            @foreach (\App\Models\AppointmentType::FORMATS as $key => $label)
                                <option value="{{ $key }}" @selected(old('meeting_format', $type->meeting_format) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="ix-label" for="approval">Confirmation</label>
                        <select class="ix-select" id="approval" name="approval">
                            <option value="follow" @selected(old('approval', $approval) === 'follow')>Follow the business setting ({{ $approvalMode === 'auto' ? 'confirm automatically' : 'we confirm each one' }})</option>
                            <option value="always" @selected(old('approval', $approval) === 'always')>Always confirm myself</option>
                            <option value="auto" @selected(old('approval', $approval) === 'auto')>Confirm automatically</option>
                        </select>
                    </div>
                </div>

                <div class="ix-form__row">
                    <div>
                        <label class="ix-label" for="location_note">Where or how to join <span class="ix-hint">an address, or a link or phone number for joining. An in-person address is shown to everyone before they book; for online and phone appointments this is shown only to people who have booked. Do not put anything here you would not tell a stranger if the appointment is in person.</span></label>
                        <input class="ix-input" id="location_note" name="location_note" type="text" maxlength="300" value="{{ old('location_note', $type->location_note) }}">
                    </div>
                </div>

                <div class="ix-form__row">
                    <div>
                        <label class="ix-label" for="preparation">What to prepare <span class="ix-hint">shown only to people who have booked</span></label>
                        <textarea class="ix-textarea" id="preparation" name="preparation" rows="3" maxlength="1500">{{ old('preparation', $type->preparation) }}</textarea>
                    </div>
                </div>

                <fieldset class="ix-choice">
                    <legend class="ix-label">Who takes this appointment</legend>
                    @forelse ($providers as $provider)
                        <label class="ix-choice__opt">
                            <input type="checkbox" name="providers[]" value="{{ $provider->id }}" @checked(in_array($provider->id, $ticked, true)) @disabled(! $provider->active && ! in_array($provider->id, $linked, true))>
                            <span><b>{{ $provider->public_name }}</b>@unless ($provider->active)<span class="ix-hint">Switched off</span>@endunless</span>
                        </label>
                    @empty
                        <p class="ix-hint">No providers yet, so {{ $team->name }} takes this appointment itself, on the business hours. <a href="{{ route('business.booking.providers', $team) }}">Add a provider</a>.</p>
                    @endforelse
                    @if ($providers->isNotEmpty())
                        <p class="ix-hint">Tick no one and the business itself takes it, on the business hours.</p>
                    @endif
                </fieldset>

                <div class="ix-form__row">
                    <div>
                        <label class="ix-label" for="sort">Order in the list <span class="ix-hint">lowest number first</span></label>
                        <input class="ix-input" id="sort" name="sort" type="number" inputmode="numeric" min="0" max="9999" step="1" value="{{ old('sort', $type->sort) }}">
                    </div>
                </div>

                <input type="hidden" name="active" value="0">
                <fieldset class="ix-choice">
                    <legend class="ix-label">Availability</legend>
                    <label class="ix-choice__opt">
                        <input type="checkbox" name="active" value="1" @checked(old('active', $type->active ?? true))>
                        <span><b>Customers can book this</b><span class="ix-hint">Untick to keep it saved but hidden.</span></span>
                    </label>
                </fieldset>

                <div class="ix-actions">
                    <button type="submit" class="ix-btn ix-btn--primary">{{ $editing ? 'Save the appointment type' : 'Add the appointment type' }}</button>
                    <a class="ix-btn ix-btn--ghost" href="{{ route('business.booking.types', $team) }}">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</x-app-layout>

<x-app-layout :title="'Request an appointment with '.$team->name">
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Request an appointment with {{ $team->name }}</h1>
            <p class="ix-lede">{{ $team->name }} has no booking calendar on InfoDot, so say when suits you and what it is about. The business accepts with a set date and time, or declines with a reason. Its answer arrives in your inbox.</p>
        </div>
        <a href="{{ route('business.show', $team) }}" class="ix-btn ix-btn--ghost">Back to the business</a>
    </div>

    @if ($errors->any())<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif

    @if ($reason)
        <div class="ix-empty">
            <h2>You cannot send a request here right now</h2>
            <p>{{ $reason }}</p>
            <a class="ix-btn" href="{{ route('business.show', $team) }}">Back to the business</a>
        </div>
    @else
        <form method="post" action="{{ route('business.appointment-request.store', $team) }}" class="ix-form ix-compose">
            @csrf
            <div class="ix-form__row">
                <label class="ix-label" for="on">Preferred date</label>
                <input class="ix-input" type="date" id="on" name="on" required min="{{ now()->toDateString() }}" max="{{ now()->addYear()->toDateString() }}" value="{{ old('on') }}">
            </div>
            <div class="ix-form__row">
                <label class="ix-label" for="part_of_day">Preferred time</label>
                <select class="ix-select" id="part_of_day" name="part_of_day" required>
                    @foreach (\App\Models\Enquiry::PARTS_OF_DAY as $value => $label)<option value="{{ $value }}" @selected(old('part_of_day', 'any') === $value)>{{ $label }}</option>@endforeach
                    <option value="set" @selected(old('part_of_day') === 'set')>A set time</option>
                </select>
            </div>
            <div class="ix-form__row">
                <label class="ix-label" for="set_time">Time <span class="ix-hint">only when you chose a set time</span></label>
                <input class="ix-input" type="time" id="set_time" name="set_time" step="900" value="{{ old('set_time') }}">
            </div>
            <div class="ix-form__row">
                <label class="ix-label" for="minutes">How long</label>
                <select class="ix-select" id="minutes" name="minutes" required>
                    @foreach (\App\Models\Enquiry::LENGTHS as $m)<option value="{{ $m }}" @selected((int) old('minutes', 60) === $m)>{{ \App\Models\Enquiry::lengthLabel($m) }}</option>@endforeach
                </select>
            </div>
            @include('partials.acting-company', ['companies' => $companies, 'selected' => $company, 'label' => 'Booking for', 'hint' => $team->name.' sees this request as coming from the company you choose, with a link to its page.'])
            <div class="ix-form__row">
                <label class="ix-label" for="about">What it is about</label>
                <textarea class="ix-textarea" id="about" name="about" rows="6" minlength="{{ config('enquiries.min_length') }}" maxlength="{{ config('enquiries.max_length') }}" required placeholder="What you would like to discuss, and where you would like to meet or whether a call suits you.">{{ old('about') }}</textarea>
            </div>
            <p class="ix-hint">A request does not hold a time until {{ $team->name }} accepts it. Only its owner, administrators and the person they give it to can read it.</p>
            <div><button type="submit" class="ix-btn ix-btn--primary"><span class="material-symbols-rounded" aria-hidden="true">send</span>Send the request</button></div>
        </form>
    @endif
</x-app-layout>

<x-app-layout :title="'Confirm your appointment with '.$team->name">
    @push('meta')<meta name="robots" content="noindex">@endpush

    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Check and book</h1>
            <p class="ix-lede"><a href="{{ route('business.show', $team) }}">{{ $team->name }}</a></p>
        </div>
        <a href="{{ $dateUrl }}" class="ix-btn ix-btn--ghost"><span class="material-symbols-rounded" aria-hidden="true">arrow_back</span>Choose another time</a>
    </div>

    @if ($errors->has('booking'))<div class="ix-error" role="alert"><p>{{ $errors->first('booking') }}</p></div>@endif

    @if ($closed)
        <p class="ix-alert" role="status">{{ $closed }}</p>
    @else
        <x-booking-zone :zone="$zone">
            <section class="ix-panel" aria-labelledby="sum-h">
                <div class="ix-panel__body">
                    <h2 class="ix-h2" id="sum-h">{{ $type->name }}</h2>
                    <dl class="ix-biz__facts">
                        <div><dt>When</dt><dd><time datetime="{{ $start->format('Y-m-d\TH:i:s\Z') }}" data-show="full">{{ $local->format('D j M Y, H:i') }}</time> <span class="ix-hint">{{ $zone }} time ({{ $local->format('T') }})</span></dd></div>
                    </dl>
                    @include('business.book._facts', ['type' => $type])
                </div>
            </section>
        </x-booking-zone>

        <form method="post" action="{{ route('business.book.store', $team) }}" class="ix-form" style="margin-top: 16px;">
            @csrf
            <input type="hidden" name="type" value="{{ $type->id }}">
            <input type="hidden" name="start" value="{{ $start->format('Y-m-d\TH:i:s\Z') }}">
            @if ($provider)<input type="hidden" name="provider" value="{{ $provider }}">@endif

            @if ($errors->any() && ! $errors->has('booking'))<div class="ix-error" role="alert"><p>{{ $errors->first() }}</p></div>@endif

            <div class="ix-form__row">
                <div><label class="ix-label" for="name">Your name</label><input class="ix-input" id="name" name="name" type="text" required minlength="2" maxlength="120" autocomplete="name" value="{{ old('name', $user->name) }}"></div>
                <div><label class="ix-label" for="email">Email</label><input class="ix-input" id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="{{ old('email', $user->email) }}"></div>
            </div>
            <div class="ix-form__row">
                <div><label class="ix-label" for="phone">Phone <span class="ix-hint">optional</span></label><input class="ix-input" id="phone" name="phone" type="tel" maxlength="40" autocomplete="tel" value="{{ old('phone') }}"></div>
                <div><label class="ix-label" for="company">Your business <span class="ix-hint">optional</span></label><input class="ix-input" id="company" name="company" type="text" maxlength="160" value="{{ old('company') }}"></div>
            </div>
            <div class="ix-form__row">
                <div><label class="ix-label" for="reason">What is it about? <span class="ix-hint">optional</span></label><textarea class="ix-textarea" id="reason" name="reason" rows="4" maxlength="1500">{{ old('reason') }}</textarea></div>
            </div>

            @if ($setting->terms)
                <section aria-label="Terms">
                    <h2 class="ix-h2">Terms of {{ $team->name }}</h2>
                    <p class="ix-biz__prose">{!! nl2br(e($setting->terms)) !!}</p>
                    <label><input type="checkbox" name="terms_accepted" value="1" required @checked(old('terms_accepted'))> I accept these terms</label>
                </section>
            @endif

            <p class="ix-hint">{{ $team->name }} sees your name, email and anything you add above, so it can prepare. Fees are for information only; nothing is charged through InfoDot.</p>
            <div><button type="submit" class="ix-btn ix-btn--primary"><span class="material-symbols-rounded" aria-hidden="true">task_alt</span>Book this appointment</button></div>
        </form>
    @endif
</x-app-layout>

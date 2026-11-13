@if ($errors->any())
    {{-- What needs attention, announced once and worded as something the person can put right (it is a missed field, not a fault). --}}
    <div {{ $attributes->merge(['class' => 'ix-alert ix-alert--danger']) }} role="alert">
        <p class="ix-alert__head">{{ __('Check the following and try again.') }}</p>

        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

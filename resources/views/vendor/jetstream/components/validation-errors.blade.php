@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'ix-alert']) }} role="alert" style="border-color: var(--ix-danger);">
        <div style="color: var(--ix-danger); font-weight: 600;">{{ __('Something went wrong.') }}</div>

        <ul class="mt-2 list-inside list-disc" style="color: var(--ix-text-2); font-weight: 400;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- A group of radios in a fieldset: `options` is value => label (or value => [label, hint]); the chosen one is old input, else `value`. --}}
@props(['name', 'legend', 'options', 'value' => null])
@php($chosen = (string) old($name, $value))
<fieldset {{ $attributes->class(['ix-choice']) }} @if ($errors->has($name)) aria-invalid="true" @endif>
    <legend class="ix-label">{{ $legend }}</legend>
    @foreach ($options as $optionValue => $option)
        @php([$optionLabel, $optionHint] = is_array($option) ? [$option[0], $option[1] ?? null] : [$option, null])
        <label class="ix-choice__opt">
            <input type="radio" name="{{ $name }}" value="{{ $optionValue }}" @checked((string) $optionValue === $chosen)>
            <span><b>{{ $optionLabel }}</b>@if ($optionHint)<span class="ix-hint">{{ $optionHint }}</span>@endif</span>
        </label>
    @endforeach
    @error($name)<p class="ix-error" role="alert">{{ $message }}</p>@enderror
</fieldset>

@props(['for'])

@error($for)
    <p {{ $attributes->merge(['class' => 'ix-error']) }} role="alert">{{ $message }}</p>
@enderror

@props(['for'])

@error($for)
    <p {{ $attributes->merge(['class' => 'ix-error']) }}>{{ $message }}</p>
@enderror

{{-- The form's one full-width action, with a loading state: the label changes, the button is disabled and announced as busy
     while the request is on its way (the form carries x-data="{ sending: false }" and sets it on submit). --}}
@props(['label', 'busy' => 'One moment…'])
<x-jet-button {{ $attributes->merge(['class' => 'ix-entry__go']) }} x-bind:disabled="sending" x-bind:aria-busy="sending ? 'true' : 'false'">
    <span x-show="! sending">{{ $label }}</span>
    <span x-show="sending" x-cloak>{{ $busy }}</span>
</x-jet-button>

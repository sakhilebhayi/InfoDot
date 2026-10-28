{{-- A password field with a show/hide control, so a long passphrase can be checked before it is sent. Pass the usual input attributes. --}}
<div class="ix-pw" x-data="{ shown: false }">
    <x-jet-input {{ $attributes }} x-bind:type="shown ? 'text' : 'password'" type="password" />
    <button type="button" class="ix-pw__toggle" x-on:click="shown = ! shown" x-bind:aria-label="shown ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'" aria-label="{{ __('Show password') }}">
        <span class="material-symbols-rounded" aria-hidden="true" x-text="shown ? 'visibility_off' : 'visibility'">visibility</span>
    </button>
</div>

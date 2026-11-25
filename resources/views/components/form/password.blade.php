{{--
    A password field (R-64 phase 4): a show and hide control that says what it does (aria-pressed), a warning while Caps Lock is on, and, with `strength`,
    the existing strength advice underneath. Never refills the old value. Use inside form.field; `strength` needs the field's id (the strength meter listens for it).
--}}
@props(['name' => 'password', 'id' => null, 'strength' => false, 'autocomplete' => 'current-password'])
@php($id = $id ?? $name)
<div class="ix-pw" x-data="{ shown: false, caps: false }">
    <input {{ $attributes->merge(['name' => $name, 'id' => $id, 'class' => 'ix-input', 'autocomplete' => $autocomplete]) }} type="password" x-bind:type="shown ? 'text' : 'password'"
        aria-describedby="{{ $id }}-msg" @if ($errors->has($name)) aria-invalid="true" @endif
        x-on:keyup="caps = $event.getModifierState && $event.getModifierState('CapsLock')" x-on:blur="caps = false">
    <button type="button" class="ix-pw__toggle" x-on:click="shown = ! shown" x-bind:aria-pressed="shown ? 'true' : 'false'" aria-pressed="false" aria-label="{{ __('Show password') }}"
        x-bind:aria-label="shown ? @js(__('Hide password')) : @js(__('Show password'))">
        <span class="material-symbols-rounded" aria-hidden="true" x-text="shown ? 'visibility_off' : 'visibility'">visibility</span>
    </button>
    <p class="ix-hint ix-pw__caps" x-show="caps" x-cloak role="status">Caps Lock is on.</p>
</div>
@if ($strength)
    <x-auth.strength :for="$id" />
@endif

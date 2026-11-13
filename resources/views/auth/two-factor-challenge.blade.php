<x-guest-layout title="Check it is you">
    <x-jet-authentication-card>
        <h1 class="ix-auth-title">Check it is you</h1>

        <div x-data="{ recovery: false }">
            <div class="ix-entry__lede" x-show="! recovery">
                {{ __('Enter the six-digit code from your authenticator app.') }}
            </div>

            <div class="ix-entry__lede" x-show="recovery">
                {{ __('Enter one of the recovery codes you saved when you turned two-factor on.') }}
            </div>

            <x-jet-validation-errors />

            <form class="ix-entry__form" method="POST" action="{{ route('two-factor.login') }}">
                @csrf

                <div class="ix-field" x-show="! recovery">
                    <x-jet-label for="code" value="{{ __('Code') }}" />
                    <x-jet-input id="code" type="text" inputmode="numeric" name="code" autofocus x-ref="code" autocomplete="one-time-code" />
                </div>

                <div class="ix-field" x-show="recovery">
                    <x-jet-label for="recovery_code" value="{{ __('Recovery Code') }}" />
                    <x-jet-input id="recovery_code" type="text" name="recovery_code" x-ref="recovery_code" autocomplete="one-time-code" />
                </div>

                <div class="ix-entry__row">
                    <x-jet-button>{{ __('Sign in') }}</x-jet-button>

                    <button type="button" class="ix-btn ix-btn--ghost"
                                    x-show="! recovery"
                                    x-on:click="
                                        recovery = true;
                                        $nextTick(() => { $refs.recovery_code.focus() })
                                    ">
                        {{ __('Use a recovery code') }}
                    </button>

                    <button type="button" class="ix-btn ix-btn--ghost"
                                    x-show="recovery"
                                    x-on:click="
                                        recovery = false;
                                        $nextTick(() => { $refs.code.focus() })
                                    ">
                        {{ __('Use an authentication code') }}
                    </button>

                </div>
            </form>
        </div>
    </x-jet-authentication-card>
</x-guest-layout>

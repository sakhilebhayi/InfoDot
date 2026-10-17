<x-app-layout>
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Account settings</h1>
            <p class="ix-lede">Your profile details, password, two factor authentication and signed-in browsers.</p>
        </div>
        <a href="{{ route('profile.show', auth()->id()) }}" class="ix-btn">View public profile</a>
    </div>

    <div style="max-width: 980px;">
        @if (Laravel\Fortify\Features::canUpdateProfileInformation())
            @livewire('profile.update-profile-information-form')
        @endif

        @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
            @livewire('profile.update-password-form')
        @endif

        @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
            @livewire('profile.two-factor-authentication-form')
        @endif

        @livewire('profile.logout-other-browser-sessions-form')

        @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
            @livewire('profile.delete-user-form')
        @endif
    </div>
</x-app-layout>

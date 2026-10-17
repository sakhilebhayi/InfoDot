<x-app-layout>
    @php
        $sections = array_values(array_filter([
            Laravel\Fortify\Features::canUpdateProfileInformation() ? ['profile', 'Profile', 'profile.update-profile-information-form'] : null,
            Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()) ? ['password', 'Password', 'profile.update-password-form'] : null,
            Laravel\Fortify\Features::canManageTwoFactorAuthentication() ? ['two-factor', 'Two factor', 'profile.two-factor-authentication-form'] : null,
            ['sessions', 'Browsers', 'profile.logout-other-browser-sessions-form'],
            Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures() ? ['delete', 'Delete account', 'profile.delete-user-form'] : null,
        ]));
    @endphp
    <div class="ix-pagehead">
        <div>
            <h1 class="ix-h1">Account settings</h1>
            <p class="ix-lede">Your profile details, password, two factor authentication and signed-in browsers.</p>
        </div>
        <a href="{{ route('profile.show', auth()->id()) }}" class="ix-btn">View public profile</a>
    </div>

    <div class="ix-settings">
        <nav class="ix-settings__index" aria-label="Settings sections">
            @foreach ($sections as $i => [$id, $label])
                <a href="#{{ $id }}"><span class="ix-settings__no">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="ix-settings__body">
            @foreach ($sections as [$id, $label, $component])
                <section id="{{ $id }}" class="ix-settings__sec">@livewire($component)</section>
            @endforeach
        </div>
    </div>
</x-app-layout>

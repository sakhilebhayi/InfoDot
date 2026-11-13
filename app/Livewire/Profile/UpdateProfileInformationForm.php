<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm as JetstreamForm;

/**
 * Jetstream's profile form, with one difference. After a picture is saved Jetstream redirects to the route named
 * profile.show, expecting its own /user/profile. Here that name belongs to a person's public page,
 * /user/profile/{id}, so the redirect had no id and every picture save answered 500 (production, 2026-11-12).
 * This form returns to the account page, where the form lives.
 */
class UpdateProfileInformationForm extends JetstreamForm
{
    public function updateProfileInformation(UpdatesUserProfileInformation $updater)
    {
        $this->resetErrorBag();

        $withPhoto = isset($this->photo);

        $updater->update(
            Auth::user(),
            $withPhoto ? array_merge($this->state, ['photo' => $this->photo]) : $this->state
        );

        if ($withPhoto) {
            return redirect()->route('profile.edit');
        }

        $this->dispatch('saved');

        $this->dispatch('refresh-navigation-menu');

        return null;
    }
}

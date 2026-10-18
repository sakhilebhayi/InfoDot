<?php

namespace App\Livewire;

use App\Support\EcosystemHandoff;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

/**
 * A control that opens another Dot platform signed in (the same handoff as the Dot
 * Apps menu): a top-bar tab, a navigation row, or a door on Home. It renders nothing
 * for a platform that is not live, so the interface never offers something that
 * cannot be opened.
 */
class PlatformLink extends Component
{
    public string $platform = '';

    public string $label = '';

    public string $icon = 'open_in_new';

    /** tab | row | door */
    public string $variant = 'row';

    public function open(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $url = EcosystemHandoff::launchUrl($user, $this->platform);

        if ($url !== null) {
            $this->redirect($url);
        }
    }

    public function render(): View
    {
        $live = (bool) (config('ecosystem.platforms.'.$this->platform.'.active') ?? false)
            && EcosystemHandoff::endpointFor(config('ecosystem.platforms.'.$this->platform)) !== null;

        return view('livewire.platform-link', ['live' => $live, 'area' => $this->platform === 'docs' ? 'documents' : $this->platform]);
    }
}

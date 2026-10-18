<?php

namespace App\Livewire;

use App\Support\EcosystemHandoff;
use App\Support\Platforms;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DotSwitcher extends Component
{
    public bool $open = false;

    public bool $inline = false;

    #[Computed]
    public function platforms(): array
    {
        return Platforms::visible();
    }

    public function switchTo(string $platformKey): void
    {
        $user = Auth::user();

        // Bridge services are never opened by a person, even by a hand-made request.
        if (! $user || Platforms::isInternal($platformKey)) {
            return;
        }

        $url = EcosystemHandoff::launchUrl($user, $platformKey);

        if ($url !== null) {
            $this->redirect($url);
        }
    }

    public function render(): View
    {
        return view('livewire.dot-switcher');
    }
}

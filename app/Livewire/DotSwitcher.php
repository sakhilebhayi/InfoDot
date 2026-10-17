<?php

namespace App\Livewire;

use App\Support\EcosystemHandoff;
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
        return config('ecosystem.platforms', []);
    }

    public function switchTo(string $platformKey): void
    {
        $user = Auth::user();

        if (! $user) {
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

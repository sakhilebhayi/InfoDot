<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
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
        $platform = $this->platforms[$platformKey] ?? null;

        if (! $platform) {
            return;
        }

        $user = Auth::user();

        if (! $user) {
            return;
        }

        $tokenResult = $user->createToken(
            'ecosystem-handoff',
            ['ecosystem:read'],
            now()->addMinutes((int) config('ecosystem.handoff_ttl', 5))
        );

        $token = $tokenResult->plainTextToken;

        if ($token) {
            $this->redirect(rtrim($platform['url'], '/') . '/auth/ecosystem?token=' . $token);
        }
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.dot-switcher');
    }
}

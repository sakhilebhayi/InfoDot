<?php

namespace App\Livewire;

use App\Support\EcosystemHandoff;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        $platform = $this->platforms[$platformKey] ?? null;

        if (! $platform) {
            return;
        }

        $user = Auth::user();

        if (! $user) {
            return;
        }

        // Checked BEFORE a token exists: a refused destination never gets one.
        $endpoint = EcosystemHandoff::endpointFor($platform);

        if ($endpoint === null) {
            Log::warning('Ecosystem handoff refused: platform is inactive or its URL is not on a trusted https domain.', [
                'platform' => $platformKey,
                'url' => $platform['url'] ?? null,
            ]);

            return;
        }

        $tokenResult = $user->createToken(
            'ecosystem-handoff',
            ['ecosystem:read'],
            now()->addMinutes((int) config('ecosystem.handoff_ttl', 5))
        );

        $token = $tokenResult->plainTextToken;

        if ($token) {
            $this->redirect($endpoint.'?token='.$token);
        }
    }

    public function render(): View
    {
        return view('livewire.dot-switcher');
    }
}

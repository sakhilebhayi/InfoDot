<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class EcosystemWidget extends Component
{
    public ?string $launching = null;

    #[Computed]
    public function groups(): array
    {
        $all = config('ecosystem.platforms', []);

        return [
            'Workspace' => array_filter($all, fn ($k) => in_array($k, ['files', 'docs', 'forms', 'sheet', 'projects', 'tasks', 'hr', 'plug']), ARRAY_FILTER_USE_KEY),
            'AI & Automation' => array_filter($all, fn ($k) => in_array($k, ['agents', 'analytics', 'memory']), ARRAY_FILTER_USE_KEY),
            'Community & Engagement' => array_filter($all, fn ($k) => in_array($k, ['pulse', 'engage', 'press', 'dopemine']), ARRAY_FILTER_USE_KEY),
            'Commerce & Finance' => array_filter($all, fn ($k) => in_array($k, ['finance', 'emall', 'auction', 'billing', 'charts']), ARRAY_FILTER_USE_KEY),
            'Services & Learning' => array_filter($all, fn ($k) => in_array($k, ['ehail', 'tutor', 'design']), ARRAY_FILTER_USE_KEY),
            'Infrastructure' => array_filter($all, fn ($k) => in_array($k, ['central', 'notify']), ARRAY_FILTER_USE_KEY),
            'Industry Verticals' => array_filter($all, fn ($k) => in_array($k, ['mines', 'farms']), ARRAY_FILTER_USE_KEY),
        ];
    }

    public function launch(string $key): void
    {
        $platforms = config('ecosystem.platforms', []);
        $platform = $platforms[$key] ?? null;

        if (! $platform) {
            return;
        }

        $user = Auth::user();

        if (! $user) {
            return;
        }

        $this->launching = $key;

        $token = $user->createToken(
            'ecosystem-handoff',
            ['ecosystem:read'],
            now()->addMinutes((int) config('ecosystem.handoff_ttl', 5))
        )->plainTextToken;

        $this->redirect(rtrim($platform['url'], '/') . '/auth/ecosystem?token=' . $token);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.ecosystem-widget');
    }
}

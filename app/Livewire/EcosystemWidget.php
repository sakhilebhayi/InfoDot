<?php

namespace App\Livewire;

use App\Support\EcosystemHandoff;
use App\Support\Platforms;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class EcosystemWidget extends Component
{
    public ?string $launching = null;

    /**
     * Platforms by area. Each group carries the plate code and container colour the
     * Dot Apps map uses, so the same platform reads the same everywhere.
     *
     * @return array<string, array{code: string, tone: string, members: array}>
     */
    #[Computed]
    public function groups(): array
    {
        $all = Platforms::visible();
        $pick = fn (array $keys) => array_filter($all, fn ($k) => in_array($k, $keys, true), ARRAY_FILTER_USE_KEY);

        return [
            'Workspace' => ['code' => 'WORK', 'tone' => 'knowledge', 'members' => $pick(['files', 'docs', 'forms', 'sheet', 'projects', 'tasks', 'hr', 'plug'])],
            'AI & Automation' => ['code' => 'AUTO', 'tone' => 'analytics', 'members' => $pick(['agents', 'analytics'])],
            'Community & Engagement' => ['code' => 'COMM', 'tone' => 'platforms', 'members' => $pick(['pulse', 'engage', 'press'])],
            'Commerce & Finance' => ['code' => 'FINA', 'tone' => 'files', 'members' => $pick(['finance', 'emall', 'auction', 'billing', 'charts'])],
            'Services & Learning' => ['code' => 'LEARN', 'tone' => 'documents', 'members' => $pick(['ehail', 'tutor', 'design'])],
            'Infrastructure' => ['code' => 'INFRA', 'tone' => 'people', 'members' => $pick(['central', 'notify'])],
            'Industry Verticals' => ['code' => 'VERT', 'tone' => 'knowledge', 'members' => $pick(['mines', 'farms'])],
        ];
    }

    public function launch(string $key): void
    {
        $user = Auth::user();

        if (! $user || Platforms::isInternal($key)) {
            return;
        }

        $url = EcosystemHandoff::launchUrl($user, $key);

        if ($url !== null) {
            $this->launching = $key;
            $this->redirect($url);
        }
    }

    public function render(): View
    {
        return view('livewire.ecosystem-widget');
    }
}

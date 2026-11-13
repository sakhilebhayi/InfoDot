<?php

namespace App\Livewire;

use App\Services\Platforms\CompanyCatalogue;
use App\Support\EcosystemHandoff;
use App\Support\PlatformCategories;
use App\Support\Platforms;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Dot Apps, the switcher in the top bar. Since R-50 it shows only what the person's current company holds and this
 * person may open now; everything else is on the Platforms page, which it links to. The bays are grouped by the
 * catalogue's categories, each platform under the first one it names.
 */
class DotSwitcher extends Component
{
    public bool $open = false;

    public bool $inline = false;

    /**
     * The platforms this person can open for their current company, by catalogue category.
     *
     * @return list<array{name: string, code: string, tone: string, members: array<string, array<string, mixed>>}>
     */
    #[Computed]
    public function groups(): array
    {
        $user = Auth::user();

        if ($user === null) {
            return [];
        }

        $rows = CompanyCatalogue::shared()->openable($user, CompanyCatalogue::teamOf($user));
        $groups = [];

        foreach (PlatformCategories::ALL as $key => $name) {
            $members = $rows->filter(fn (array $row) => $row['category'] === $key);

            if ($members->isNotEmpty()) {
                [$code, $tone] = PlatformCategories::PLATES[$key];
                $groups[] = ['name' => $name, 'code' => $code, 'tone' => $tone, 'members' => $members->all()];
            }
        }

        return $groups;
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

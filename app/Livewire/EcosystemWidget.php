<?php

namespace App\Livewire;

use App\Services\Platforms\CompanyCatalogue;
use App\Services\Platforms\PlatformRecommendations;
use App\Support\EcosystemHandoff;
use App\Support\PlatformCategories;
use App\Support\Platforms;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The Platforms page (R-50 section 7): the catalogue for the person's current company. What the company holds comes
 * first, then everything else under the catalogue's categories, each with its state in words and one action. The
 * rows, their states and their actions come from CompanyCatalogue; this component only searches, filters and groups
 * them. Opening a platform still goes through EcosystemHandoff, the door that decides.
 *
 * @property-read Collection<string, array<string, mixed>> $rows
 * @property-read Collection<string, array<string, mixed>> $matches
 * @property-read list<array<string, mixed>> $groups
 * @property-read array<string, string> $states
 * @property-read array<string, string> $categories
 * @property-read bool $isCompany
 * @property-read bool $manages
 * @property-read Collection<int, array<string, mixed>> $recommendations
 */
class EcosystemWidget extends Component
{
    public ?string $launching = null;

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'category', except: '')]
    public string $category = '';

    #[Url(as: 'state', except: '')]
    public string $state = '';

    private ?CompanyCatalogue $catalogue = null;

    private function catalogue(): CompanyCatalogue
    {
        return $this->catalogue ??= CompanyCatalogue::shared();
    }

    /** @return Collection<string, array<string, mixed>> every platform the company may be shown */
    #[Computed]
    public function rows(): Collection
    {
        $user = Auth::user();

        return $this->catalogue()->rows($user, CompanyCatalogue::teamOf($user));
    }

    /** @return Collection<string, array<string, mixed>> the rows left after the search and the two filters */
    #[Computed]
    public function matches(): Collection
    {
        $words = array_values(array_filter(explode(' ', Str::squish(Str::lower(Str::limit($this->q, 80, ''))))));

        return $this->rows->filter(fn (array $row): bool => $this->passes($row, $words));
    }

    /**
     * Whether a row is left after the category filter, the state filter and the search. Every word typed must be
     * found somewhere in what the catalogue says about the platform: its name, its purpose, what it does, who it is for.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $words
     */
    private function passes(array $row, array $words): bool
    {
        if ($this->category !== '' && ! in_array($this->category, $row['categories'], true)) {
            return false;
        }

        if ($this->state !== '' && $row['state'] !== $this->state) {
            return false;
        }

        if ($words === []) {
            return true;
        }

        $entry = $row['entry'];
        $haystack = Str::lower(implode(' ', array_merge(
            [$row['name'], $row['tagline'], (string) $entry->description, (string) $entry->benefit, (string) $entry->audience],
            (array) $entry->capabilities,
            array_map(fn (string $c) => PlatformCategories::ALL[$c], $row['categories']),
        )));

        return collect($words)->every(fn (string $word) => str_contains($haystack, $word));
    }

    /**
     * Where a row is listed: with the company's own when it is held, otherwise under its first category.
     *
     * @param  array<string, mixed>  $row
     */
    private static function sits(array $row, ?string $category): bool
    {
        return $category === null ? $row['held'] === true : $row['held'] !== true && $row['category'] === $category;
    }

    /**
     * The page's sections: what the company holds first, then one section per catalogue category that has something
     * to show. A platform is listed once, under the first category it names.
     *
     * @return list<array{id: string, name: string, code: string, tone: string, rows: Collection<string, array<string, mixed>>}>
     */
    #[Computed]
    public function groups(): array
    {
        $matches = $this->matches;
        $groups = [];

        if (($held = $matches->filter(fn (array $row): bool => self::sits($row, null)))->isNotEmpty()) {
            $groups[] = ['id' => 'yours', 'name' => $this->isCompany ? "Your company's platforms" : 'In your personal workspace', 'code' => 'YOURS', 'tone' => 'platforms', 'rows' => $held];
        }

        foreach (PlatformCategories::ALL as $key => $name) {
            $rows = $matches->filter(fn (array $row): bool => self::sits($row, $key));

            if ($rows->isNotEmpty()) {
                [$code, $tone] = PlatformCategories::PLATES[$key];
                $groups[] = ['id' => str_replace('_', '-', $key), 'name' => $name, 'code' => $code, 'tone' => $tone, 'rows' => $rows];
            }
        }

        return $groups;
    }

    /** @return array<string, string> the states that occur for this company, in the words shown */
    #[Computed]
    public function states(): array
    {
        return $this->rows->pluck('state')->unique()
            ->sortBy(fn (string $state) => array_search($state, array_keys(CompanyCatalogue::LABELS), true))
            ->mapWithKeys(fn (string $state) => [$state => CompanyCatalogue::LABELS[$state] ?? Str::headline($state)])->all();
    }

    /** @return array<string, string> the categories that hold at least one platform */
    #[Computed]
    public function categories(): array
    {
        $used = $this->rows->flatMap(fn (array $row) => $row['categories'])->unique()->all();

        return array_filter(PlatformCategories::ALL, fn (string $key) => in_array($key, $used, true), ARRAY_FILTER_USE_KEY);
    }

    #[Computed]
    public function isCompany(): bool
    {
        $team = CompanyCatalogue::teamOf(Auth::user());

        return $team->exists && ! $team->personal_team;
    }

    #[Computed]
    public function manages(): bool
    {
        $user = Auth::user();

        return $this->isCompany && $user->can('managePlatforms', CompanyCatalogue::teamOf($user));
    }

    /** @return Collection<int, array{key: string, reason: string, row: array<string, mixed>}> shown to the people who can act on them */
    #[Computed]
    public function recommendations(): Collection
    {
        $user = Auth::user();

        return $this->manages ? app(PlatformRecommendations::class)->for($user, CompanyCatalogue::teamOf($user), $this->catalogue()) : collect();
    }

    public function clearFilters(): void
    {
        $this->reset('q', 'category', 'state');
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
        // A filter that names nothing real is dropped, so a hand-made address cannot leave the page stuck on an empty list.
        if ($this->category !== '' && ! isset(PlatformCategories::ALL[$this->category])) {
            $this->category = '';
        }

        if ($this->state !== '' && ! isset(CompanyCatalogue::LABELS[$this->state])) {
            $this->state = '';
        }

        return view('livewire.ecosystem-widget');
    }
}

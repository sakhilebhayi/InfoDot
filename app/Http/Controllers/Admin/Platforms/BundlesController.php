<?php

namespace App\Http\Controllers\Admin\Platforms;

use App\Http\Controllers\Admin\Platforms\Concerns\ActsAsAdministrator;
use App\Http\Controllers\Controller;
use App\Models\PlatformBundle;
use App\Models\PlatformCatalogue;
use App\Models\PlatformCatalogueEvent;
use App\Models\PlatformEntitlement;
use App\Services\Platforms\CatalogueAdmin;
use App\Services\Platforms\EntitlementRefused;
use App\Support\EcosystemHandoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bundles in the admin centre (R-50 section 9): named sets of platforms sold at one price, under the same rule as a
 * plan. A bundle is on sale only after an administrator approves the price it carries, and changing the price or
 * what the bundle holds withdraws that approval.
 */
class BundlesController extends Controller
{
    use ActsAsAdministrator;

    public function __construct(private readonly CatalogueAdmin $catalogue) {}

    public function index(): View
    {
        return view('admin.platforms.bundles', [
            'bundles' => PlatformBundle::with('items')->orderBy('name')->get(),
            'choices' => $this->choices(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$values, $keys, $reason] = $this->input($request);

        try {
            $bundle = $this->catalogue->createBundle($values, $keys, $request->user(), $reason);
        } catch (EntitlementRefused $e) {
            return redirect()->route('admin.platforms.bundles')->withInput()->withErrors(['refused' => $e->getMessage()]);
        }

        return redirect()->route('admin.platforms.bundle', $bundle)->with('status', 'The bundle was created. It is not on sale: a price is sold only after it is approved below.');
    }

    public function show(PlatformBundle $bundle): View
    {
        return view('admin.platforms.bundle', [
            'bundle' => $bundle->load('items'),
            'choices' => $this->choices(),
            'holders' => PlatformEntitlement::where('bundle_id', $bundle->id)->distinct()->count('team_id'),
            'events' => PlatformCatalogueEvent::with('actor')->where('subject_type', 'bundle')->where('subject_key', $bundle->slug)->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function update(Request $request, PlatformBundle $bundle): RedirectResponse
    {
        [$values, $keys, $reason] = $this->input($request);

        return $this->attempt(redirect()->route('admin.platforms.bundle', $bundle), function () use ($bundle, $values, $keys, $reason, $request) {
            $wasApproved = $bundle->approved_at !== null;
            $bundle = $this->catalogue->updateBundle($bundle, $values, $keys, $request->user(), $reason);

            return $wasApproved && $bundle->approved_at === null
                ? 'The bundle was saved. Its price or its contents changed, so the approval was withdrawn and it is off sale until approved again.'
                : 'The bundle was saved.';
        });
    }

    public function decide(Request $request, PlatformBundle $bundle, string $decision): RedirectResponse
    {
        $data = $request->validate(['reason' => self::REASON, 'confirm' => $decision === 'approve' ? ['accepted'] : ['nullable']], self::REASON_MESSAGES + ['confirm.accepted' => 'Tick the box to confirm you approve this exact price.']);

        return $this->attempt(redirect()->route('admin.platforms.bundle', $bundle), function () use ($bundle, $decision, $data, $request) {
            if ($decision === 'approve') {
                $this->catalogue->approveBundle($bundle, $request->user(), $data['reason']);

                return 'The price is approved.'.($bundle->fresh()?->active ? ' The bundle is on sale.' : ' The bundle is not switched on, so it is not on sale yet.');
            }

            $this->catalogue->withdrawBundleApproval($bundle, $request->user(), $data['reason']);

            return 'The approval was withdrawn. The bundle is off sale; companies that already hold it keep what they have.';
        });
    }

    /** The platforms a bundle may hold: in the catalogue, not core, and switched on from here. @return Collection<int, PlatformCatalogue> */
    private function choices(): Collection
    {
        return PlatformCatalogue::query()->where('is_core', false)->orderBy('sort')->orderBy('name')->get()
            ->reject(fn (PlatformCatalogue $entry) => config('ecosystem.platforms.'.$entry->key) === null || EcosystemHandoff::hasNoReceiver($entry->key))->values();
    }

    /** @return array{0: array<string, mixed>, 1: list<string>, 2: string} */
    private function input(Request $request): array
    {
        $request->merge(['currency' => $request->filled('currency') ? strtoupper(trim((string) $request->input('currency'))) : null]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'interval' => ['nullable', Rule::in(CatalogueAdmin::INTERVALS)],
            'amount_minor' => ['nullable', 'integer', 'min:1', 'max:999999999999'],
            'currency' => ['nullable', 'regex:/\A[A-Z]{3}\z/'],
            'active' => ['nullable', 'boolean'],
            'platforms' => ['required', 'array', 'min:2', 'max:40'],
            'platforms.*' => ['string', 'max:40'],
            'reason' => self::REASON,
        ], self::REASON_MESSAGES + ['platforms.required' => 'Choose at least two platforms for the bundle.', 'platforms.min' => 'Choose at least two platforms for the bundle.', 'currency.regex' => 'The currency is a three-letter code.']);

        return [[
            'name' => $data['name'], 'description' => $data['description'] ?? null, 'interval' => $data['interval'] ?? null,
            'amount_minor' => isset($data['amount_minor']) ? (int) $data['amount_minor'] : null, 'currency' => $data['currency'] ?? null, 'active' => (bool) ($data['active'] ?? false),
        ], array_values($data['platforms']), $data['reason']];
    }
}

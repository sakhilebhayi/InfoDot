<?php

namespace App\Http\Controllers\Admin\Platforms;

use App\Http\Controllers\Admin\Platforms\Concerns\ActsAsAdministrator;
use App\Http\Controllers\Controller;
use App\Models\PlatformCatalogue;
use App\Models\PlatformCatalogueEvent;
use App\Models\PlatformEntitlement;
use App\Models\PlatformPlan;
use App\Services\Platforms\CatalogueAdmin;
use App\Services\Platforms\EntitlementRefused;
use App\Support\EcosystemHandoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The admin centre's catalogue (R-50 section 9), for platform administrators (route middleware): what each platform
 * is, whether it is working, whether businesses are shown it, and the plans it is sold on. Every write goes through
 * CatalogueAdmin, which asks the gate again and keeps the reason.
 */
class CatalogueController extends Controller
{
    use ActsAsAdministrator;

    public function __construct(private readonly CatalogueAdmin $catalogue) {}

    public function index(): View
    {
        $plans = PlatformPlan::query()->get()->groupBy('platform_key');
        $held = PlatformEntitlement::query()->whereIn('status', [PlatformEntitlement::ACTIVE, PlatformEntitlement::TRIAL, PlatformEntitlement::PAYMENT_REQUIRED])
            ->selectRaw('platform_key, count(*) as n')->groupBy('platform_key')->pluck('n', 'platform_key');

        return view('admin.platforms.index', [
            'entries' => PlatformCatalogue::query()->orderBy('sort')->orderBy('name')->get(),
            'plans' => $plans,
            'held' => $held,
            'registry' => array_map('strval', array_keys((array) config('ecosystem.platforms', []))),
        ]);
    }

    public function show(PlatformCatalogue $entry): View
    {
        $plans = PlatformPlan::where('platform_key', $entry->key)->orderBy('name')->get();

        return view('admin.platforms.platform', [
            'entry' => $entry,
            'plans' => $plans,
            'ownSignIn' => EcosystemHandoff::hasNoReceiver($entry->key),
            'events' => PlatformCatalogueEvent::with('actor')
                ->where(fn ($w) => $w->where(fn ($q) => $q->where('subject_type', 'platform')->where('subject_key', $entry->key))
                    ->orWhere(fn ($q) => $q->where('subject_type', 'plan')->where('subject_key', 'like', $entry->key.'/%')))
                ->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function update(Request $request, PlatformCatalogue $entry): RedirectResponse
    {
        $data = $request->validate([
            'operational_status' => ['required', Rule::in(PlatformCatalogue::STATUSES)],
            'discoverable' => ['nullable', 'boolean'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'benefit' => ['nullable', 'string', 'max:1000'],
            'audience' => ['nullable', 'string', 'max:1000'],
            'reason' => self::REASON,
        ], self::REASON_MESSAGES);

        $values = ['discoverable' => $entry->is_core ? true : (bool) ($data['discoverable'] ?? false)] + collect($data)->except('reason', 'discoverable')->all();

        return $this->attempt(redirect()->route('admin.platforms.platform', $entry), function () use ($entry, $values, $data, $request) {
            $before = $entry->updated_at;
            $entry = $this->catalogue->updatePlatform($entry, $values, $request->user(), $data['reason']);

            return $entry->updated_at == $before ? 'Nothing was different, so nothing was changed.' : $entry->name.' was updated, and the change is in the audit trail.';
        });
    }

    public function storePlan(Request $request, PlatformCatalogue $entry): RedirectResponse
    {
        $data = $this->planInput($request);

        try {
            $plan = $this->catalogue->createPlan($entry->key, collect($data)->except('reason')->all(), $request->user(), $data['reason']);
        } catch (EntitlementRefused $e) {
            return redirect()->route('admin.platforms.platform', $entry)->withInput()->withErrors(['refused' => $e->getMessage()]);
        }

        return redirect()->route('admin.platforms.plan', $plan)->with('status', 'The plan was created. It is not on sale: a price is sold only after it is approved below.');
    }

    public function plan(PlatformPlan $plan): View
    {
        return view('admin.platforms.plan', [
            'plan' => $plan,
            'entry' => PlatformCatalogue::where('key', $plan->platform_key)->first(),
            'holders' => PlatformEntitlement::where('plan_id', $plan->id)->count(),
            'events' => PlatformCatalogueEvent::with('actor')->where('subject_type', 'plan')->where('subject_key', $plan->platform_key.'/'.$plan->slug)->orderByDesc('id')->limit(30)->get(),
        ]);
    }

    public function updatePlan(Request $request, PlatformPlan $plan): RedirectResponse
    {
        $data = $this->planInput($request);

        return $this->attempt(redirect()->route('admin.platforms.plan', $plan), function () use ($plan, $data, $request) {
            $wasApproved = $plan->approved_at !== null;
            $plan = $this->catalogue->updatePlan($plan, collect($data)->except('reason')->all(), $request->user(), $data['reason']);

            return $wasApproved && $plan->approved_at === null
                ? 'The plan was saved. Its price changed, so the approval was withdrawn and the plan is off sale until the new price is approved.'
                : 'The plan was saved.';
        });
    }

    public function decidePlan(Request $request, PlatformPlan $plan, string $decision): RedirectResponse
    {
        $data = $request->validate(['reason' => self::REASON, 'confirm' => $decision === 'approve' ? ['accepted'] : ['nullable']], self::REASON_MESSAGES + ['confirm.accepted' => 'Tick the box to confirm you approve this exact price.']);

        return $this->attempt(redirect()->route('admin.platforms.plan', $plan), function () use ($plan, $decision, $data, $request) {
            if ($decision === 'approve') {
                $this->catalogue->approvePlan($plan, $request->user(), $data['reason']);

                return 'The price is approved.'.($plan->fresh()?->isSellable() ? ' The plan is on sale.' : ' The plan is not switched on, so it is not on sale yet.');
            }

            $this->catalogue->withdrawPlanApproval($plan, $request->user(), $data['reason']);

            return 'The approval was withdrawn. The plan is off sale; companies that already hold it keep what they have.';
        });
    }

    /** @return array<string, mixed> */
    private function planInput(Request $request): array
    {
        $request->merge(['currency' => $request->filled('currency') ? strtoupper(trim((string) $request->input('currency'))) : null]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'model' => ['required', Rule::in(CatalogueAdmin::MODELS)],
            'interval' => ['nullable', Rule::in(CatalogueAdmin::INTERVALS)],
            'amount_minor' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'currency' => ['nullable', 'regex:/\A[A-Z]{3}\z/'],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('platform_subscriptions.max_trial_days')],
            'active' => ['nullable', 'boolean'],
            'reason' => self::REASON,
        ], self::REASON_MESSAGES + ['currency.regex' => 'The currency is a three-letter code, such as the one on your invoices.']);

        return [
            'name' => $data['name'], 'model' => $data['model'], 'interval' => $data['interval'] ?? null,
            'amount_minor' => isset($data['amount_minor']) ? (int) $data['amount_minor'] : null, 'currency' => $data['currency'] ?? null,
            'trial_days' => isset($data['trial_days']) ? (int) $data['trial_days'] : null, 'active' => (bool) ($data['active'] ?? false), 'reason' => $data['reason'],
        ];
    }
}

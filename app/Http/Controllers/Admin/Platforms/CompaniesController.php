<?php

namespace App\Http\Controllers\Admin\Platforms;

use App\Http\Controllers\Admin\Platforms\Concerns\ActsAsAdministrator;
use App\Http\Controllers\Controller;
use App\Models\PlatformCatalogue;
use App\Models\PlatformEntitlement;
use App\Models\PlatformEntitlementEvent;
use App\Models\PlatformPlan;
use App\Models\Team;
use App\Services\Platforms\Entitlements;
use App\Support\EcosystemHandoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A company's platforms in the admin centre (R-50 section 9): find a company, read what it holds and the history,
 * and grant complimentary access, start a trial, suspend, restore, cancel or change who may use a platform. Every
 * action is one call to Entitlements, which checks the role again, writes the event with the actor and the reason,
 * and queues the message to the platform. Nothing here edits a row.
 */
class CompaniesController extends Controller
{
    use ActsAsAdministrator;

    private const RECENT = 15;

    private const PER_PAGE = 20;

    public function __construct(private readonly Entitlements $entitlements) {}

    public function index(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $q = trim((string) ($data['q'] ?? ''));
        $searching = mb_strlen($q) >= 2;

        $companies = Team::query()->where('personal_team', false)->with('owner')->withCount('users');

        if ($searching) {
            // Matched literally: % and _ are not wildcards here ("!" escapes them on MySQL and SQLite alike).
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
            $companies = $companies->where(fn ($w) => $w->whereRaw("name like ? escape '!'", [$like])->orWhereRaw("code like ? escape '!'", [$like])->orWhere('uuid', $q))
                ->orderBy('name')->orderBy('id')->paginate(self::PER_PAGE)->withQueryString();
        } else {
            // Without a search: the companies whose platforms changed most recently, not everyone.
            $recent = PlatformEntitlement::query()->selectRaw('team_id, max(updated_at) as changed_at')->groupBy('team_id')->orderByDesc('changed_at')->limit(self::RECENT)->pluck('team_id');
            $companies = $companies->whereIn('id', $recent)->get()->sortBy(fn (Team $team) => $recent->search($team->id))->values();
        }

        $ids = $searching ? collect($companies->items())->pluck('id') : $companies->pluck('id');

        return view('admin.platforms.companies', [
            'q' => $q,
            'searching' => $searching,
            'companies' => $companies,
            'held' => PlatformEntitlement::whereIn('team_id', $ids)->get()->groupBy('team_id'),
        ]);
    }

    public function show(Request $request, Team $team): View
    {
        abort_if($team->personal_team, 404);

        $entitlements = PlatformEntitlement::where('team_id', $team->id)->orderBy('platform_key')->get()->keyBy('platform_key');
        $catalogue = PlatformCatalogue::query()->orderBy('sort')->orderBy('name')->get()->keyBy('key');
        $selected = $entitlements->get((string) $request->query('platform'));

        return view('admin.platforms.company', [
            'team' => $team->load('owner')->loadCount('users'),
            'entitlements' => $entitlements,
            'catalogue' => $catalogue,
            'core' => $catalogue->where('is_core', true),
            // What an administrator can give: in the catalogue and shown, not core, launched, and switched on from here.
            'grantable' => $catalogue->filter(fn (PlatformCatalogue $entry) => ! $entry->is_core && $entry->discoverable && ! $entry->isNotLaunched()
                && config('ecosystem.platforms.'.$entry->key) !== null && ! EcosystemHandoff::hasNoReceiver($entry->key)),
            'trialPlans' => PlatformPlan::whereNotNull('trial_days')->orderBy('platform_key')->orderBy('name')->get()->filter->isSellable()->values(),
            'selected' => $selected,
            'events' => PlatformEntitlementEvent::with('actor')->where('team_id', $team->id)->orderByDesc('id')->limit(60)->get(),
        ]);
    }

    public function act(Request $request, Team $team, string $action): RedirectResponse
    {
        abort_if($team->personal_team, 404);

        $data = $request->validate([
            'platform' => ['required', 'string', 'max:40', Rule::exists('platform_catalogue', 'key')],
            'reason' => self::REASON,
            'until' => ['nullable', 'date', 'after:today', 'before:'.now()->addYears(5)->toDateString()],
            'days' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('platform_subscriptions.max_trial_days')],
            'plan' => ['nullable', 'integer'],
            'mode' => [Rule::requiredIf($action === 'access'), 'nullable', Rule::in([PlatformEntitlement::EVERYONE, PlatformEntitlement::SELECTED])],
            'confirm' => in_array($action, ['suspend', 'cancel'], true) ? ['accepted'] : ['nullable'],
        ], self::REASON_MESSAGES + ['confirm.accepted' => 'Tick the box to confirm you mean to do this.', 'until.after' => 'The end date must be after today.']);

        $key = (string) $data['platform'];
        $name = (string) PlatformCatalogue::where('key', $key)->value('name');
        $admin = $request->user();
        $reason = (string) $data['reason'];

        return $this->attempt(redirect()->route('admin.platforms.company', ['team' => $team, 'platform' => $key]), function () use ($action, $team, $key, $name, $admin, $reason, $data) {
            switch ($action) {
                case 'grant':
                    $until = isset($data['until']) ? Carbon::parse((string) $data['until'])->endOfDay() : null;
                    $this->entitlements->grantComplimentary($team, $key, $admin, $until, $reason);

                    return $team->name.' now has '.$name.' at no charge'.($until ? ' until '.$until->format('j M Y').'.' : ', with no end date.');
                case 'trial':
                    $plan = isset($data['plan']) ? PlatformPlan::find((int) $data['plan']) : null;
                    $entitlement = $this->entitlements->startTrial($team, $key, $admin, $plan, isset($data['days']) ? (int) $data['days'] : null, $reason);

                    return $team->name.' has a trial of '.$name.' until '.$entitlement->trial_ends_at?->format('j M Y, H:i').'.';
                case 'suspend':
                    $this->entitlements->suspend($team, $key, $admin, $reason);

                    return $name.' is suspended for '.$team->name.'. Nobody in the company can open it until it is restored.';
                case 'restore':
                    $entitlement = $this->entitlements->restore($team, $key, $admin, $reason);

                    return $name.' is restored for '.$team->name.' and is '.str_replace('_', ' ', $entitlement->status).' again.';
                case 'cancel':
                    $entitlement = $this->entitlements->cancel($team, $key, $admin, $reason);

                    return $name.' is cancelled for '.$team->name.($entitlement->grants() ? '. Access continues to the end of the period already paid for.' : '. Access has ended; the platform keeps the company\'s data.');
                default:
                    $this->entitlements->administerAccessMode($team, $key, (string) $data['mode'], $admin, $reason);

                    return $name.' is now for '.($data['mode'] === PlatformEntitlement::SELECTED ? 'selected people in '.$team->name.' (owners and administrators always).' : 'everyone in '.$team->name.'.');
            }
        });
    }
}

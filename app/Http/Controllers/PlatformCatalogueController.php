<?php

namespace App\Http\Controllers;

use App\Models\PlatformEntitlement;
use App\Models\PlatformEntitlementEvent;
use App\Models\PlatformUserAccess;
use App\Models\Team;
use App\Models\User;
use App\Services\Platforms\CompanyCatalogue;
use App\Services\Platforms\EntitlementRefused;
use App\Services\Platforms\Entitlements;
use App\Services\Platforms\PlatformRecommendations;
use App\Support\EcosystemHandoff;
use App\Support\Input;
use App\Support\TeamRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * One platform as the person's current company sees it, and what the company can do about it from the catalogue
 * (R-50 section 7): switch a free or unpriced platform on, switch it off again, start the one trial a priced plan
 * offers, and choose who in the company may open it. Every action is for the person's current company and nothing else: no company is named in the address,
 * and the company a form was drawn for is only compared with the current one, so a stale tab or a forged form
 * cannot act for a different company. Who may do what is TeamPolicy (managePlatforms, cancelPlatforms), checked here
 * and again inside the Entitlements service that writes the change, its history and its outbox message.
 */
class PlatformCatalogueController extends Controller
{
    public function show(Request $request, string $platform): View
    {
        [$user, $team, $row] = $this->resolve($request, $platform);
        $company = $team->exists && ! $team->personal_team;
        $manages = $company && $user->can('managePlatforms', $team);
        $entitlement = $row['entitlement'];
        $selected = $company && $entitlement !== null
            ? PlatformUserAccess::where('team_id', $team->id)->where('platform_key', $platform)->pluck('user_id')->all()
            : [];

        return view('platforms.show', [
            'row' => $row,
            'entry' => $row['entry'],
            'team' => $team,
            'isCompany' => $company,
            'manages' => $manages,
            'owns' => $company && $user->can('cancelPlatforms', $team),
            'canSwitchOff' => $company && $user->can('cancelPlatforms', $team) && $this->switchable($entitlement),
            'plans' => CompanyCatalogue::shared()->plans($platform),
            'trialPlans' => $row['state'] === 'trial_available' ? CompanyCatalogue::shared()->trialPlans($team, $platform) : collect(),
            'connections' => collect((array) $row['entry']->connects_with)->map(fn ($key) => $key === 'infodot' ? null : CompanyCatalogue::shared()->find($user, $team, (string) $key))->filter()->values(),
            'people' => $manages && $entitlement !== null ? $this->peopleOf($team) : collect(),
            'selected' => $selected,
            'youAreSelected' => in_array($user->id, $selected, true),
            'history' => $manages ? $this->history($team, $platform) : collect(),
            'financeUrl' => $row['action'] === 'finance' ? EcosystemHandoff::launchUrl($user, 'finance') : null,
        ]);
    }

    public function confirm(Request $request, string $platform): View|RedirectResponse
    {
        [$user, $team, $row] = $this->resolve($request, $platform);
        $off = $request->routeIs('platforms.switch-off.confirm');

        abort_unless($team->exists && $user->can($off ? 'cancelPlatforms' : 'managePlatforms', $team), 403);

        // Nothing to confirm when the state has moved on (already on, already off, or not something a company switches itself).
        if ($off ? ! $this->switchable($row['entitlement']) : ! in_array($row['state'], CompanyCatalogue::SWITCHABLE, true)) {
            return redirect()->route('platforms.show', ['platform' => $platform]);
        }

        return view('platforms.confirm', [
            'row' => $row,
            'team' => $team,
            'off' => $off,
            'owns' => $user->ownsTeam($team),
            'ownerName' => trim((string) $team->owner?->name),
            'headcount' => 1 + $team->users()->where('users.id', '!=', $team->user_id)->count(),
        ]);
    }

    public function switchOn(Request $request, string $platform, Entitlements $entitlements): RedirectResponse
    {
        [$user, $team, $row] = $this->resolve($request, $platform);
        $this->sameCompany($request, $team);
        abort_unless($user->can('managePlatforms', $team), 403);

        try {
            $entitlements->activate($team, $platform, $user);
        } catch (EntitlementRefused $e) {
            return redirect()->route('platforms.show', ['platform' => $platform])->with('status', $e->getMessage());
        }

        return redirect()->route('platforms.show', ['platform' => $platform])->with('status', "{$row['name']} is switched on for {$team->name}. Everyone in the company can open it now.");
    }

    public function switchOff(Request $request, string $platform, Entitlements $entitlements): RedirectResponse
    {
        [$user, $team, $row] = $this->resolve($request, $platform);
        $this->sameCompany($request, $team);
        abort_unless($user->can('cancelPlatforms', $team), 403);

        try {
            $entitlements->deactivate($team, $platform, $user);
        } catch (EntitlementRefused $e) {
            return redirect()->route('platforms.show', ['platform' => $platform])->with('status', $e->getMessage());
        }

        return redirect()->route('platforms.show', ['platform' => $platform])->with('status', "{$row['name']} is switched off for {$team->name}. Nothing was deleted.");
    }

    /**
     * The page that says what a trial is before it starts: the plan, the date it ends, and that nothing is charged
     * when it does. Owner or administrator, and only while the company has not had its trial of this platform.
     */
    public function trialConfirm(Request $request, string $platform): View|RedirectResponse
    {
        [$user, $team, $row] = $this->resolve($request, $platform);

        abort_unless($team->exists && $user->can('managePlatforms', $team), 403);

        $plans = $row['state'] === 'trial_available' ? CompanyCatalogue::shared()->trialPlans($team, $platform) : collect();

        if ($plans->isEmpty()) {
            return redirect()->route('platforms.show', ['platform' => $platform]);
        }

        return view('platforms.trial', [
            'row' => $row,
            'team' => $team,
            'plans' => $plans,
            'chosen' => $plans->firstWhere('slug', Input::text($request->query('plan'))) ?? $plans->first(),
            'owns' => $user->ownsTeam($team),
            'headcount' => 1 + $team->users()->where('users.id', '!=', $team->user_id)->count(),
        ]);
    }

    /** Start the trial that was confirmed. The Entitlements service checks everything again and allows one per company. */
    public function startTrial(Request $request, string $platform, Entitlements $entitlements): RedirectResponse
    {
        [$user, $team, $row] = $this->resolve($request, $platform);
        $this->sameCompany($request, $team);
        abort_unless($user->can('managePlatforms', $team), 403);

        // The plan is named by its slug and looked up among this platform's trial plans only: nothing else can be trialled from here.
        $plan = CompanyCatalogue::shared()->trialPlans($team, $platform)->firstWhere('slug', Input::text($request->input('plan')));

        try {
            if ($plan === null) {
                throw new EntitlementRefused($row['state'] === 'trial_available' ? 'Choose the plan to try.' : "{$row['name']} has no trial that {$team->name} can start.");
            }

            $entitlement = $entitlements->startTrial($team, $platform, $user, $plan);
        } catch (EntitlementRefused $e) {
            return redirect()->route('platforms.show', ['platform' => $platform])->with('status', $e->getMessage().' Nothing was changed and nothing was charged.');
        }

        return redirect()->route('platforms.show', ['platform' => $platform])
            ->with('status', "The trial of {$row['name']} has started for {$team->name}. It ends on ".$entitlement->trial_ends_at?->format('j F Y').', and nothing is charged when it does.');
    }

    /** Everyone in the company, or the people ticked. Owners and administrators can always open it and are never listed. */
    public function people(Request $request, string $platform, Entitlements $entitlements): RedirectResponse
    {
        [$user, $team, $row] = $this->resolve($request, $platform);
        $this->sameCompany($request, $team);
        abort_unless($user->can('managePlatforms', $team), 403);

        $mode = Input::text($request->input('access_mode'));
        abort_unless(in_array($mode, [PlatformEntitlement::EVERYONE, PlatformEntitlement::SELECTED], true), 422);

        $choosable = $this->roster($team)->reject(fn (User $person) => TeamRoles::manages($person, $team))->keyBy('id');
        $wanted = collect((array) $request->input('people', []))->map(fn ($id) => is_scalar($id) ? (int) $id : 0)->filter(fn (int $id) => $choosable->has($id))->unique()->values();

        try {
            $entitlements->setAccessMode($team, $platform, $mode, $user);

            // The list is only changed when it is the list that was submitted: choosing "everyone" keeps it for later.
            if ($mode === PlatformEntitlement::SELECTED) {
                $current = PlatformUserAccess::where('team_id', $team->id)->where('platform_key', $platform)->pluck('user_id');

                foreach ($wanted->diff($current) as $id) {
                    $entitlements->grantUser($team, $platform, $choosable->get($id), $user);
                }

                foreach ($current->diff($wanted) as $id) {
                    if (($person = User::find($id)) !== null) {
                        $entitlements->revokeUser($team, $platform, $person, $user);
                    }
                }
            }
        } catch (EntitlementRefused $e) {
            return redirect()->route('platforms.show', ['platform' => $platform])->with('status', $e->getMessage());
        }

        $message = $mode === PlatformEntitlement::EVERYONE
            ? "Everyone in {$team->name} can open {$row['name']}."
            : ($wanted->isEmpty() ? "Only the owner and administrators of {$team->name} can open {$row['name']} now." : "{$row['name']} is open to the owner, administrators and the ".$wanted->count().' '.($wanted->count() === 1 ? 'person' : 'people').' you chose.');

        return redirect()->to(route('platforms.show', ['platform' => $platform]).'#people')->with('status', $message);
    }

    public function dismiss(Request $request, string $platform, PlatformRecommendations $recommendations): RedirectResponse
    {
        [$user, $team] = $this->resolve($request, $platform);
        $this->sameCompany($request, $team);
        abort_unless($user->can('managePlatforms', $team), 403);

        $recommendations->dismiss($team, $platform, $user);

        return redirect()->route('ecosystem.widget')->with('status', 'That suggestion will not be shown to your company again.');
    }

    /** @return array{0: User, 1: Team, 2: array<string, mixed>} the person, their current company, and the platform as they see it */
    private function resolve(Request $request, string $platform): array
    {
        $user = $request->user();
        $team = CompanyCatalogue::teamOf($user);
        $row = CompanyCatalogue::shared()->find($user, $team, $platform);

        abort_if($row === null, 404);

        return [$user, $team, $row];
    }

    /** The form says which company it was drawn for. If the person has since switched company, nothing is changed. */
    private function sameCompany(Request $request, Team $team): void
    {
        abort_if(! $team->exists || ! hash_equals((string) $team->uuid, Input::text($request->input('company'))), 409, 'You have switched company since this page was opened. Go back and try again.');
    }

    /** Only what the company switched on for free by itself can be switched off here (the service checks this again). */
    private function switchable(?PlatformEntitlement $entitlement): bool
    {
        return $entitlement !== null && $entitlement->status === PlatformEntitlement::ACTIVE
            && in_array($entitlement->source, PlatformEntitlement::SELF_SERVICE_SOURCES, true);
    }

    /** @return Collection<int, User> everyone in the company, owner first */
    private function roster(Team $team): Collection
    {
        return $team->allUsers()->sortBy(fn (User $person) => [$person->id === $team->user_id ? 0 : 1, mb_strtolower((string) $person->name)])->values();
    }

    /** @return Collection<int, array{user: User, always: bool, role: string}> */
    private function peopleOf(Team $team): Collection
    {
        return $this->roster($team)->map(fn (User $person) => [
            'user' => $person,
            'always' => TeamRoles::manages($person, $team),
            'role' => $person->id === $team->user_id ? 'Owner' : (TeamRoles::manages($person, $team) ? 'Administrator' : ''),
        ]);
    }

    /** @return Collection<int, array<string, mixed>> this company's history for this platform, newest first: when, what happened, by whom */
    private function history(Team $team, string $platform): Collection
    {
        $events = PlatformEntitlementEvent::where('team_id', $team->id)->where('platform_key', $platform)->orderByDesc('id')->limit(100)->get();
        $actors = User::whereIn('id', $events->pluck('actor_id')->filter()->unique())->get()->keyBy('id');
        $details = fn (PlatformEntitlementEvent $event): array => (array) $event->getAttribute('details');
        $subjects = User::whereIn('uuid', $events->map(fn (PlatformEntitlementEvent $event) => $details($event)['user_uuid'] ?? null)->filter()->unique())->get()->keyBy('uuid');
        $members = $team->allUsers()->pluck('id')->all();
        $history = collect();

        foreach ($events as $event) {
            $actor = $actors->get($event->actor_id);
            $detail = $details($event);
            $person = (string) ($subjects->get((string) ($detail['user_uuid'] ?? ''))->name ?? 'someone who has since left');
            $source = $detail['source'] ?? null;

            $history->push([
                'at' => $event->created_at,
                'text' => match ($event->type) {
                    'activated' => 'Switched on'.($source === 'unpriced_free' ? ', free while pricing is being finalised' : ($source === 'free' ? ', free' : '')),
                    'deactivated' => 'Switched off',
                    'complimentary_granted' => 'Given at no charge by InfoDot',
                    'suspended' => 'Paused by InfoDot',
                    'restored' => 'Restored by InfoDot',
                    'access_mode_changed' => ($detail['to'] ?? null) === PlatformEntitlement::SELECTED ? 'Limited to selected people' : 'Opened to everyone in the company',
                    'user_granted' => "Added {$person}",
                    'user_revoked' => "Removed {$person}",
                    'complimentary_ended' => 'The period at no charge ended',
                    'trial_started' => 'Trial started',
                    'trial_ended' => 'Trial ended',
                    'subscription_requested' => 'Paid plan asked for, waiting for the payment',
                    'payment_confirmed' => 'Payment confirmed',
                    'renewal_confirmed' => 'Plan renewed',
                    'payment_failed' => 'Payment did not go through',
                    'grace_ended' => 'Access stopped, payment not settled',
                    'refunded' => 'Payment refunded, access ended',
                    'cancelled' => 'Plan cancelled',
                    'period_ended' => 'Paid period ended',
                    default => ucfirst(str_replace('_', ' ', (string) $event->type)),
                },
                // A person outside the company who acted on it is InfoDot staff: their name is not the company's to see.
                'by' => $actor !== null && in_array($actor->id, $members, true) ? (string) $actor->name : 'InfoDot',
            ]);
        }

        return $history;
    }
}

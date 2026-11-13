<?php

namespace App\Services\Platforms;

use App\Models\PlatformCatalogue;
use App\Models\PlatformEntitlement;
use App\Models\PlatformPlan;
use App\Models\Team;
use App\Models\User;
use App\Support\EcosystemHandoff;
use App\Support\PlatformCategories;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The catalogue as one company and one person see it (R-50 section 7): every platform a business may be shown, with
 * the company's state in plain words and the one action that fits. It decides nothing. The state is
 * PlatformAccess::businessState, whether the person may open it is PlatformAccess::decideFor, and who may switch
 * things on and off is TeamPolicy; this class only puts words and one action beside those answers. A price is read
 * from an approved plan row or it is not shown at all.
 *
 * A row is an array:
 *   key, name, tagline, entry (PlatformCatalogue), categories (list of keys), category (the first one),
 *   state (businessState), label, why, held (the company holds it), open (this person may open it now),
 *   action ('open' | 'switch_on' | 'finance' | 'details'), action_label, price (string|null), entitlement.
 */
class CompanyCatalogue
{
    /** The states a business can be shown, in the words used everywhere. */
    public const LABELS = [
        'included' => 'Included',
        'active' => 'Active',
        'trial' => 'Trial',
        'activation_pending' => 'Activation pending',
        'free_to_activate' => 'Free to switch on',
        'free_while_pricing_finalised' => 'Free while pricing is being finalised',
        'available_for_purchase' => 'Available for purchase',
        'trial_available' => 'Trial available',
        'payment_required' => 'Payment needed',
        'subscription_expired' => 'Subscription expired',
        'suspended' => 'Suspended',
        'temporarily_unavailable' => 'Temporarily unavailable',
        'not_launched' => 'Not launched yet',
        'managed_in_platform' => 'Managed in the platform',
        'company_required' => 'Company required',
    ];

    /** The states a company can act on by itself today: nothing is held and switching on costs nothing. */
    public const SWITCHABLE = ['free_to_activate', 'free_while_pricing_finalised'];

    /** The states in which a recommendation still makes sense: the company does not have the platform yet. */
    public const NOT_YET_HELD = ['free_to_activate', 'free_while_pricing_finalised', 'available_for_purchase', 'trial_available'];

    private PreloadedAccess $access;

    /** @var array<string, Collection<string, array<string, mixed>>> */
    private array $memo = [];

    public function __construct(?PreloadedAccess $access = null)
    {
        $this->access = $access ?? new PreloadedAccess;
    }

    /**
     * The catalogue for the request being answered: one page often asks twice (Dot Apps in the top bar, then the page
     * itself), and both should read the records once. Kept on the request, never in the container, so the next
     * request reads the records afresh.
     */
    public static function shared(): self
    {
        $request = request();
        $held = $request->attributes->get(self::class);

        if (! $held instanceof self) {
            $request->attributes->set(self::class, $held = new self);
        }

        return $held;
    }

    /** The company a page is drawn for. A person with no workspace at all is treated like a personal workspace: the free core only. */
    public static function teamOf(?User $user): Team
    {
        $team = $user?->currentTeam;

        return $team instanceof Team ? $team : (new Team)->forceFill(['personal_team' => true, 'name' => 'Your workspace']);
    }

    /**
     * Every platform a business may be shown, keyed by platform key, in catalogue order. InfoDot itself is left out:
     * the person is already in it.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function rows(User $user, Team $team): Collection
    {
        return $this->memo[$user->id.':'.($team->id ?? 0)] ??= $this->access->catalogue()
            ->filter(fn (PlatformCatalogue $entry) => $entry->discoverable && $this->access->governs($entry->key))
            ->map(fn (PlatformCatalogue $entry) => $this->row($user, $team, $entry));
    }

    /** One platform, or null when it is not one a business may be shown. */
    public function find(User $user, Team $team, string $key): ?array
    {
        return $this->rows($user, $team)->get($key);
    }

    /**
     * The platforms the company holds and this person may open now: what Dot Apps and the navigation show.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function openable(User $user, Team $team): Collection
    {
        return $this->rows($user, $team)->filter(self::heldAndOpen(...));
    }

    /** @param  array<string, mixed>  $row */
    private static function heldAndOpen(array $row): bool
    {
        return $row['held'] === true && $row['open'] === true;
    }

    /** @return array<string, mixed> */
    private function row(User $user, Team $team, PlatformCatalogue $entry): array
    {
        $key = $entry->key;
        $state = $this->access->businessState($team, $key);
        $held = $team->exists ? $this->access->companyHolds($team, $key) : (bool) $entry->is_core;
        $decision = $this->access->decideFor($user, $team->exists ? $team : null, $key);
        // A platform whose registry entry is switched off or not on a trusted host cannot be opened, whatever the records say.
        $reachable = EcosystemHandoff::endpointFor(config('ecosystem.platforms.'.$key)) !== null;
        $open = $decision->allowed && $reachable;

        // The registry's own switch: a platform that cannot be reached is shown as unavailable, whatever the company holds.
        if (! $reachable && $state !== 'not_launched') {
            $state = 'temporarily_unavailable';
        }
        $entitlement = $team->exists && ! $team->personal_team ? $this->access->entitlement($team, $key) : null;
        $manages = $team->exists && Gate::forUser($user)->check('managePlatforms', $team);
        $owns = $team->exists && ! $team->personal_team && $user->ownsTeam($team);
        $notForYou = $decision->reason === AccessDecision::NOT_PERMITTED_FOR_YOU;

        [$action, $actionLabel] = match (true) {
            $open => ['open', 'Open'],
            $manages && in_array($state, self::SWITCHABLE, true) => ['switch_on', 'Switch on'],
            $owns && in_array($state, ['payment_required', 'subscription_expired'], true) && $key !== 'finance' => ['finance', 'Open Dot.Finance'],
            default => ['details', 'See details'],
        };

        return [
            'key' => $key,
            'name' => (string) $entry->name,
            'tagline' => (string) $entry->tagline,
            'entry' => $entry,
            'categories' => array_values(array_intersect((array) $entry->getAttribute('categories'), array_keys(PlatformCategories::ALL))),
            'category' => PlatformCategories::primary($entry->getAttribute('categories')),
            'state' => $state,
            'label' => $notForYou && $state !== 'temporarily_unavailable' ? 'Active for selected people' : (self::LABELS[$state] ?? Str::headline($state)),
            'why' => $this->why($state, $entry, $team, $entitlement, $user, $manages, $owns, $notForYou),
            'held' => $held,
            'open' => $open,
            'action' => $action,
            'action_label' => $actionLabel,
            'price' => $this->price($key),
            'entitlement' => $entitlement,
        ];
    }

    /** The reason for the state, in one or two plain sentences, for this reader. */
    private function why(string $state, PlatformCatalogue $entry, Team $team, ?PlatformEntitlement $entitlement, User $user, bool $manages, bool $owns, bool $notForYou): string
    {
        $name = (string) $entry->name;
        $company = $team->personal_team || ! $team->exists ? 'your company' : (string) $team->name;
        $ask = $this->ask($team, $user);

        if ($notForYou && $state !== 'temporarily_unavailable') {
            return "{$company} uses {$name} with selected people, and you are not one of them yet. {$ask} to add you.";
        }

        return match ($state) {
            'included' => "{$name} is part of the free core. Every company has it, with nothing to switch on.",
            'active' => $this->activeBecause($name, $company, $entitlement),
            'trial' => "{$company} is trying {$name}".($entitlement?->trial_ends_at ? ' until '.$entitlement->trial_ends_at->format('j F Y') : '').'.',
            'activation_pending' => "{$company} asked for {$name}, and it opens as soon as the payment is confirmed.",
            'free_to_activate' => $manages ? "{$name} is free. You can switch it on for {$company} now." : "{$name} is free. {$ask} to switch it on.",
            'free_while_pricing_finalised' => $manages
                ? "No price has been set for {$name}, so it is free for now. You can switch it on for {$company}."
                : "No price has been set for {$name}, so it is free for now. {$ask} to switch it on.",
            'available_for_purchase' => "{$name} has a price, and paid plans cannot be started yet. Nothing can be charged from this page.",
            'trial_available' => "{$name} has a price and offers a trial. Trials cannot be started from this page yet.",
            'payment_required' => $owns ? "A payment for {$name} has not been confirmed. Payments are handled in Dot.Finance." : "A payment for {$name} has not been confirmed. {$ask} to settle it in Dot.Finance.",
            'subscription_expired' => $owns ? "{$company}'s access to {$name} has ended. Nothing was deleted. Renewing is done in Dot.Finance." : "{$company}'s access to {$name} has ended. Nothing was deleted. {$ask} to renew it.",
            'suspended' => "An InfoDot administrator has paused {$company}'s use of {$name}. Nothing was deleted. Contact support to find out why.",
            'temporarily_unavailable' => "{$name} is being worked on. Nothing is wrong with your account or your company's access.",
            'not_launched' => "{$name} is not open to businesses yet. There is nothing to switch on for now.",
            'managed_in_platform' => "{$name} has its own sign-in. Accounts and access are managed in {$name} itself, not in InfoDot.",
            'company_required' => "{$name} is for companies. A personal workspace has the free core only.",
            default => '',
        };
    }

    private function activeBecause(string $name, string $company, ?PlatformEntitlement $entitlement): string
    {
        if ($entitlement === null) {
            return "{$company} uses {$name}.";
        }

        if ($entitlement->status === PlatformEntitlement::CANCELLED && $entitlement->current_period_ends_at !== null) {
            return "{$company} cancelled {$name}. It stays open until ".$entitlement->current_period_ends_at->format('j F Y').', the end of the period already paid for.';
        }

        $until = $entitlement->ended_at !== null ? ' until '.$entitlement->ended_at->format('j F Y') : '';

        return match ($entitlement->source) {
            'unpriced_free' => "{$company} switched {$name} on. It is free while its pricing is being finalised.",
            'free' => "{$company} switched {$name} on. It is free.",
            'complimentary' => "{$name} was given to {$company} at no charge{$until}.",
            'included' => "{$name} is included for {$company}{$until}.",
            default => "{$company} uses {$name}{$until}.",
        };
    }

    /** "Ask Thandi Mokoena, who owns Northwind," or a general form when the reader is the owner or there is no company. */
    private function ask(Team $team, User $user): string
    {
        if (! $team->exists || $team->personal_team || $user->ownsTeam($team)) {
            return 'Ask the owner or an administrator of your company';
        }

        $owner = trim((string) $team->owner?->name);

        return $owner === '' ? 'Ask the owner or an administrator of your company' : "Ask {$owner}, who owns {$team->name},";
    }

    /**
     * The price to show, from approved plan rows only. Null when there is none: the page then says nothing about
     * money except that the platform is free while pricing is being finalised.
     */
    public function price(string $key): ?string
    {
        $plan = $this->access->sellablePlans($key)->first(fn (PlatformPlan $p) => ! $p->isFree());

        return $plan === null ? null : 'From '.self::priceOf($plan);
    }

    /** @return Collection<int, PlatformPlan> */
    public function plans(string $key): Collection
    {
        return $this->access->sellablePlans($key);
    }

    /** "ZAR 250.00 a month", from the plan's own amount, currency and interval. */
    public static function priceOf(PlatformPlan $plan): string
    {
        if ($plan->isFree()) {
            return 'Free';
        }

        $amount = strtoupper((string) $plan->currency).' '.number_format(((int) $plan->amount_minor) / 100, 2);
        $per = match ($plan->model) {
            'per_user' => ' per person',
            default => '',
        };
        $interval = match ($plan->interval) {
            'month' => ' a month',
            'year' => ' a year',
            default => '',
        };

        return $amount.$per.$interval;
    }

    public function access(): PreloadedAccess
    {
        return $this->access;
    }
}

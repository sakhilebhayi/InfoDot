<?php

namespace App\Services\Platforms;

use App\Models\Team;
use App\Models\User;
use App\Support\EcosystemHandoff;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Turns a refused access decision into the page a person reads (R-50 section 5): what happened, in plain words, and
 * the one next step that fits them (switch the platform on, ask the owner, open Dot.Finance, try later). Every door
 * that refuses a person in a browser answers with this same page, so a refusal never looks like an outage.
 */
class PlatformRefusal
{
    public function __construct(private readonly PlatformAccess $access) {}

    public function response(User $user, string $key, AccessDecision $decision, ?string $redirect = null, int $status = 403): Response
    {
        return response()->view('platforms.access', $this->explain($user, $key, $decision, $redirect), $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** @return array<string, mixed> */
    public function explain(User $user, string $key, AccessDecision $decision, ?string $redirect = null): array
    {
        $team = $decision->team;
        $platform = (string) ($this->access->entry($key)->name ?? config('ecosystem.platforms.'.$key.'.name', $key));
        $company = $team !== null && ! $team->personal_team ? (string) $team->name : null;
        $manages = $team !== null && Gate::forUser($user)->check('managePlatforms', $team);
        $owns = $team !== null && ! $team->personal_team && $user->ownsTeam($team);
        $offer = $this->access->offer($key);
        $owner = $this->ownerName($team, $user);
        $ask = $owner !== null ? "Ask {$owner}, who owns {$company}," : 'Ask the owner or an administrator of your company';
        $reason = (string) $decision->reason;

        $canActivate = $manages && $offer !== 'paid' && in_array($reason, [AccessDecision::NOT_ACTIVATED, AccessDecision::CANCELLED, AccessDecision::EXPIRED], true);
        $free = $offer === 'free'
            ? "{$platform} is free to switch on."
            : "{$platform} is free while its pricing is being finalised. Nothing is charged now, and nothing will be charged later unless the owner agrees to a price first.";

        [$title, $message] = match ($reason) {
            AccessDecision::NOT_ACTIVATED => [
                "{$company} has not switched on {$platform}",
                $canActivate ? "{$free} Switching it on opens it for everyone in {$company}."
                    : ($manages ? "{$platform} has a price, and paid plans cannot be started yet. Nothing has been charged." : "{$ask} to switch it on. It takes them a moment."),
            ],
            AccessDecision::CANCELLED => [
                "{$company} switched {$platform} off",
                $canActivate ? "Nothing was deleted: what the company kept in {$platform} is still there. {$free}" : "Nothing was deleted. {$ask} to switch it on again.",
            ],
            AccessDecision::EXPIRED => [
                "{$company}'s access to {$platform} has ended",
                $canActivate ? "Nothing was deleted. {$free}" : ($owns ? 'Nothing was deleted. Renewing is done in Dot.Finance.' : "Nothing was deleted. {$ask} to renew it."),
            ],
            AccessDecision::TRIAL_ENDED => [
                "The trial of {$platform} has ended",
                $owns ? "What {$company} made during the trial is kept. Choosing a plan is done in Dot.Finance." : "What {$company} made during the trial is kept. {$ask} to choose a plan.",
            ],
            AccessDecision::PAYMENT_REQUIRED => [
                "{$platform} is waiting for a payment",
                $owns ? "{$company}'s payment for {$platform} has not been confirmed. Payments are handled in Dot.Finance; the platform opens again as soon as one is confirmed." : "{$company}'s payment for {$platform} has not been confirmed. {$ask} to settle it in Dot.Finance.",
            ],
            AccessDecision::SUSPENDED => [
                "{$platform} is suspended for {$company}",
                "An InfoDot administrator has paused {$company}'s use of {$platform}. Nothing was deleted. Contact support to find out why and what happens next.",
            ],
            AccessDecision::NOT_PERMITTED_FOR_YOU => [
                "You have not been added to {$platform}",
                "{$company} uses {$platform} with selected people, and you are not one of them yet. {$ask} to add you.",
            ],
            AccessDecision::NO_PERSONAL_ACCESS => [
                "{$platform} is for companies",
                'Your personal workspace includes '.$this->coreNames().". To use {$platform}, switch to a company you belong to or create one.",
            ],
            AccessDecision::TEMPORARILY_UNAVAILABLE => [
                "{$platform} is temporarily unavailable",
                "Nothing is wrong with your account or your company's access. {$platform} is being worked on; try again later.",
            ],
            AccessDecision::NOT_LAUNCHED => ["{$platform} has not launched yet", "{$platform} is not open to businesses yet. There is nothing to switch on for now."],
            AccessDecision::NOT_A_MEMBER => ['You are not in that company', "You can open {$platform} only for a company you belong to. Switch to one of your companies and try again."],
            default => ["Sign in to open {$platform}", 'Sign in with a verified email address, then try again.'],
        };

        $toFinance = $owns && in_array($reason, [AccessDecision::PAYMENT_REQUIRED, AccessDecision::TRIAL_ENDED], true) || ($owns && ! $canActivate && $reason === AccessDecision::EXPIRED);

        return [
            'key' => $key,
            'platform' => $platform,
            'reason' => $reason,
            'title' => $title,
            'message' => $message,
            'companyUuid' => $team?->uuid,
            'canActivate' => $canActivate,
            'redirect' => EcosystemHandoff::safeRedirect($redirect),
            'financeUrl' => $toFinance ? EcosystemHandoff::launchUrl($user, 'finance') : null,
            'createCompany' => $reason === AccessDecision::NO_PERSONAL_ACCESS,
            'support' => $reason === AccessDecision::SUSPENDED,
        ];
    }

    /** The owner's name, for "ask ...": only for a company, and never when the reader is the owner. */
    private function ownerName(?Team $team, User $user): ?string
    {
        if ($team === null || $team->personal_team || $user->ownsTeam($team)) {
            return null;
        }

        $name = trim((string) $team->owner?->name);

        return $name === '' ? null : $name;
    }

    private function coreNames(): string
    {
        $names = $this->access->catalogue()->filter(fn ($entry) => $entry->is_core && $entry->key !== 'infodot')->pluck('name')->values()->all();
        $last = array_pop($names);

        return $names === [] ? (string) $last : implode(', ', $names).' and '.$last;
    }
}

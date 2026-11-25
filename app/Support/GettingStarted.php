<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;
use App\Services\Growth\OnboardingEvents;
use Illuminate\Support\Facades\DB;

/**
 * The "Getting started" block on Home (R-62): the few things a new business does first, each read from what is in the
 * database now (the company, its essential details, a colleague, a platform switched on or opened). It is about the
 * company, not the person: a profile picture and a first question are no longer steps (the account page still has
 * them). Nothing here is a ticked box that could disagree with the facts; the only stored choice is putting the
 * block away for good.
 */
final class GettingStarted
{
    /**
     * The steps for one person, in order.
     *
     * @return list<array{key: string, title: string, note: string, done: bool, url: string, action: string}>
     */
    public static function steps(User $user): array
    {
        $business = self::managedBusiness($user);
        $held = $business === null ? 0 : self::platformsHeld($business);

        return [
            self::companyStep($business),
            self::essentialsStep($business),
            self::inviteStep($business),
            self::platformStep($user, $business, $held),
            self::exploreStep($business, $held),
        ];
    }

    /**
     * What Home draws: every step while the block is not put away and something is still to do, otherwise nothing.
     *
     * @return list<array{key: string, title: string, note: string, done: bool, url: string, action: string}>
     */
    public static function stepsToShow(User $user): array
    {
        if ($user->getting_started_dismissed_at !== null) {
            return [];
        }

        $steps = self::steps($user);

        return collect($steps)->contains(fn (array $step) => ! $step['done']) ? $steps : [];
    }

    /** Whether Home shows the block: not put away, and something is still to do. */
    public static function showsFor(User $user): bool
    {
        return self::stepsToShow($user) !== [];
    }

    /**
     * Notes the first time a person is really sent to a platform. The sign-in token is deleted when the platform uses
     * it, so this is the only record the "Open a platform" step can read. Written once, and never by a page view.
     */
    public static function notePlatformOpened(User $user): void
    {
        if ($user->platform_opened_at === null) {
            $user->forceFill(['platform_opened_at' => now()])->saveQuietly();
            app(OnboardingEvents::class)->record($user, 'platform_opened');
        }
    }

    /** The business this person runs (owner or administrator): the one in view first, else the oldest. */
    public static function managedBusiness(User $user): ?Team
    {
        return $user->allTeams()
            ->filter(fn (Team $team) => ! $team->personal_team && ($user->ownsTeam($team) || $user->hasTeamRole($team, 'admin')))
            ->sortBy(fn (Team $team) => [$team->id === $user->current_team_id ? 0 : 1, $team->id])
            ->first();
    }

    /** How many platforms beyond the free core the company holds right now. */
    private static function platformsHeld(Team $business): int
    {
        return DB::table('platform_entitlements')->where('team_id', $business->id)
            ->whereIn('status', ['active', 'trial'])->where('source', '!=', 'core')->count();
    }

    /** What every later step says while there is no company to act on. */
    private const NEEDS_COMPANY = 'Needs your company first.';

    /** @return array{key: string, title: string, note: string, done: bool, url: string, action: string} */
    private static function companyStep(?Team $business): array
    {
        if ($business === null) {
            return [
                'key' => 'company',
                'title' => 'Create your company profile',
                'note' => 'Your company is what you manage on InfoDot: its page, its people and its platforms. A name and a sentence about what it does is enough.',
                'done' => false,
                'url' => route('onboarding.business'),
                'action' => 'Create your company',
            ];
        }

        return [
            'key' => 'company',
            'title' => $business->name.' is created',
            'note' => 'This is the workspace you are in. You can create another company at any time.',
            'done' => true,
            'url' => route('business.edit', $business),
            'action' => 'Edit the business page',
        ];
    }

    /** @return array{key: string, title: string, note: string, done: bool, url: string, action: string} */
    private static function essentialsStep(?Team $business): array
    {
        $step = ['key' => 'essentials', 'title' => 'Complete the essential business information'];

        if ($business === null) {
            return $step + ['note' => self::NEEDS_COMPANY, 'done' => false, 'url' => route('onboarding.business'), 'action' => 'Create your company'];
        }

        $profile = $business->profile()->first();
        $missing = array_keys(array_filter([
            'a description' => blank($profile?->description),
            'an industry' => blank($business->industry) || $business->industry === 'general',
            'a way to contact it' => blank($profile?->website) && blank($profile?->contact_email) && blank($profile?->contact_phone),
            'a logo' => ! $business->logo()->exists(),
        ]));

        return $step + [
            'note' => $missing === [] ? 'The essentials are in. Add more whenever you like.' : 'Still to add: '.implode(', ', $missing).'. Nothing else is required.',
            'done' => $missing === [],
            'url' => route('business.edit', $business),
            'action' => 'Edit the business page',
        ];
    }

    /** @return array{key: string, title: string, note: string, done: bool, url: string, action: string} */
    private static function inviteStep(?Team $business): array
    {
        $step = ['key' => 'invite', 'title' => 'Invite your team'];

        if ($business === null) {
            return $step + ['note' => self::NEEDS_COMPANY, 'done' => false, 'url' => route('onboarding.business'), 'action' => 'Create your company'];
        }

        return $step + [
            'note' => 'Colleagues and partners join '.$business->name.' and see its questions, solutions and people.',
            'done' => $business->users()->exists() || $business->teamInvitations()->exists(),
            'url' => route('teams.show', $business).'#members',
            'action' => 'Invite someone',
        ];
    }

    /** @return array{key: string, title: string, note: string, done: bool, url: string, action: string} */
    private static function platformStep(User $user, ?Team $business, int $held): array
    {
        $step = ['key' => 'platforms', 'title' => 'Activate your first Dot platforms'];

        if ($business === null) {
            return $step + ['note' => self::NEEDS_COMPANY, 'done' => false, 'url' => route('onboarding.business'), 'action' => 'Create your company'];
        }

        return $step + [
            'note' => 'We offer the first free platforms that fit your business. Switching one on is free and does not buy anything.',
            'done' => $held > 0 || $user->platform_opened_at !== null,
            'url' => route('onboarding.recommendations'),
            'action' => 'Choose your first platforms',
        ];
    }

    /** @return array{key: string, title: string, note: string, done: bool, url: string, action: string} */
    private static function exploreStep(?Team $business, int $held): array
    {
        $step = ['key' => 'explore', 'title' => 'Explore the business ecosystem'];

        if ($business === null) {
            return $step + ['note' => self::NEEDS_COMPANY, 'done' => false, 'url' => route('onboarding.business'), 'action' => 'Create your company'];
        }

        return $step + [
            'note' => 'See what else the Dot platforms can do as the business grows.',
            'done' => $held >= 2,
            'url' => route('ecosystem.widget'),
            'action' => 'See every platform',
        ];
    }
}

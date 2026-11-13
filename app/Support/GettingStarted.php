<?php

namespace App\Support;

use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;

/**
 * The "Getting started" block on Home: the few things a new person does first, each read from what is in the database
 * now (a photo on the account, a question or solution of their own, a listed business, a colleague, a platform
 * opened). Nothing here is a ticked box that could disagree with the facts; the only stored choice is putting the
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
        $steps = [
            [
                'key' => 'photo',
                'title' => 'Add a profile picture',
                'note' => 'People recognise your answers and your name on the roster.',
                'done' => filled($user->profile_photo_path),
                'url' => route('profile.edit').'#profile',
                'action' => 'Open account settings',
            ],
            [
                'key' => 'contribute',
                'title' => 'Ask a first question or write a first solution',
                'note' => 'A question gets you an answer; a solution writes down something you already worked out.',
                'done' => Questions::withoutGlobalScopes()->where('user_id', $user->id)->exists()
                    || Solutions::withoutGlobalScopes()->where('user_id', $user->id)->exists(),
                'url' => route('seek'),
                'action' => 'Ask a question',
            ],
            self::businessStep($business),
        ];

        // Inviting is offered only to someone who has a business to invite people into.
        if ($business !== null) {
            $steps[] = [
                'key' => 'invite',
                'title' => 'Invite a colleague',
                'note' => 'They join '.$business->name.' and see its questions, solutions and people.',
                'done' => $business->users()->exists() || $business->teamInvitations()->exists(),
                'url' => route('teams.show', $business).'#members',
                'action' => 'Invite someone',
            ];
        }

        $steps[] = [
            'key' => 'platform',
            'title' => 'Open a platform',
            'note' => 'One sign-in opens every live Dot platform. Pick one from the register.',
            'done' => $user->platform_opened_at !== null,
            'url' => route('ecosystem.widget'),
            'action' => 'See the platforms',
        ];

        return $steps;
    }

    /** Whether Home shows the block: not put away, and something is still to do. */
    public static function showsFor(User $user): bool
    {
        return $user->getting_started_dismissed_at === null
            && collect(self::steps($user))->contains(fn (array $step) => ! $step['done']);
    }

    /**
     * Notes the first time a person is really sent to a platform. The sign-in token is deleted when the platform uses
     * it, so this is the only record the "Open a platform" step can read. Written once, and never by a page view.
     */
    public static function notePlatformOpened(User $user): void
    {
        if ($user->platform_opened_at === null) {
            $user->forceFill(['platform_opened_at' => now()])->saveQuietly();
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

    /**
     * Creating a business and listing it are one step with three states: none yet, one that is not listed (with what
     * is missing, from Team::listing()), and listed.
     *
     * @return array{key: string, title: string, note: string, done: bool, url: string, action: string}
     */
    private static function businessStep(?Team $business): array
    {
        if ($business === null) {
            return [
                'key' => 'business',
                'title' => 'Create your business and list it',
                'note' => 'A business gets its own four-letter owner code, a page and a place in the directory.',
                'done' => false,
                'url' => route('teams.create'),
                'action' => 'Create a business',
            ];
        }

        $listing = $business->listing();
        $missing = match (true) {
            $listing['listed'] => null,
            in_array('restricted', $listing['missing'], true) => 'It stays out of the directory while InfoDot reviews it.',
            in_array('visibility', $listing['missing'], true) && in_array('description', $listing['missing'], true) => 'Not listed yet: say what the business does and choose who may see it.',
            in_array('visibility', $listing['missing'], true) => 'Not listed yet: its page is for your own team until you choose who may see it.',
            default => 'Not listed yet: say what the business does.',
        };

        return [
            'key' => 'business',
            'title' => $listing['listed'] ? $business->name.' is listed' : 'List '.$business->name.' in the directory',
            'note' => $missing ?? ($listing['audience'] === TeamProfile::PUBLIC ? 'Anyone can find it in the directory.' : 'People signed in to InfoDot can find it in the directory.'),
            'done' => $listing['listed'],
            'url' => route('business.edit', $business),
            'action' => 'Edit the business page',
        ];
    }
}

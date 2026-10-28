<?php

namespace App\Support;

use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Team;
use App\Models\Topic;
use App\Models\User;

/**
 * Where in the signed-in area the current request is: the page title and the
 * breadcrumb trail shown in the top bar.
 */
class NavSection
{
    public static function current(): string
    {
        return match (true) {
            request()->routeIs('dashboard') => 'Home',
            request()->routeIs('questions*', 'seek', 'solutions*', 'add', 'topics*', 'library', 'solution_search_results') => 'Knowledge',
            request()->routeIs('ecosystem.widget', 'subservices.*') => 'Platforms',
            request()->routeIs('analytics.*') => 'Analytics',
            request()->routeIs('people') => 'People',
            request()->routeIs('moderation*') => 'Moderation',
            request()->routeIs('points*') => 'Points',
            request()->routeIs('notices*') => 'Notifications',
            request()->routeIs('profile.*') => 'Account',
            request()->routeIs('teams.*', 'api-tokens.*') => 'Administration',
            default => 'InfoDot',
        };
    }

    /**
     * The business area in view: its container colour (data-area on the body) and the
     * four-letter code stencilled in the band, like a container's category code.
     *
     * @return array{0: string, 1: string}
     */
    public static function area(): array
    {
        return match (true) {
            request()->routeIs('dashboard') => ['home', 'GATE'],
            request()->routeIs('questions*', 'seek', 'solutions*', 'add', 'topics*', 'library', 'solution_search_results') => ['knowledge', 'KNOW'],
            request()->routeIs('ecosystem.widget', 'subservices.*') => ['platforms', 'PLAT'],
            request()->routeIs('analytics.*') => ['analytics', 'DATA'],
            request()->routeIs('people') => ['people', 'CREW'],
            request()->routeIs('moderation*') => ['people', 'MODR'],
            request()->routeIs('points*') => ['people', 'PNTS'],
            request()->routeIs('notices*') => ['people', 'NOTE'],
            request()->routeIs('profile.*') => ['people', 'ACCT'],
            request()->routeIs('teams.*', 'api-tokens.*') => ['people', 'ADMN'],
            default => ['home', 'GATE'],
        };
    }

    /**
     * Trail for the area band. The last item is the current page (no link).
     *
     * @return list<array{0: string, 1: ?string}>
     */
    public static function crumbs(): array
    {
        $knowledge = ['Knowledge', route('questions')];
        $platforms = ['Platforms', route('ecosystem.widget')];

        return match (true) {
            request()->routeIs('dashboard') => [['Home', null]],
            request()->routeIs('questions') => [$knowledge, ['Questions', null]],
            request()->routeIs('seek') => [$knowledge, ['Questions', route('questions')], ['Ask a question', null]],
            request()->routeIs('questions.view') => [$knowledge, ['Questions', route('questions')], [self::title(Questions::class, 'question', request()->route('qid')), null]],
            request()->routeIs('solutions') => [$knowledge, ['Solutions', null]],
            request()->routeIs('add') => [$knowledge, ['Solutions', route('solutions')], ['Add a solution', null]],
            request()->routeIs('solutions.view') => [$knowledge, ['Solutions', route('solutions')], [self::title(Solutions::class, 'solution_title', request()->route('id')), null]],
            request()->routeIs('ecosystem.widget') => [['Platforms', null]],
            request()->routeIs('subservices.*') => [$platforms, [self::platformName(), null]],
            request()->routeIs('analytics.*') => [['Analytics', null]],
            request()->routeIs('people') => [['People', null]],
            request()->routeIs('moderation') => [['Moderation', null]],
            request()->routeIs('moderation.topics') => [['Moderation', route('moderation')], ['Topics', null]],
            request()->routeIs('moderation.friction') => [['Moderation', route('moderation')], ['Where people struggle', null]],
            request()->routeIs('moderation.library*') => [['Moderation', route('moderation')], ['Verified library', null]],
            request()->routeIs('library') => [$knowledge, ['Library', null]],
            request()->routeIs('solution_search_results') => [$knowledge, ['Search', null]],
            request()->routeIs('topics') => [$knowledge, ['Topics', null]],
            request()->routeIs('topics.show') => [$knowledge, ['Topics', route('topics')], [self::title(Topic::class, 'name', request()->route('topic')?->getKey()), null]],
            request()->routeIs('points*') => [['Account', null], ['Dot Points', null]],
            request()->routeIs('notices*') => [['Account', null], ['Notifications', null]],
            request()->routeIs('profile.show') => [['Account', null], [self::title(User::class, 'name', request()->route('id')), null]],
            request()->routeIs('profile.edit') => [['Account', null], ['Settings', null]],
            request()->routeIs('teams.show') => [['Administration', null], ['Team settings', null]],
            request()->routeIs('teams.create') => [['Administration', null], ['New team', null]],
            request()->routeIs('api-tokens.*') => [['Administration', null], ['API tokens', null]],
            default => [['InfoDot', null]],
        };
    }

    private static function title(string $model, string $column, mixed $id): string
    {
        $value = is_scalar($id) ? $model::query()->whereKey($id)->value($column) : null;

        return filled($value) ? (string) $value : 'Details';
    }

    private static function platformName(): string
    {
        $key = str(request()->route()?->getName() ?? '')->after('subservices.')->toString();
        $key = ['sheets' => 'sheet'][$key] ?? $key;

        return (string) (config('ecosystem.platforms.'.$key.'.name') ?? 'Platform');
    }

    /** Whether the signed-in person administers their current team. */
    public static function administersTeam(?User $user): bool
    {
        $team = $user?->currentTeam;

        return $user !== null && $team instanceof Team
            && ($user->ownsTeam($team) || $user->hasTeamRole($team, 'admin'));
    }
}

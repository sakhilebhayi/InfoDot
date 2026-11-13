<?php

namespace App\Support;

use App\Models\BusinessCollection;
use App\Models\PlatformCatalogue;
use App\Models\Questions;
use App\Models\Seminar;
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
            request()->routeIs('seminars*') => 'Seminars',
            request()->routeIs('ecosystem.widget', 'platforms.*') => 'Platforms',
            request()->routeIs('analytics.*') => 'Analytics',
            request()->routeIs('people') => 'People',
            request()->routeIs('business.*', 'businesses.*') => 'Businesses',
            request()->routeIs('admin.platforms*') => 'Platform admin',
            request()->routeIs('moderation*') => 'Moderation',
            request()->routeIs('points*') => 'Points',
            request()->routeIs('notices*') => 'Notifications',
            request()->routeIs('inbox*') => 'Inbox',
            request()->routeIs('collections*') => 'Collections',
            request()->routeIs('reputation*', 'moderation.reputation*') => 'Progress',
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
            request()->routeIs('seminars*') => ['knowledge', 'SEMI'],
            request()->routeIs('ecosystem.widget', 'platforms.*') => ['platforms', 'PLAT'],
            request()->routeIs('analytics.*') => ['analytics', 'DATA'],
            request()->routeIs('people') => ['people', 'CREW'],
            request()->routeIs('business.*', 'businesses.*') => ['people', 'IDNT'],
            request()->routeIs('admin.platforms*') => ['platforms', 'CTRL'],
            request()->routeIs('moderation*') => ['people', 'MODR'],
            request()->routeIs('points*') => ['people', 'PNTS'],
            request()->routeIs('reputation*', 'moderation.reputation*') => ['people', 'PROG'],
            request()->routeIs('notices*') => ['people', 'NOTE'],
            request()->routeIs('inbox*') => ['people', 'INBX'],
            request()->routeIs('collections*') => ['people', 'SAVE'],
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

        return match (true) {
            request()->routeIs('dashboard') => [['Home', null]],
            request()->routeIs('questions') => [$knowledge, ['Questions', null]],
            request()->routeIs('seek') => [$knowledge, ['Questions', route('questions')], ['Ask a question', null]],
            request()->routeIs('questions.view') => [$knowledge, ['Questions', route('questions')], [self::title(Questions::class, 'question', request()->route('qid')), null]],
            request()->routeIs('solutions') => [$knowledge, ['Solutions', null]],
            request()->routeIs('add') => [$knowledge, ['Solutions', route('solutions')], ['Add a solution', null]],
            request()->routeIs('solutions.view') => [$knowledge, ['Solutions', route('solutions')], [self::title(Solutions::class, 'solution_title', request()->route('id')), null]],
            request()->routeIs('ecosystem.widget') => [['Platforms', null]],
            request()->routeIs('platforms.show') => [['Platforms', route('ecosystem.widget')], [self::platformName(), null]],
            request()->routeIs('platforms.*') => [['Platforms', route('ecosystem.widget')], [self::platformName(), route('platforms.show', ['platform' => (string) request()->route('platform')])], [request()->routeIs('platforms.switch-off*') ? 'Switch off' : 'Switch on', null]],
            request()->routeIs('analytics.*') => [['Analytics', null]],
            request()->routeIs('people') => [['People', null]],
            request()->routeIs('admin.platforms') => [['Platform admin', null]],
            request()->routeIs('admin.platforms.*') => [['Platform admin', route('admin.platforms')], [match (true) {
                request()->routeIs('admin.platforms.platform*', 'admin.platforms.plan*') => 'Catalogue',
                request()->routeIs('admin.platforms.bundle*') => 'Bundles',
                request()->routeIs('admin.platforms.compan*') => 'Companies',
                request()->routeIs('admin.platforms.outbox*') => 'Deliveries',
                request()->routeIs('admin.platforms.payments') => 'Confirmed payments',
                default => 'Audit trail',
            }, null]],
            request()->routeIs('moderation') => [['Moderation', null]],
            request()->routeIs('moderation.topics') => [['Moderation', route('moderation')], ['Topics', null]],
            request()->routeIs('moderation.friction') => [['Moderation', route('moderation')], ['Where people struggle', null]],
            request()->routeIs('moderation.library*') => [['Moderation', route('moderation')], ['Verified library', null]],
            request()->routeIs('library') => [$knowledge, ['Library', null]],
            request()->routeIs('seminars.show') => [$knowledge, ['Seminars', route('seminars')], [self::title(Seminar::class, 'title', request()->route('seminar')?->getKey()), null]],
            request()->routeIs('seminars*') => [$knowledge, ['Seminars', null]],
            request()->routeIs('solution_search_results') => [$knowledge, ['Search', null]],
            request()->routeIs('topics') => [$knowledge, ['Topics', null]],
            request()->routeIs('topics.show') => [$knowledge, ['Topics', route('topics')], [self::title(Topic::class, 'name', request()->route('topic')?->getKey()), null]],
            request()->routeIs('points*') => [['Account', null], ['Dot Points', null]],
            request()->routeIs('notices*') => [['Account', null], ['Notifications', null]],
            request()->routeIs('moderation.trust') => [['Moderation', route('moderation')], ['Business trust', null]],
            request()->routeIs('moderation.trust.*') => [['Moderation', route('moderation')], ['Business trust', route('moderation.trust')], ['Case', null]],
            request()->routeIs('moderation.reputation*') => [['Moderation', route('moderation')], ['Score appeals', null]],
            request()->routeIs('reputation*') => [['Account', null], ['Your progress', null]],
            request()->routeIs('profile.show') => [['Account', null], [self::title(User::class, 'name', request()->route('id')), null]],
            request()->routeIs('profile.edit') => [['Account', null], ['Settings', null]],
            request()->routeIs('businesses.index') => [['Businesses', null]],
            request()->routeIs('inbox') => [['Inbox', null]],
            request()->routeIs('collections') => [['Account', null], ['Collections', null]],
            request()->routeIs('collections.*') => [['Collections', route('collections')], [self::title(BusinessCollection::class, 'name', request()->route('collection')?->getKey()), null]],
            request()->routeIs('businesses.featured') => [['Businesses', route('businesses.index')], ['How featuring works', null]],
            request()->routeIs('business.insights') => [['Businesses', route('businesses.index')], [self::title(Team::class, 'name', request()->route('team')?->id), route('business.show', request()->route('team'))], ['Insights', null]],
            request()->routeIs('inbox.*') => [['Inbox', route('inbox')], ['Enquiry', null]],
            request()->routeIs('business.report*') => [['Businesses', route('businesses.index')], [self::title(Team::class, 'name', request()->route('team')?->id), route('business.show', request()->route('team'))], ['Report', null]],
            request()->routeIs('business.standing', 'business.appeal') => [['Businesses', route('businesses.index')], [self::title(Team::class, 'name', request()->route('team')?->id), route('business.show', request()->route('team'))], ['Standing and appeals', null]],
            request()->routeIs('business.message*') => [['Businesses', route('businesses.index')], [self::title(Team::class, 'name', request()->route('team')?->id), route('business.show', request()->route('team'))], ['Message', null]],
            request()->routeIs('business.inbox') => [['Businesses', route('businesses.index')], [self::title(Team::class, 'name', request()->route('team')?->id), route('business.show', request()->route('team'))], ['Inbox', null]],
            request()->routeIs('business.inbox.*') => [['Businesses', route('businesses.index')], [self::title(Team::class, 'name', request()->route('team')?->id), route('business.show', request()->route('team'))], ['Inbox', route('business.inbox', request()->route('team'))], ['Enquiry', null]],
            request()->routeIs('business.show') => [['Businesses', route('businesses.index')], [self::title(Team::class, 'name', request()->route('team')?->id), null]],
            request()->routeIs('business.edit', 'business.update', 'business.media', 'business.media.remove') => [['Businesses', route('businesses.index')], ['Edit profile', null]],
            request()->routeIs('teams.show') => [['Administration', null], ['Company settings', null]],
            request()->routeIs('teams.create') => [['Administration', null], ['New company', null]],
            request()->routeIs('api-tokens.*') => [['Administration', null], ['API tokens', null]],
            default => [['InfoDot', null]],
        };
    }

    private static function title(string $model, string $column, mixed $id): string
    {
        $value = is_scalar($id) ? $model::query()->whereKey($id)->value($column) : null;

        return filled($value) ? (string) $value : 'Details';
    }

    /** The catalogue name of the platform in the address, for the trail. */
    private static function platformName(): string
    {
        $key = request()->route('platform');
        $name = is_string($key) ? PlatformCatalogue::query()->where('key', $key)->value('name') : null;

        return filled($name) ? (string) $name : 'Platform';
    }

    /** Whether the signed-in person administers their current team. */
    public static function administersTeam(?User $user): bool
    {
        $team = $user?->currentTeam;

        return $user !== null && $team instanceof Team
            && ($user->ownsTeam($team) || $user->hasTeamRole($team, 'admin'));
    }
}

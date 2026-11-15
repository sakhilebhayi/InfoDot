@php
    use App\Support\EcosystemHandoff;
    use App\Support\NavSection;
    use Illuminate\Support\Facades\Auth;
    use Laravel\Jetstream\Jetstream;

    $user = Auth::user();
    $company = $user->currentTeam;
    $routeIs = fn (string ...$names) => request()->routeIs(...$names);

    // A platform opens from the bar only when it is live (the same test the old tabs used); the link is the platform launch route the
    // Platforms page and the Dot Apps menu use, so the R-50 entitlement check and its explanation apply (nothing is bypassed).
    $live = fn (string $key) => (bool) (config('ecosystem.platforms.'.$key.'.active') ?? false)
        && EcosystemHandoff::endpointFor(config('ecosystem.platforms.'.$key)) !== null;

    // R-52: the bar shows Home and three named groups (Knowledge, Network, Platforms), plus Admin for staff; every item is also in the drawer. The same array prints the drawer on a phone.
    $platformItems = array_values(array_filter([
        ['label' => 'All platforms', 'href' => route('ecosystem.widget'), 'current' => $routeIs('ecosystem.widget', 'platforms.*', 'platform.access')],
        ['label' => 'Analytics', 'href' => route('analytics.dashboard'), 'current' => $routeIs('analytics.*')],
        $live('files') ? ['label' => 'Files', 'href' => route('platform.launch', ['platform' => 'files'])] : null,
        $live('docs') ? ['label' => 'Documents', 'href' => route('platform.launch', ['platform' => 'docs'])] : null,
    ]));

    $nav = [
        ['label' => 'Home', 'href' => route('dashboard'), 'current' => $routeIs('dashboard')],
        ['label' => 'Knowledge', 'items' => [
            ['label' => 'Questions', 'href' => route('questions'), 'current' => $routeIs('questions*', 'seek')],
            ['label' => 'Solutions', 'href' => route('solutions'), 'current' => $routeIs('solutions*', 'add')],
            ['label' => 'Topics', 'href' => route('topics'), 'current' => $routeIs('topics*')],
            ['label' => 'Library', 'href' => route('library'), 'current' => $routeIs('library')],
            ['label' => 'Seminars', 'href' => route('seminars'), 'current' => $routeIs('seminars*')],
        ]],
        ['label' => 'Network', 'items' => [
            ['label' => 'Businesses', 'href' => route('businesses.index'), 'current' => $routeIs('businesses.*', 'business.show')],
            ['label' => 'People', 'href' => route('people'), 'current' => $routeIs('people', 'teams.*', 'api-tokens.*')],
        ]],
        ['label' => 'Platforms', 'items' => $platformItems],
    ];

    $staff = array_values(array_filter([
        $user->isModerator() ? ['label' => 'Moderation', 'href' => route('moderation'), 'current' => $routeIs('moderation*')] : null,
        Gate::allows('administer-platforms') ? ['label' => 'Platform admin', 'href' => route('admin.platforms'), 'current' => $routeIs('admin.platforms*')] : null,
    ]));
    if ($staff !== []) {
        $nav[] = ['label' => 'Admin', 'items' => $staff];
    }

    $inboxUnread = app(\App\Services\Enquiries\EnquiryService::class)->unreadCount($user);
    $bellUnread = app(\App\Services\Knowledge\InAppNotices::class)->unreadCount($user);

    // The account menu: the person's own places, then the company they are working in (its settings and the switch between
    // companies, which used to be a second popover beside the logo). Notifications are the bell's: they are not repeated here.
    $links = array_values(array_filter([
        ['label' => 'Profile', 'href' => route('profile.show', ['id' => $user->id]), 'current' => $routeIs('profile.show')],
        config('reputation.enabled') ? ['label' => 'Your progress', 'href' => route('reputation'), 'current' => $routeIs('reputation*')] : null,
        ['label' => 'Inbox', 'href' => route('inbox'), 'badge' => $inboxUnread > 0 ? ($inboxUnread > 99 ? '99+' : $inboxUnread) : null, 'current' => $routeIs('inbox*', 'business.inbox*')],
        ['label' => 'Collections', 'href' => route('collections'), 'current' => $routeIs('collections*')],
        ['label' => 'Points', 'href' => route('points'), 'current' => $routeIs('points*')],
        ['label' => 'Settings', 'href' => route('profile.edit'), 'current' => $routeIs('profile.edit')],
        ['rule' => true],
        ['heading' => $company?->name ?? 'Personal workspace'],
        $company && NavSection::administersTeam($user) ? ['label' => 'Company settings', 'href' => route('teams.show', $company->id), 'current' => $routeIs('teams.show')] : null,
        $company && NavSection::administersTeam($user) && Jetstream::hasApiFeatures() ? ['label' => 'API tokens', 'href' => route('api-tokens.index'), 'current' => $routeIs('api-tokens.*')] : null,
        Gate::allows('create', Jetstream::newTeamModel()) ? ['label' => 'Create a company', 'href' => route('teams.create'), 'current' => $routeIs('teams.create')] : null,
    ]));

    if ($user->allTeams()->count() > 1) {
        $links[] = ['heading' => __('Switch company')];
        foreach ($user->allTeams() as $team) {
            $links[] = ['label' => $team->name, 'current' => $user->isCurrentTeam($team), 'form' => ['action' => route('current-team.update'), 'method' => 'PUT', 'fields' => ['team_id' => $team->id]]];
        }
    }

    $account = [
        'name' => (string) $user->name,
        'email' => (string) $user->email,
        'team' => $company ? $company->name.' · '.(($company->personal_team ?? true) ? 'Personal workspace' : 'Company') : 'Personal workspace',
        'avatar' => $user->photoUrl(),
        'initials' => $user->initial(),
        'links' => $links,
        'signOut' => ['action' => route('logout'), 'label' => __('Log out')],
    ];
@endphp
<x-dot.header
    brand-href="{{ route('dashboard') }}" brand-label="InfoDot home"
    :nav="$nav" :account="$account" :context="$company?->name ?? 'Personal workspace'" skip="#main" drawer-title="Menu"
    class="ix-header">
    <x-slot:brand><x-ix.brand /></x-slot:brand>
    <x-slot:search><livewire:search /></x-slot:search>
    <x-slot:tools>
        <livewire:dot-switcher />
        <x-dot.bell :href="route('notices')" :count="$bellUnread" always />
    </x-slot:tools>
</x-dot.header>

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dot Platform Registry
    |--------------------------------------------------------------------------
    | Every platform in the Dot Ecosystem, shared verbatim across all of them
    | (see InfoDot's original config/ecosystem.php, the source of this file).
    | Used to power cross-platform "discover" surfaces (e.g. error pages)
    | without hardcoding a fixed subset -- add a platform here once and it
    | appears everywhere automatically. Update URLs per environment via the
    | matching DOT_*_URL env vars.
    |
    | `internal` marks bridge services (shared memory, AI, platform connectivity): they stay
    | here because platforms talk to them, but they are never listed or launched for a person
    | (see App\Support\Platforms).
    |
    | `active` means a real application is deployed there. Placeholders (static
    | "Coming Soon" pages) and development-only platforms are listed with
    | `active => false` so error pages do not send people to them. Defaults
    | point at the live *.infodot.co.za hosts (the old *.infodot.app domain has
    | no DNS). Verified against the 2026-10-16 ecosystem audit, section 1; flip
    | a flag when a platform really goes live.
    */

    'platforms' => [
        'infodot' => ['name' => 'InfoDot', 'url' => env('DOT_INFODOT_URL', 'https://infodot.co.za'), 'icon' => 'home', 'accent' => '#2f8fd6', 'active' => true],
        'agents' => ['name' => 'Dot.Agents', 'url' => env('DOT_AGENTS_URL', 'https://agents.infodot.co.za'), 'icon' => 'smart_toy', 'accent' => '#f1c62e', 'active' => true],
        'analytics' => ['name' => 'Dot.Analytics', 'url' => env('DOT_ANALYTICS_URL', 'https://analytics.infodot.co.za'), 'icon' => 'insights', 'accent' => '#f1c62e', 'active' => false],
        'auction' => ['name' => 'Dot.Auction', 'url' => env('DOT_AUCTION_URL', 'https://auction.infodot.co.za'), 'icon' => 'gavel', 'accent' => '#f1c62e', 'active' => false],
        'billing' => ['name' => 'Dot.Billing', 'url' => env('DOT_BILLING_URL', 'https://billing.infodot.co.za'), 'icon' => 'credit_card', 'accent' => '#f1c62e', 'active' => false],
        'central' => ['name' => 'Dot.Central', 'url' => env('DOT_CENTRAL_URL', 'https://central.infodot.co.za'), 'icon' => 'hub', 'accent' => '#e8bd3d', 'active' => true],
        'charts' => ['name' => 'Dot.Charts', 'url' => env('DOT_CHARTS_URL', 'https://charts.infodot.co.za'), 'icon' => 'candlestick_chart', 'accent' => '#f1c62e', 'active' => true],
        'design' => ['name' => 'Dot.Design', 'url' => env('DOT_DESIGN_URL', 'https://design.infodot.co.za'), 'icon' => 'palette', 'accent' => '#efb93a', 'active' => false],
        'dopemine' => ['name' => 'Dot.Dopemine', 'url' => env('DOT_DOPEMINE_URL', 'https://dopemine.infodot.co.za'), 'icon' => 'bolt', 'accent' => '#e8b923', 'active' => true, 'internal' => true],
        'ehail' => ['name' => 'Dot.Ehail', 'url' => env('DOT_EHAIL_URL', 'https://ehail.infodot.co.za'), 'icon' => 'local_taxi', 'accent' => '#e2b13d', 'active' => false],
        'emall' => ['name' => 'Dot.Emall', 'url' => env('DOT_EMALL_URL', 'https://emall.infodot.co.za'), 'icon' => 'storefront', 'accent' => '#eec13f', 'active' => false],
        'engage' => ['name' => 'Dot.Engage', 'url' => env('DOT_ENGAGE_URL', 'https://engage.infodot.co.za'), 'icon' => 'campaign', 'accent' => '#e8bb2c', 'active' => true],
        'farms' => ['name' => 'Dot.Farms', 'url' => env('DOT_FARMS_URL', 'https://farms.infodot.co.za'), 'icon' => 'agriculture', 'accent' => '#f0c862', 'active' => false],
        'files' => ['name' => 'Dot.Files', 'url' => env('DOT_FILES_URL', 'https://files.infodot.co.za'), 'icon' => 'folder', 'accent' => '#d9a018', 'active' => true],
        'finance' => ['name' => 'Dot.Finance', 'url' => env('DOT_FINANCE_URL', 'https://finance.infodot.co.za'), 'icon' => 'payments', 'accent' => '#e8af39', 'active' => false],
        'forms' => ['name' => 'Dot.Forms', 'url' => env('DOT_FORMS_URL', 'https://forms.infodot.co.za'), 'icon' => 'dynamic_form', 'accent' => '#f1c62e', 'active' => true],
        'hr' => ['name' => 'Dot.HR', 'url' => env('DOT_HR_URL', 'https://hr.infodot.co.za'), 'icon' => 'badge', 'accent' => '#f0b91c', 'active' => false],
        'memory' => ['name' => 'Dot.Memory', 'url' => env('DOT_MEMORY_URL', 'https://memory.infodot.co.za'), 'icon' => 'memory', 'accent' => '#f4c94c', 'active' => true, 'internal' => true],
        'mines' => ['name' => 'Dot.Mines', 'url' => env('DOT_MINES_URL', 'https://mines.infodot.co.za'), 'icon' => 'terrain', 'accent' => '#f1c62e', 'active' => true],
        'notify' => ['name' => 'Dot.Notify', 'url' => env('DOT_NOTIFY_URL', 'https://notify.infodot.co.za'), 'icon' => 'notifications', 'accent' => '#f0c33a', 'active' => false],
        'plug' => ['name' => 'Dot.Plug', 'url' => env('DOT_PLUG_URL', 'https://plug.infodot.co.za'), 'icon' => 'extension', 'accent' => '#f0c33a', 'active' => false],
        'press' => ['name' => 'Dot.Press', 'url' => env('DOT_PRESS_URL', 'https://pres.infodot.co.za'), 'icon' => 'newspaper', 'accent' => '#f0c33a', 'active' => true],
        'projects' => ['name' => 'Dot.Projects', 'url' => env('DOT_PROJECTS_URL', 'https://projects.infodot.co.za'), 'icon' => 'folder_special', 'accent' => '#f0c33a', 'active' => true],
        'pulse' => ['name' => 'Dot.Pulse', 'url' => env('DOT_PULSE_URL', 'https://pulse.infodot.co.za'), 'icon' => 'favorite', 'accent' => '#f1c62e', 'active' => false],
        'sheet' => ['name' => 'Dot.Sheet', 'url' => env('DOT_SHEET_URL', 'https://sheets.infodot.co.za'), 'icon' => 'table_chart', 'accent' => '#e3ab1f', 'active' => true],
        'tasks' => ['name' => 'Dot.Tasks', 'url' => env('DOT_TASKS_URL', 'https://tasks.infodot.co.za'), 'icon' => 'task_alt', 'accent' => '#f2a803', 'active' => false],
        'tutor' => ['name' => 'Dot.Tutor', 'url' => env('DOT_TUTOR_URL', 'https://tutor.infodot.co.za'), 'icon' => 'school', 'accent' => '#f1c62e', 'active' => false],
        'docs' => ['name' => 'Dot.docs', 'url' => env('DOT_DOCS_URL', 'https://doc.infodot.co.za'), 'icon' => 'description', 'accent' => '#f1c62e', 'active' => true],
    ],

    /*
    |--------------------------------------------------------------------------
    | Handoff Token TTL
    |--------------------------------------------------------------------------
    | Short-lived tokens used for cross-platform SSO. Measured in minutes.
    | (Currently only InfoDot issues these, but the setting lives in the
    | shared registry so any platform that starts issuing tokens inherits
    | the same env-configurable default rather than hardcoding its own.)
    */

    'handoff_ttl' => env('ECOSYSTEM_HANDOFF_TTL', 5),

    /*
    |--------------------------------------------------------------------------
    | Trusted Handoff Domains
    |--------------------------------------------------------------------------
    | The platform switcher and dashboard widget send a signed-in user's browser
    | to {platform url}/auth/ecosystem?token=<handoff token>. The token is a
    | credential, so it is only ever sent to https hosts under these domains
    | (the domain itself or any subdomain), whatever a DOT_*_URL env var says.
    | A mistyped or hijackable URL is refused before a token is created.
    | Comma-separated in ECOSYSTEM_TRUSTED_DOMAINS. For local development add
    | your dev host and set ECOSYSTEM_ALLOW_INSECURE_HANDOFF=true.
    */

    'trusted_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('ECOSYSTEM_TRUSTED_DOMAINS', 'infodot.co.za'))))),

    'allow_insecure_handoff' => (bool) env('ECOSYSTEM_ALLOW_INSECURE_HANDOFF', false),

    /*
    |--------------------------------------------------------------------------
    | Redeem service secrets
    |--------------------------------------------------------------------------
    | Production gives every Dot app its own database, so a satellite cannot look
    | a handoff token up locally. It sends the token to POST /api/ecosystem/redeem
    | on InfoDot instead, proving who it is with a per-platform secret
    | (ECOSYSTEM_SECRET_<PLATFORM KEY>, for example ECOSYSTEM_SECRET_FILES). The
    | same value is set on the satellite as INFODOT_SERVICE_SECRET. A platform
    | with no secret here cannot redeem anything.
    */

    'service_secrets' => collect(['files', 'docs', 'forms', 'sheet', 'projects', 'agents', 'memory', 'central', 'mines', 'engage', 'press', 'charts', 'dopemine', 'analytics', 'auction', 'billing', 'design', 'ehail', 'emall', 'farms', 'finance', 'hr', 'notify', 'plug', 'pulse', 'tasks', 'tutor'])
        ->mapWithKeys(fn ($key) => [$key => env('ECOSYSTEM_SECRET_'.strtoupper($key))])
        ->filter()
        ->all(),

    /*
    |--------------------------------------------------------------------------
    | Membership webhooks
    |--------------------------------------------------------------------------
    | When someone is removed from a company, has their role changed, or a company is
    | deleted, these platforms are told at once (signed with the same per-platform secret as
    | the redeem call) so access ends in seconds, not at the person's next sign-in. The
    | sign-in sync stays as the backstop. Comma-separated in ECOSYSTEM_MEMBERSHIP_PLATFORMS.
    */

    'membership_platforms' => array_values(array_filter(array_map('trim', explode(',', (string) env('ECOSYSTEM_MEMBERSHIP_PLATFORMS', 'files,docs'))))),

    /*
    |--------------------------------------------------------------------------
    | Cross-platform search
    |--------------------------------------------------------------------------
    | While someone types in the header search, these platforms are asked (signed, server to
    | server) for things in the person's current company that match. Each platform filters by
    | what that person may see, InfoDot keeps no copy of any title, and a result is only shown
    | if it is a pointer InfoDot already holds for that company. Comma-separated in
    | ECOSYSTEM_SEARCH_PLATFORMS.
    */

    'search_platforms' => array_values(array_filter(array_map('trim', explode(',', (string) env('ECOSYSTEM_SEARCH_PLATFORMS', 'files,docs'))))),

];

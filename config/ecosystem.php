<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dot Platform Registry
    |--------------------------------------------------------------------------
    | Each satellite app is listed here with its URL and display metadata.
    | Update these URLs per environment via the matching VITE_DOT_* env vars.
    */

    'platforms' => [
        // Core workspace platforms
        'files'     => ['name' => 'Dot.Files',     'url' => env('DOT_FILES_URL',     'https://files.infodot.app'),     'icon' => 'folder'],
        'docs'      => ['name' => 'Dot.Docs',      'url' => env('DOT_DOCS_URL',      'https://docs.infodot.app'),      'icon' => 'description'],
        'forms'     => ['name' => 'Dot.Forms',     'url' => env('DOT_FORMS_URL',     'https://forms.infodot.app'),     'icon' => 'dynamic_form'],
        'sheet'     => ['name' => 'Dot.Sheet',     'url' => env('DOT_SHEET_URL',     'https://sheet.infodot.app'),     'icon' => 'table_chart'],
        'projects'  => ['name' => 'Dot.Projects',  'url' => env('DOT_PROJECTS_URL',  'https://projects.infodot.app'),  'icon' => 'folder_special'],
        'tasks'     => ['name' => 'Dot.Tasks',     'url' => env('DOT_TASKS_URL',     'https://tasks.infodot.app'),     'icon' => 'task_alt'],
        'hr'        => ['name' => 'Dot.HR',        'url' => env('DOT_HR_URL',        'https://hr.infodot.app'),        'icon' => 'badge'],
        'plug'      => ['name' => 'Dot.Plug',      'url' => env('DOT_PLUG_URL',      'https://plug.infodot.app'),      'icon' => 'extension'],

        // AI & automation
        'agents'    => ['name' => 'Dot.Agents',    'url' => env('DOT_AGENTS_URL',    'https://agents.infodot.app'),    'icon' => 'smart_toy'],
        'analytics' => ['name' => 'Dot.Analytics', 'url' => env('DOT_ANALYTICS_URL', 'https://analytics.infodot.app'), 'icon' => 'insights'],
        'memory'    => ['name' => 'Dot.Memory',    'url' => env('DOT_MEMORY_URL',    'https://memory.infodot.app'),    'icon' => 'memory'],

        // Community & engagement
        'pulse'     => ['name' => 'Dot.Pulse',     'url' => env('DOT_PULSE_URL',     'https://pulse.infodot.app'),     'icon' => 'favorite'],
        'engage'    => ['name' => 'Dot.Engage',    'url' => env('DOT_ENGAGE_URL',    'https://engage.infodot.app'),    'icon' => 'campaign'],
        'press'     => ['name' => 'Dot.Press',     'url' => env('DOT_PRESS_URL',     'https://press.infodot.app'),     'icon' => 'newspaper'],
        'dopemine'  => ['name' => 'Dot.Dopemine',  'url' => env('DOT_DOPEMINE_URL',  'https://dopemine.infodot.app'),  'icon' => 'bolt'],

        // Commerce & finance
        'finance'   => ['name' => 'Dot.Finance',   'url' => env('DOT_FINANCE_URL',   'https://finance.infodot.app'),   'icon' => 'payments'],
        'emall'     => ['name' => 'Dot.Emall',     'url' => env('DOT_EMALL_URL',     'https://emall.infodot.app'),     'icon' => 'storefront'],
        'auction'   => ['name' => 'Dot.Auction',   'url' => env('DOT_AUCTION_URL',   'https://auction.infodot.app'),   'icon' => 'gavel'],
        'billing'   => ['name' => 'Dot.Billing',   'url' => env('DOT_BILLING_URL',   'https://billing.infodot.app'),   'icon' => 'credit_card'],
        'charts'    => ['name' => 'Dot.Charts',    'url' => env('DOT_CHARTS_URL',    'https://charts.infodot.app'),    'icon' => 'candlestick_chart'],

        // Services & learning
        'ehail'     => ['name' => 'Dot.Ehail',     'url' => env('DOT_EHAIL_URL',     'https://ehail.infodot.app'),     'icon' => 'local_taxi'],
        'tutor'     => ['name' => 'Dot.Tutor',     'url' => env('DOT_TUTOR_URL',     'https://tutor.infodot.app'),     'icon' => 'school'],
        'design'    => ['name' => 'Dot.Design',    'url' => env('DOT_DESIGN_URL',    'https://design.infodot.app'),    'icon' => 'palette'],

        // Infrastructure
        'central'   => ['name' => 'Dot.Central',   'url' => env('DOT_CENTRAL_URL',   'https://central.infodot.app'),   'icon' => 'hub'],
        'notify'    => ['name' => 'Dot.Notify',    'url' => env('DOT_NOTIFY_URL',    'https://notify.infodot.app'),    'icon' => 'notifications'],

        // Industry verticals
        'mines'     => ['name' => 'Dot.Mines',     'url' => env('DOT_MINES_URL',     'https://mines.infodot.app'),     'icon' => 'terrain'],
        'farms'     => ['name' => 'Dot.Farms',     'url' => env('DOT_FARMS_URL',     'https://farms.infodot.app'),     'icon' => 'agriculture'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Handoff Token TTL
    |--------------------------------------------------------------------------
    | Short-lived tokens used for cross-platform SSO. Measured in minutes.
    */

    'handoff_ttl' => env('ECOSYSTEM_HANDOFF_TTL', 5),

];

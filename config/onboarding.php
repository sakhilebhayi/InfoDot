<?php

/**
 * R-59 Phase 1B: the steps a new business moves through (each one is recorded once, when it happens, so abandonment can be measured),
 * what a business can say about itself, and which platforms each aim points to. The recommender reads only this and the live platform registry.
 */
return [
    'steps' => [
        'account_created' => 'Account created',
        'email_verified' => 'Email verified',
        'business_created' => 'Business created',
        'profile_established' => 'Business described',
        'category_selected' => 'Category chosen',
        'objectives_set' => 'Aims chosen',
        'team_invited' => 'Colleague invited',
        'platforms_recommended' => 'Platforms recommended',
        'platform_opened' => 'First platform opened',
    ],

    // The step each one is measured against. Steps like a colleague invited or a platform opened do not depend on the step just before them
    // in the list, so each is compared with the step it really follows; that way no share can come out above 100 percent.
    'parents' => [
        'email_verified' => 'account_created',
        'business_created' => 'email_verified',
        'profile_established' => 'business_created',
        'category_selected' => 'business_created',
        'objectives_set' => 'business_created',
        'team_invited' => 'business_created',
        'platforms_recommended' => 'business_created',
        'platform_opened' => 'business_created',
    ],

    'stages' => [
        'idea' => 'I have an idea',
        'starting' => 'I am just starting',
        'running' => 'I am running it',
        'growing' => 'It is growing',
        'established' => 'It is established',
    ],

    'size_bands' => [
        'solo' => 'Just me',
        '2-5' => '2 to 5 people',
        '6-20' => '6 to 20 people',
        '21-100' => '21 to 100 people',
        '100+' => 'More than 100 people',
    ],

    // R-62 phase 2: an unconfirmed company import (and anything kept with it) is deleted after this many days.
    'import_retention_days' => (int) env('ONBOARDING_IMPORT_RETENTION_DAYS', 30),

    'max_objectives' => 5,
    'max_recommendations' => 5,

    // An aim, in the business's own words, and the platforms that serve it (best first). A platform that is not live is never shown.
    'objectives' => [
        'manage_money' => ['label' => 'Keep the books, invoices and tax in order', 'platforms' => ['finance', 'sheet']],
        'manage_people' => ['label' => 'Manage employees, leave and records', 'platforms' => ['hr']],
        'organise_work' => ['label' => 'Organise projects and daily tasks', 'platforms' => ['tasks', 'projects']],
        'share_files' => ['label' => 'Store, write and share documents', 'platforms' => ['files', 'docs']],
        'collect_information' => ['label' => 'Collect information with forms', 'platforms' => ['forms']],
        'understand_business' => ['label' => 'Understand how the business is doing', 'platforms' => ['analytics', 'charts']],
        'talk_to_people' => ['label' => 'Keep the team and customers informed', 'platforms' => ['notify', 'engage']],
        'find_customers' => ['label' => 'Find customers and business partners', 'platforms' => ['pulse', 'emall']],
        'look_professional' => ['label' => 'Design and present the business well', 'platforms' => ['design', 'press']],
        'sell_or_auction' => ['label' => 'Sell goods or run auctions', 'platforms' => ['emall', 'auction']],
        'move_people_or_goods' => ['label' => 'Run rides or deliveries', 'platforms' => ['ehail']],
        'run_a_mine' => ['label' => 'Run a mining operation', 'platforms' => ['mines']],
        'train_people' => ['label' => 'Train and teach', 'platforms' => ['tutor']],
    ],

    // Small, explainable nudges on top of the aims: a stage or a size can make a platform worth mentioning first.
    'stage_boost' => [
        'idea' => ['docs', 'finance'],
        'starting' => ['finance', 'tasks', 'files'],
        'growing' => ['projects', 'hr', 'analytics'],
        'established' => ['analytics', 'hr'],
    ],
    'size_boost' => [
        '2-5' => ['tasks'],
        '6-20' => ['projects', 'hr'],
        '21-100' => ['hr', 'projects', 'analytics'],
        '100+' => ['hr', 'projects', 'analytics'],
    ],
    // With no aims chosen: the platforms most businesses reach for first.
    'starter' => ['finance', 'tasks', 'files'],
];

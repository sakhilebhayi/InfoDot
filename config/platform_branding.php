<?php

/*
 * R-54: the real identity of each platform for the central sign-in pages, taken from the platform's own design record
 * (its DESIGN.md or, where it has none, its stylesheet tokens) in the 2026-11-17 audit. Only colours and light/dark
 * mode live here. Names come from the registry, descriptions from the catalogue tagline, and headlines from the two
 * fixed forms ("Welcome back to Dot.X", "Get started with Dot.X"), so nothing is invented. `branding:seed` copies
 * these into `platform_branding` for platforms that are registered as sign-in clients, once, and never overwrites a
 * row an administrator has touched.
 *
 * Open item: Dot.docs has two conflicting palettes (gold landing, green app). Gold is used until the owner decides.
 */
return [
    'agents' => ['primary' => '#f0c23a', 'secondary' => '#121018', 'accent' => '#4fbf94', 'mode' => 'dark'],
    'analytics' => ['primary' => '#2338d8', 'secondary' => '#10151c', 'accent' => '#12704a', 'mode' => 'light'],
    'auction' => ['primary' => '#f1c62e', 'secondary' => '#d71016', 'accent' => '#1c2120', 'mode' => 'light'],
    'central' => ['primary' => '#f2a70b', 'secondary' => '#0b0e12', 'accent' => '#f2a70b', 'mode' => 'dark'],
    'charts' => ['primary' => '#f2a70b', 'secondary' => '#01040d', 'accent' => '#8ec9dc', 'mode' => 'dark'],
    'design' => ['primary' => '#0a6a91', 'secondary' => '#14181c', 'accent' => '#0a6a91', 'mode' => 'light'],
    'docs' => ['primary' => '#f1c62e', 'secondary' => '#1f1b14', 'accent' => '#c9970f', 'mode' => 'light'],
    'ehail' => ['primary' => '#0b6a3c', 'secondary' => '#d9eedf', 'accent' => '#f2c230', 'mode' => 'light'],
    'emall' => ['primary' => '#4b58a8', 'secondary' => '#1b1e36', 'accent' => '#f2c82d', 'mode' => 'light'],
    'engage' => ['primary' => '#234a8f', 'secondary' => '#15181b', 'accent' => '#a3262a', 'mode' => 'light'],
    'files' => ['primary' => '#141414', 'secondary' => '#f2f4f7', 'accent' => '#d9a018', 'mode' => 'light'],
    'finance' => ['primary' => '#14181f', 'secondary' => '#f6f7f9', 'accent' => '#5a6371', 'mode' => 'light'],
    'forms' => ['primary' => '#2b4a9e', 'secondary' => '#191a1c', 'accent' => '#b3362a', 'mode' => 'light'],
    'hr' => ['primary' => '#1b6e66', 'secondary' => '#1f201d', 'accent' => '#114b45', 'mode' => 'light'],
    'mines' => ['primary' => '#d99e2b', 'secondary' => '#211a14', 'accent' => '#f0c669', 'mode' => 'dark'],
    'notify' => ['primary' => '#2438b8', 'secondary' => '#131a3a', 'accent' => '#f2c832', 'mode' => 'light'],
    'press' => ['primary' => '#f2a100', 'secondary' => '#14171a', 'accent' => '#6b4600', 'mode' => 'light'],
    'projects' => ['primary' => '#1d5fbf', 'secondary' => '#141414', 'accent' => '#1d5fbf', 'mode' => 'light'],
    'pulse' => ['primary' => '#1f3cff', 'secondary' => '#0b0e1a', 'accent' => '#ffd400', 'mode' => 'light'],
    'sheet' => ['primary' => '#2438c9', 'secondary' => '#15201b', 'accent' => '#1f8a5f', 'mode' => 'light'],
    'tasks' => ['primary' => '#1c7c54', 'secondary' => '#171815', 'accent' => '#1c7c54', 'mode' => 'light'],
    'tutor' => ['primary' => '#115b3e', 'secondary' => '#11211a', 'accent' => '#edb333', 'mode' => 'light'],
];

<?php

namespace App\Support;

/**
 * Names the section of the signed-in area the current request belongs to, for
 * the page title and the top bar.
 */
class NavSection
{
    public static function current(): string
    {
        return match (true) {
            request()->routeIs('questions*', 'seek') => 'Questions',
            request()->routeIs('solutions*', 'add') => 'Solutions',
            request()->routeIs('ecosystem.widget') => 'Platforms',
            request()->routeIs('analytics.*') => 'Analytics',
            request()->routeIs('profile.*', 'teams.*', 'api-tokens.*') => 'Account',
            default => 'InfoDot',
        };
    }
}

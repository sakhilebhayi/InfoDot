<?php

namespace App\Support;

/**
 * Describes where in the signed-in area the current request is, for the page
 * title, the top tabs and the blue band under them.
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

    /**
     * The band under the top bar: a title, an optional one-line purpose, whether
     * the big search sits in it, an optional primary action, and whether it is the
     * full band (section home) or the compact one (detail and form pages).
     *
     * @return array{title: string, lede: ?string, search: bool, action: ?array{label: string, url: string, icon: string}, compact: bool}
     */
    public static function band(): array
    {
        $home = request()->routeIs('questions', 'solutions', 'ecosystem.widget', 'analytics.*');

        $band = match (self::current()) {
            'Questions' => [
                'title' => 'Questions',
                'lede' => 'Ask your team what is blocking you, or answer someone else.',
                'search' => true,
                'action' => ['label' => 'Ask a question', 'url' => route('seek'), 'icon' => 'add'],
            ],
            'Solutions' => [
                'title' => 'Solutions',
                'lede' => 'Step-by-step answers written down so a problem is only solved once.',
                'search' => true,
                'action' => ['label' => 'Add a solution', 'url' => route('add'), 'icon' => 'add'],
            ],
            'Platforms' => [
                'title' => 'Dot platforms',
                'lede' => 'Open any live Dot platform with your current sign-in.',
                'search' => true,
                'action' => null,
            ],
            'Analytics' => [
                'title' => 'Analytics',
                'lede' => 'Who is posting, what gets liked, and how much of it gets answered.',
                'search' => false,
                'action' => null,
            ],
            'Account' => [
                'title' => 'Your account',
                'lede' => null,
                'search' => false,
                'action' => null,
            ],
            default => [
                'title' => 'InfoDot',
                'lede' => null,
                'search' => true,
                'action' => null,
            ],
        };

        $band['compact'] = ! $home;

        // Compact bands keep only the section title; the lede, the big search and the
        // action would repeat what the page itself says.
        if ($band['compact']) {
            $band['lede'] = null;
            $band['search'] = false;
            $band['action'] = null;
        }

        return $band;
    }
}

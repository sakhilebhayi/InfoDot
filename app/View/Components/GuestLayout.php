<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    /**
     * @param  string|null  $title  the page name shown in the tab, e.g. "Sign in"; the site name is added
     * @param  string|null  $panel  R-69: on InfoDot's own sign-in pages, which split layout to draw beside the form
     *                              ('login', 'register' or 'account'); null keeps the plain single-column page
     */
    public function __construct(public ?string $title = null, public ?string $panel = null) {}

    /**
     * Get the view / contents that represents the component.
     *
     * @return View
     */
    public function render()
    {
        return view('layouts.guest');
    }
}

<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    /** The page name shown in the tab, e.g. "Sign in"; the site name is added. */
    public function __construct(public ?string $title = null) {}

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

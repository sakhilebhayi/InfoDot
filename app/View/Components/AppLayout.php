<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /** A page may name itself in the browser tab (a business page carries the business's name). */
    public function __construct(public ?string $title = null) {}

    /**
     * Get the view / contents that represents the component.
     *
     * @return View
     */
    public function render()
    {
        return view('layouts.app');
    }
}

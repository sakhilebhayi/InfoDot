<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;

class Comment extends Component
{
    public mixed $comment;

    public function render(): View
    {
        return view('livewire.comment');
    }
}

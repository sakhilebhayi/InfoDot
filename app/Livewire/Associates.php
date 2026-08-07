<?php

namespace App\Livewire;

use App\Models\Associates as AssociatesModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Associates extends Component
{
    public mixed $user;

    public mixed $model;

    public function connect(): void
    {
        $connect = AssociatesModel::where('user_id', Auth::id())
            ->where('associate_id', $this->user->id)
            ->whereNull('deleted_at')
            ->first();

        if ($connect) {
            $connect->delete();
        } else {
            AssociatesModel::create([
                'user_id' => Auth::id(),
                'associate_id' => $this->user->id,
            ]);
        }
    }

    public function render(): View
    {
        return view('livewire.associates');
    }
}

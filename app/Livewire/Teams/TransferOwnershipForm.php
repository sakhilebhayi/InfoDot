<?php

namespace App\Livewire\Teams;

use App\Actions\Teams\TransferTeamOwnership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Jetstream;
use Livewire\Component;

/**
 * The "Transfer ownership" section of team settings (R-49): the owner picks someone already in the company and
 * confirms with their password. It exists so an owner can hand a company on instead of deleting it; the rule itself
 * lives in App\Actions\Teams\TransferTeamOwnership.
 */
class TransferOwnershipForm extends Component
{
    public Team $team;

    public string $newOwner = '';

    public string $password = '';

    public function mount(Team $team): void
    {
        Gate::authorize('transferOwnership', $team);
        $this->team = $team;
    }

    public function transferOwnership(TransferTeamOwnership $action): mixed
    {
        $this->resetErrorBag();
        /** @var User $owner */
        $owner = Auth::user();
        Gate::authorize('transferOwnership', $this->team);

        $candidate = $this->team->users()->whereKey((int) $this->newOwner)->first();

        if (! $candidate instanceof User) {
            throw ValidationException::withMessages(['new_owner' => 'Choose who takes over the company.']);
        }

        if (! Hash::check($this->password, (string) $owner->password)) {
            $this->password = '';

            throw ValidationException::withMessages(['password' => __('This password does not match our records.')]);
        }

        $role = $action->transfer($owner, $this->team, $candidate);
        $roleName = Jetstream::findRole($role)->name ?? ucfirst($role);

        session()->flash('flash.banner', "{$candidate->name} now owns {$this->team->name}. You are still in the company as ".mb_strtolower($roleName).'.');
        session()->flash('flash.bannerStyle', 'success');

        return redirect()->route('teams.show', $this->team->getKey());
    }

    public function render(): View
    {
        return view('teams.transfer-ownership-form', [
            'candidates' => $this->team->users()->orderBy('name')->get(),
        ]);
    }
}

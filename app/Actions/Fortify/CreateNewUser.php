<?php

namespace App\Actions\Fortify;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @return User
     */
    public function create(array $input)
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['required', 'accepted'] : '',
        ])->validate();

        try {
            return DB::transaction(function () use ($input) {
                return tap(User::create([
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'password' => Hash::make($input['password']),
                ]), function (User $user) {
                    $this->createTeam($user);
                });
            });
        } catch (UniqueConstraintViolationException) {
            return $this->accountFromTheSameSignUp($input);
        }
    }

    /**
     * The address passed the unique rule and the insert still collided: another request for the same address
     * finished in between. In practice that is a second tap on Register (production, 2026-11-12), and the browser
     * shows the answer to the second request. If this request carries the password of an account made in the last
     * two minutes it is the same person and the same sign-up, so it continues with that account. Anything else is
     * the ordinary "already taken" message. An account older than that is never signed in from this form.
     */
    private function accountFromTheSameSignUp(array $input): User
    {
        $user = User::where('email', $input['email'])->first();

        if ($user !== null
            && $user->created_at !== null
            && $user->created_at->gte(now()->subMinutes(2))
            && Hash::check($input['password'], $user->password)) {
            return $user;
        }

        throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
    }

    /**
     * Create a personal team for the user.
     *
     * @return void
     */
    protected function createTeam(User $user)
    {
        $user->ownedTeams()->save(Team::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]));
    }
}

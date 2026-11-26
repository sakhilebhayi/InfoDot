<?php

namespace App\Actions\Fortify;

use App\Models\Team;
use App\Models\User;
use App\Rules\DeliverableEmailDomain;
use App\Services\Growth\Acquisition;
use App\Services\Growth\OnboardingEvents;
use App\Services\Growth\Referrals;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    /** R-73: the homepage buttons that lead to sign-up, recorded with the "account created" step (hero: the top; close: the final call). */
    public const SIGNUP_SOURCES = ['hero', 'close'];

    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @return User
     */
    public function create(array $input)
    {
        // R-62: the form asks for a first and a last name and they are stored joined in the one `name` column every Dot platform already reads, so
        // nothing else in the ecosystem changes. A caller that still sends a single `name` (an older client, an API call) works as before.
        $input['name'] = $this->fullName($input);

        Validator::make($input, [
            'first_name' => ['nullable', 'required_with:last_name', 'string', 'max:120'],
            'last_name' => ['nullable', 'required_with:first_name', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', new DeliverableEmailDomain, 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['required', 'accepted'] : '',
        ])->validate();

        try {
            $user = DB::transaction(function () use ($input) {
                return tap(User::create([
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'password' => Hash::make($input['password']),
                ]), function (User $user) {
                    $this->createTeam($user);
                    app(Acquisition::class)->record($user, request());
                });
            });

            // R-73: the homepage button the person came from, when they came from one.
            $from = session()->pull('signup.from');
            app(OnboardingEvents::class)->record($user, 'account_created', null, in_array($from, self::SIGNUP_SOURCES, true) ? ['from' => $from] : []);
            app(Referrals::class)->attribute($user);

            return $user;
        } catch (UniqueConstraintViolationException) {
            return $this->accountFromTheSameSignUp($input);
        }
    }

    /** First and last name joined with one space, else the single name that was sent. @param  array<string, mixed>  $input */
    private function fullName(array $input): string
    {
        $joined = trim(preg_replace('/\s+/', ' ', trim((string) ($input['first_name'] ?? '')).' '.trim((string) ($input['last_name'] ?? ''))));

        return $joined !== '' ? $joined : trim((string) ($input['name'] ?? ''));
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

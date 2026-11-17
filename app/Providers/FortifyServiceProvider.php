<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Controllers\Auth\ResendVerificationEmailController;
use App\Http\Responses\VerifiedEmailResponse;
use App\Models\User;
use App\Services\Accounts\AccountSuspension;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Fortify registers the route against its own controller; ours adds the answer for a mail server that is down.
        $this->app->bind(EmailVerificationNotificationController::class, ResendVerificationEmailController::class);
        // R-54: a platform's pending sign-in continues after the address is confirmed.
        $this->app->singleton(VerifyEmailResponse::class, VerifiedEmailResponse::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // A suspended account cannot sign in. The credentials are checked first, exactly as before, so the suspension
        // is only ever mentioned to someone who knows the password: a wrong password gets the usual answer.
        Fortify::authenticateUsing(function (Request $request) {
            $guard = Auth::guard(config('fortify.guard'));
            $credentials = $request->only(Fortify::username(), 'password');

            if (! $guard->validate($credentials)) {
                return null;
            }

            $user = $guard->getLastAttempted();

            if ($user instanceof User && $user->isSuspended()) {
                throw ValidationException::withMessages([Fortify::username() => [AccountSuspension::MESSAGE]]);
            }

            // The framework's own sign-in upgrades an old password hash at this point; keep doing so.
            $guard->getProvider()->rehashPasswordIfRequired($user, $credentials);

            return $user;
        });

        Fortify::loginView(function () {
            return view('auth.login');
        });

        Fortify::registerView(function () {
            return view('auth.register');
        });

        Fortify::requestPasswordResetLinkView(function () {
            return view('auth.forgot-password');
        });

        Fortify::resetPasswordView(function ($request) {
            return view('auth.reset-password', ['request' => $request]);
        });

        Fortify::verifyEmailView(function () {
            return view('auth.verify-email');
        });

        Fortify::confirmPasswordView(function () {
            return view('auth.confirm-password');
        });

        Fortify::twoFactorChallengeView(function () {
            return view('auth.two-factor-challenge');
        });

        RateLimiter::for('login', function (Request $request) {
            // R-54: the address is lower-cased and trimmed, so `A@x.com` and `a@x.com ` are one account to the limiter
            // (they were separate counters, which let a guesser multiply the allowance by changing case).
            $email = mb_strtolower(trim((string) $request->email));

            // Three limits: the email-and-address pair, the address on its own (so one address cannot keep trying by
            // changing the email each time), and the account on its own (so a spread of addresses cannot share the
            // guessing of one password out between them).
            return [
                Limit::perMinute(5)->by($email.$request->ip()),
                Limit::perMinute(30)->by('login-address:'.$request->ip()),
                Limit::perMinutes(15, 20)->by('login-account:'.sha1($email)),
            ];
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}

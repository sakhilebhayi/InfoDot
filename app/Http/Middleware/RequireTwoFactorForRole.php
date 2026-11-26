<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * R-72 (S-02): what a moderator or platform administrator sees while their two-factor sign-in is off. A page visit goes to the
 * security settings with the reason; anything else (a form, a script) is refused with the same reason, so nothing is done on a
 * password alone.
 */
final class RequireTwoFactorForRole
{
    public static function refuse(Request $request, string $what): Response
    {
        $reason = 'Turn on two-factor sign-in to use '.$what.'. The role stays yours; it works again as soon as two-factor is on.';

        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            return redirect()->to(route('profile.edit').'#two-factor')->with('status', $reason);
        }

        abort(403, $reason);
    }
}

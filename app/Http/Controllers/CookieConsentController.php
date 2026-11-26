<?php

namespace App\Http\Controllers;

use App\Services\Growth\Acquisition;
use App\Support\CookieConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * R-71: the visitor's answer in the cookie banner (or on the Cookie Policy page). Accept keeps the arrival that was waiting in the
 * session as the dot_src cookie; Decline discards it and removes any dot_src already set. Either way the choice is remembered.
 */
class CookieConsentController extends Controller
{
    public function __invoke(Request $request, Acquisition $acquisition): RedirectResponse|Response
    {
        $choice = $request->validate(['choice' => ['required', 'string', Rule::in(CookieConsent::CHOICES)]])['choice'];
        $pending = $request->hasSession() ? $request->session()->pull((string) config('acquisition.pending_key')) : null;

        $response = $request->expectsJson()
            ? response()->noContent()
            : redirect()->back(fallback: route('welcome'))->with('cookie-choice-saved', $choice);

        $response->headers->setCookie(CookieConsent::cookie($choice, $request->isSecure()));

        if ($choice === CookieConsent::ACCEPTED) {
            if (is_array($pending) && $request->user() === null && $acquisition->fromCookie($request) === null) {
                /** @var array{r: ?string, u: array<string, string>, h: ?string, p: ?string, at: int} $pending */
                $response->headers->setCookie($acquisition->cookieFor($pending, $request->isSecure()));
            }
        } else {
            $response->headers->setCookie(cookie()->forget((string) config('acquisition.cookie')));
        }

        return $response;
    }
}

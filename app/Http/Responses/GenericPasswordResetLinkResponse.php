<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

/**
 * R-72 (S-04): "forgot password" answers the same whether or not an account uses the address, and whether or not one was just
 * sent, so the form cannot be used to find out who has an InfoDot account. The per-address and per-source limits still apply.
 */
class GenericPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse
{
    public function __construct(public ?string $status = null) {}

    public function toResponse($request)
    {
        $message = trans('passwords.sent');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 200)
            : back()->with('status', $message);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LicenceBatch;
use App\Models\Team;
use App\Support\Knowledge\Signature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/licences/reconcile/{team_uuid}: what InfoDot holds for one company's licence batches, for Dot.Finance's read-only
 * `licences:reconcile` (which compares it with its own records and changes nothing). Signed with the same secret and scheme as Finance's
 * confirmations (a GET signs the empty body); refused entirely while that secret is unset. Read-only, one company per call, and it carries no
 * person, email or price: only batch uuid, status, quantity, interval and the end of the paid period.
 *
 * This is NOT the R-13 `/api/entitlements/{uuid}` contract: that path is Finance's own, which InfoDot reads. Finance's reconcile URL template is
 * configurable (`LICENCE_RECONCILE_INFODOT_URL`), so set it to `https://<infodot host>/api/licences/reconcile/{team_uuid}`.
 */
class LicenceReconcileController extends Controller
{
    public function __invoke(Request $request, string $teamUuid): JsonResponse
    {
        $secret = (string) config('platform_subscriptions.confirmation_secret');

        if (! Signature::valid($secret, $request->header(Signature::TIMESTAMP_HEADER), $request->header(Signature::SIGNATURE_HEADER), $request->getContent(), (int) config('platform_subscriptions.signature_window', 300))) {
            return response()->json(['error' => 'invalid_request'], 401);
        }

        $team = preg_match('/\A[0-9a-fA-F-]{36}\z/', $teamUuid) === 1 ? Team::where('uuid', strtolower($teamUuid))->first() : null;

        if ($team === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        return response()->json(['team_uuid' => $team->uuid, 'batches' => LicenceBatch::where('team_id', $team->getKey())->where('status', '<>', LicenceBatch::PENDING)->orderBy('id')->get()->map(fn (LicenceBatch $b) => [
            'batch_uuid' => $b->uuid, 'status' => $b->status === LicenceBatch::REFUNDED ? 'grace' : $b->status, 'quantity' => $b->quantity, 'interval' => $b->interval,
            'period_end' => $b->period_end?->utc()->toIso8601String(), 'counts_now' => $b->covers(),
        ])->all()]);
    }
}

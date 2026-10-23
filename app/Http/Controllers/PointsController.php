<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Participation\ExpertiseSharing;
use App\Services\Points\PointsClient;
use App\Services\Points\PointsUnavailableException;
use App\Services\Points\RedemptionRefusedException;
use App\Services\Points\RedemptionTickets;
use App\Services\Points\Reward;
use App\Support\Knowledge\TopicSuggestions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The Dot Points screen: balance, what is about to lapse, the reward catalogue with Redeem, and the person's own
 * redemptions. Every call to the points service names the signed-in person's own uuid (never anything from the
 * request) and every failure of the service becomes an honest "unavailable" state, never a 500 or an invented number.
 */
class PointsController extends Controller
{
    // Set at the start of each action, not in a constructor: the router keeps one controller instance alive across
    // requests in a long-lived process, and the client remembers an outage for the rest of "its" request.
    private PointsClient $points;

    private RedemptionTickets $tickets;

    public function index(Request $request, PointsClient $points, RedemptionTickets $tickets, ExpertiseSharing $sharing): Response
    {
        $this->points = $points;
        $this->tickets = $tickets;
        $user = $request->user();
        $configured = $this->points->enabled();

        $rewards = $history = $summary = null;
        if ($configured) {
            try {
                $rewards = $this->points->rewards();
            } catch (PointsUnavailableException) {
            }
            try {
                $history = $this->points->redemptions($user);
            } catch (PointsUnavailableException) {
            }
            $summary = $this->points->summary((string) $user->uuid);
            // A summary without a whole-number balance is not one we can show; treat it as unavailable, not as zero.
            $summary = is_array($summary) && is_int($summary['balance'] ?? null) ? $summary : null;
        }

        $balance = $summary['balance'] ?? null;

        $catalogue = [];
        foreach (Reward::KINDS as $kind) {
            $inKind = array_values(array_filter($rewards ?? [], fn (Reward $r) => $r->kind === $kind));
            if ($inKind !== []) {
                $catalogue[$kind] = array_map(fn (Reward $r) => [
                    'reward' => $r,
                    // A form (and so a key) only for a reward that can be asked for; when the balance is known and
                    // short there is nothing to confirm.
                    'token' => $r->available && ($balance === null || $balance >= $r->costPoints)
                        ? $this->tickets->issue($user->id, $r->id, $r->costPoints) : null,
                    'unconfirmed' => $this->tickets->isAttempted($user->id, $r->id),
                ], $inKind);
            }
        }

        // Only when the person switched it on: a plain read of InfoDot's own records, for them alone. Off means not even computed.
        $suggestionTopics = $user->wantsTopicSuggestions() ? TopicSuggestions::expertiseTopics($user) : null;

        $state = ! $configured ? 'not_configured' : ($summary === null && $rewards === null && $history === null ? 'unavailable' : 'ok');

        return response()->view('points.index', [
            'state' => $state,
            'summary' => $summary,
            'balance' => $balance,
            'catalogue' => $catalogue,
            'catalogueDown' => $configured && $rewards === null,
            'history' => $history,
            'historyDown' => $configured && $history === null,
            'summaryDown' => $configured && $summary === null,
            'expiryEmails' => $user->wantsPointsExpiryEmails(),
            'digestChoice' => $user->pointsDigestPeriod() ?? 'off',
            'memory' => $sharing->overview($user),
            'topicSuggestionsOn' => $suggestionTopics !== null,
            'suggestionTopics' => $suggestionTopics,
            'suggestedQuestions' => $suggestionTopics !== null ? TopicSuggestions::questionsFor($user, $suggestionTopics) : null,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function redeem(Request $request, PointsClient $points, RedemptionTickets $tickets): RedirectResponse
    {
        $this->points = $points;
        $this->tickets = $tickets;
        $user = $request->user();
        $token = $request->input('ticket');
        $ticket = is_string($token) && preg_match('/\A[A-Za-z0-9]{32}\z/', $token) ? $this->tickets->find($token, $user->id) : null;

        if ($ticket === null) {
            return $this->back('error', 'That form has expired or is not valid. Nothing was spent. Choose the reward again.');
        }
        if ($ticket['state'] === 'consumed') {
            // The same form posted twice (double-click, back button): say what happened the first time, spend nothing.
            return $this->back($ticket['notice']['level'] ?? 'ok', $ticket['notice']['message'] ?? 'That request was already sent.');
        }

        try {
            if ($ticket['state'] === 'issued') {
                $stop = $this->priceStillMatches($token, $ticket);
                if ($stop !== null) {
                    return $stop;
                }
            }

            $this->tickets->markAttempted($token);
            $receipt = $this->points->redeem($user, $ticket['reward_id'], $token);
        } catch (RedemptionRefusedException $e) {
            if ($e->reason === RedemptionRefusedException::INSUFFICIENT_POINTS) {
                $this->points->forget((string) $user->uuid); // The cached balance was wrong; show the real one next.
            }
            if ($e->reason !== RedemptionRefusedException::RATE_LIMITED) {
                $this->tickets->consume($token, ['level' => 'error', 'message' => $e->getMessage()]);
            }

            return $this->back('error', $e->getMessage());
        } catch (PointsUnavailableException) {
            return $this->back('error', 'We could not confirm that with the points service. Check your redemptions below before trying again. If you do, nothing is spent twice.');
        }

        $message = $receipt->replayed
            ? 'That request was already received: '.$receipt->rewardName.' ('.number_format($receipt->pointsSpent).' points). Nothing more was spent. Your balance is '.number_format($receipt->balanceAfter).'.'
            : 'Requested: '.$receipt->rewardName.'. '.number_format($receipt->pointsSpent).' points spent; your balance is now '.number_format($receipt->balanceAfter).'. An operator will fulfil it, and its status is under Your redemptions.';
        $this->tickets->consume($token, ['level' => 'ok', 'message' => $message]);

        return $this->back('ok', $message);
    }

    /**
     * The person's own switch for the "your points are about to lapse" email. A local preference: it works even when
     * the points service is down, and it only ever changes the signed-in person's row.
     */
    public function updateExpiryEmails(Request $request): RedirectResponse
    {
        $data = $request->validate(['points_expiry_emails' => ['required', 'in:0,1']]);
        $on = $data['points_expiry_emails'] === '1';

        // A query-builder update, not ->save(): this is one flag and should not re-index the person for search.
        User::whereKey($request->user()->getKey())->update(['points_expiry_emails' => $on]);

        return redirect()->route('points')->with('points_notice', [
            'level' => 'ok',
            'message' => $on ? 'We will email you before some of your points lapse.' : 'We will not email you about points that are about to lapse. You can still see them on this page.',
        ]);
    }

    /**
     * The person's own choice of a summary email of what they earned: none (the default), weekly or monthly. A local
     * preference like the one above: it works while the points service is down and only ever changes the signed-in row.
     * Nobody is sent a summary unless they pick one here.
     */
    public function updateDigestEmails(Request $request): RedirectResponse
    {
        $data = $request->validate(['points_digest' => ['required', 'in:'.implode(',', User::POINTS_DIGEST_CHOICES)]]);

        // A query-builder update, not ->save(): this is one flag and should not re-index the person for search.
        User::whereKey($request->user()->getKey())->update(['points_digest' => $data['points_digest']]);

        return redirect()->to(route('points').'#digest-emails')->with('digest_notice', match ($data['points_digest']) {
            'week' => 'We will email you a short summary of your points each Monday, only in weeks when you earned some.',
            'month' => 'We will email you a short summary of your points at the start of each month, only in months when you earned some.',
            default => 'We will not email you summaries of your points.',
        });
    }

    /**
     * Before the first request for a form, check the reward is still listed at the cost the person confirmed. The
     * service charges the current cost, so a changed price must be seen before it is paid, not after.
     *
     * @param  array{reward_id: int, cost: int}  $ticket
     */
    private function priceStillMatches(string $token, array $ticket): ?RedirectResponse
    {
        try {
            $current = collect($this->points->rewards())->first(fn (Reward $r) => $r->id === $ticket['reward_id']);
        } catch (PointsUnavailableException) {
            return $this->back('error', 'The points service is unavailable right now, so nothing was spent. Try again in a moment.');
        }

        if ($current === null || ! $current->available) {
            $message = 'That reward is not available any more. Nothing was spent.';
            $this->tickets->consume($token, ['level' => 'error', 'message' => $message]);

            return $this->back('error', $message);
        }
        if ($current->costPoints !== $ticket['cost']) {
            $message = $current->name.' now costs '.number_format($current->costPoints).' points (you saw '.number_format($ticket['cost']).'). Nothing was spent. Review it and confirm again.';
            $this->tickets->consume($token, ['level' => 'error', 'message' => $message]);

            return $this->back('error', $message);
        }

        return null;
    }

    private function back(string $level, string $message): RedirectResponse
    {
        return redirect()->route('points')->with('points_notice', ['level' => $level, 'message' => $message]);
    }
}

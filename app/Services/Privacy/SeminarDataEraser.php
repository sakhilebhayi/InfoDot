<?php

namespace App\Services\Privacy;

use App\Models\Seminar;
use App\Models\SeminarRegistration;
use App\Models\User;
use App\Services\Reputation\ReputationEngine;
use App\Services\Seminars\SeminarService;
use Illuminate\Support\Facades\DB;

/**
 * What deleting an InfoDot account does to everything the seminar, reputation and badge features hold about the person.
 *
 * Done in two steps so nobody is left owed money: first the live obligations are settled through the normal paths (their upcoming seminars are
 * cancelled and every ticket holder refunded in full; their own upcoming registrations are cancelled by the usual refund rule), then the personal
 * data goes. Records that money moved through are kept for tax and dispute purposes with nothing readable on them (the person's name is gone, the
 * user row is gone, a former member is shown as "A former member"): paid tickets, refund lines, ticket events, commission invoices, and the seller
 * details and host agreement of a host who sold tickets or still owes a debt. Everything else about the person is deleted.
 */
class SeminarDataEraser
{
    public function __construct(private readonly SeminarService $seminars, private readonly ReputationEngine $reputation) {}

    /** Settles what is live. Call before the account is deleted and outside the delete transaction (it talks to Billing). */
    public function settle(User $user): void
    {
        foreach (Seminar::where('host_id', $user->id)->where('status', '!=', Seminar::CANCELLED)->get() as $seminar) {
            if (! $seminar->hasEnded()) {
                $this->seminars->cancel($seminar);
            }
        }

        $upcoming = SeminarRegistration::where('user_id', $user->id)->whereIn('status', [SeminarRegistration::REGISTERED, SeminarRegistration::WAITLIST])->pluck('seminar_id');

        foreach (Seminar::whereIn('id', $upcoming)->get() as $seminar) {
            if (! $seminar->hasEnded()) {
                $this->seminars->unregister($seminar, $user);
            }
        }
    }

    /** Removes the personal data. Safe to run in the delete transaction and safe to run twice. */
    public function erase(User $user): void
    {
        $id = $user->id;

        // Their own seminars: with no ticket ever sold, the seminar and everything about it goes; with tickets, the record stays but the content and link go.
        foreach (Seminar::where('host_id', $id)->get() as $seminar) {
            $sold = SeminarRegistration::where('seminar_id', $seminar->id)->whereNotNull('ticket_ref')->exists();

            if ($sold) {
                $seminar->forceFill(['description' => null, 'join_url' => '', 'summary' => 'This seminar was hosted by a former member.'])->save();
                $this->dropRoom($seminar->id);

                continue;
            }

            $this->dropRoom($seminar->id);
            DB::table('seminar_feedback')->where('seminar_id', $seminar->id)->delete();
            DB::table('seminar_registrations')->where('seminar_id', $seminar->id)->delete();
            DB::table('seminar_link_reports')->where('seminar_id', $seminar->id)->delete();
            DB::table('seminar_reports')->where('seminar_id', $seminar->id)->delete();
            DB::table('seminar_room_bans')->where('seminar_id', $seminar->id)->delete();
            DB::table('seminar_removal_appeals')->where('seminar_id', $seminar->id)->delete();
            $seminar->delete();
        }

        // As an attendee: free registrations go; a ticket someone paid for stays as a money record.
        SeminarRegistration::where('user_id', $id)->whereNull('ticket_ref')->delete();
        // Ratings count toward a host's average and stay, the written note to the host goes.
        DB::table('seminar_feedback')->where('user_id', $id)->update(['comment' => null]);

        foreach (['seminar_link_reports' => 'user_id', 'seminar_reports' => 'reporter_id', 'seminar_room_bans' => 'user_id', 'seminar_payout_accounts' => 'user_id'] as $table => $column) {
            DB::table($table)->where($column, $id)->delete();
        }

        DB::table('seminar_removal_appeals')->where('host_id', $id)->delete();
        $this->dropPeers(DB::table('p2p_peers')->where('user_id', $id)->pluck('peer_id')->all(), $id);

        // Seller details and the agreement are kept only where money moved or is still owed.
        $moneyMoved = DB::table('seminar_refund_lines')->where('host_id', $id)->exists()
            || DB::table('seminar_commission_invoices')->where('host_id', $id)->exists()
            || DB::table('seminar_host_debts')->where('user_id', $id)->where('status', 'open')->exists();

        if (! $moneyMoved) {
            DB::table('seminar_seller_profiles')->where('user_id', $id)->delete();
            DB::table('seminar_host_agreements')->where('user_id', $id)->delete();
            DB::table('seminar_host_debts')->where('user_id', $id)->delete();
        }

        $this->reputation->forget($user);
        DB::table('user_topic_badges')->where('user_id', $id)->delete();
    }

    private function dropRoom(int $seminarId): void
    {
        $this->dropPeers(DB::table('p2p_peers')->where('seminar_id', $seminarId)->pluck('peer_id')->all(), null);
        DB::table('p2p_chat')->where('seminar_id', $seminarId)->delete();
    }

    /** @param  list<string>  $peerIds */
    private function dropPeers(array $peerIds, ?int $chatUserId): void
    {
        if ($peerIds !== []) {
            DB::table('p2p_signals')->where(fn ($q) => $q->whereIn('from_peer', $peerIds)->orWhereIn('to_peer', $peerIds))->delete();
            DB::table('p2p_peers')->whereIn('peer_id', $peerIds)->delete();
        }

        if ($chatUserId !== null) {
            DB::table('p2p_chat')->where('user_id', $chatUserId)->delete();
        }
    }
}

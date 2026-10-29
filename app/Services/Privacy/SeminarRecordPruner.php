<?php

namespace App\Services\Privacy;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Deletes seminar-feature records on the schedule the owner chose (config/seminars.php `retention`). Counts only, never content.
 *
 * - Small-room data (peers, connection messages, chat, room bans): a number of days after the seminar ended.
 * - Sent Dot Points rows: a number of days after they were sent.
 * - Closed reports about seminars and decided removal appeals: a number of months after they were decided.
 * - Money records of people whose account no longer exists: a number of years after the last payment or invoice, unless a dispute or a debt is open.
 *   Nothing is deleted for a person who still has an account; that is a separate decision.
 */
class SeminarRecordPruner
{
    /** @return array<string, int> what was (or, with $dry, would be) removed, by kind */
    public function run(bool $dry = false): array
    {
        $r = config('seminars.retention');
        $out = [];

        if (($days = (int) $r['room_days']) > 0) {
            $out += $this->rooms($days, $dry);
        }

        if (($days = (int) $r['points_outbox_days']) > 0) {
            $out['points_rows'] = $this->take(DB::table('reward_outbox')->whereNotNull('sent_at')->where('sent_at', '<', now()->subDays($days)), $dry);
        }

        if (($months = (int) $r['reports_months']) > 0) {
            $cut = now()->subMonths($months);
            $out['closed_reports'] = $this->take(DB::table('seminar_reports')->where('status', '!=', 'open')->where('resolved_at', '<', $cut), $dry);
            $out['decided_appeals'] = $this->take(DB::table('seminar_removal_appeals')->where('status', '!=', 'open')->where('resolved_at', '<', $cut), $dry);
        }

        if (($years = (int) $r['money_years']) > 0) {
            $out += $this->money(now()->subYears($years), $dry);
        }

        return $out;
    }

    /** @return array<string, int> */
    private function rooms(int $days, bool $dry): array
    {
        $cut = now()->subDays($days);
        $n = ['room_peers' => 0, 'room_messages' => 0, 'room_chat' => 0, 'room_bans' => 0];

        DB::table('seminars')->where('starts_at', '<', $cut)->select('id', 'starts_at', 'duration_minutes')->orderBy('id')->chunkById(500, function ($seminars) use ($cut, $dry, &$n) {
            $ids = $seminars->filter(fn ($s) => Carbon::parse($s->starts_at)->addMinutes((int) $s->duration_minutes)->lt($cut))->pluck('id')->all();

            if ($ids === []) {
                return;
            }

            $peerIds = DB::table('p2p_peers')->whereIn('seminar_id', $ids)->pluck('peer_id')->all();

            if ($peerIds !== []) {
                $n['room_messages'] += $this->take(DB::table('p2p_signals')->where(fn ($q) => $q->whereIn('from_peer', $peerIds)->orWhereIn('to_peer', $peerIds)), $dry);
            }

            $n['room_peers'] += $this->take(DB::table('p2p_peers')->whereIn('seminar_id', $ids), $dry);
            $n['room_chat'] += $this->take(DB::table('p2p_chat')->whereIn('seminar_id', $ids), $dry);
            $n['room_bans'] += $this->take(DB::table('seminar_room_bans')->whereIn('seminar_id', $ids), $dry);
        });

        return $n;
    }

    /** @return array<string, int> */
    private function money(Carbon $cut, bool $dry): array
    {
        $gone = fn (Builder $q, string $column) => $q->whereNotExists(fn ($x) => $x->from('users')->whereColumn('users.id', $column));
        $n = [];

        // Tickets of people who no longer have an account, and the payment events and refund lines that hang off them.
        $tickets = $gone(DB::table('seminar_registrations')->whereNotNull('ticket_ref')->where(fn ($q) => $q->whereNull('dispute_status')->orWhere('dispute_status', '!=', 'open'))
            ->whereRaw('coalesce(paid_at, created_at) < ?', [$cut]), 'seminar_registrations.user_id');
        $rows = (clone $tickets)->get(['id', 'ticket_ref']);

        if ($rows->isNotEmpty() && ! $dry) {
            foreach ($rows->chunk(500) as $chunk) {
                DB::table('seminar_ticket_events')->whereIn('ticket_ref', $chunk->pluck('ticket_ref')->all())->delete();
                DB::table('seminar_refund_lines')->whereIn('registration_id', $chunk->pluck('id')->all())->delete();
            }
        }

        $n['tickets'] = $this->take($tickets, $dry);

        $n['commission_invoices'] = $this->take($gone(DB::table('seminar_commission_invoices')->where('issued_at', '<', $cut), 'seminar_commission_invoices.host_id'), $dry);

        // A host who is gone: their seller details, agreement and settled debts, once nothing financial of theirs is left to explain.
        $hosts = DB::table('seminar_seller_profiles')->pluck('user_id')->merge(DB::table('seminar_host_agreements')->pluck('user_id'))->merge(DB::table('seminar_host_debts')->pluck('user_id'))->unique();
        $n['host_records'] = 0;

        foreach ($hosts as $uid) {
            $stillNeeded = DB::table('users')->where('id', $uid)->exists()
                || DB::table('seminar_commission_invoices')->where('host_id', $uid)->exists()
                || DB::table('seminar_refund_lines')->where('host_id', $uid)->exists()
                || DB::table('seminar_host_debts')->where('user_id', $uid)->where('status', 'open')->exists()
                || DB::table('seminar_host_debts')->where('user_id', $uid)->where('last_event_at', '>=', $cut)->exists()
                || DB::table('seminar_seller_profiles')->where('user_id', $uid)->where('updated_at', '>=', $cut)->exists()
                || DB::table('seminar_host_agreements')->where('user_id', $uid)->where('accepted_at', '>=', $cut)->exists();

            if ($stillNeeded) {
                continue;
            }

            foreach (['seminar_seller_profiles', 'seminar_host_agreements', 'seminar_host_debts'] as $table) {
                $n['host_records'] += $this->take(DB::table($table)->where('user_id', $uid), $dry);
            }
        }

        // The shell of a seminar whose host is gone, once nothing is left that refers to it.
        $n['seminars'] = $this->take($gone(DB::table('seminars')->where('starts_at', '<', $cut), 'seminars.host_id')
            ->whereNotExists(fn ($x) => $x->from('seminar_registrations')->whereColumn('seminar_registrations.seminar_id', 'seminars.id'))
            ->whereNotExists(fn ($x) => $x->from('seminar_refund_lines')->whereColumn('seminar_refund_lines.seminar_id', 'seminars.id')), $dry);

        return $n;
    }

    private function take(Builder $query, bool $dry): int
    {
        return $dry ? (int) $query->count() : (int) $query->delete();
    }
}

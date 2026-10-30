<?php

namespace App\Services\Privacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Everything the seminar features hold about one person, for them to keep (POPIA section 23). Names of other people are never included: the
 * ratings a host received come without who gave them, as the host already sees them. The bank account number is never held, so it is not here.
 */
class SeminarDataExporter
{
    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        $id = $user->id;
        $titles = fn (array $ids) => $ids === [] ? collect() : DB::table('seminars')->whereIn('id', array_unique($ids))->pluck('title', 'id');
        $iso = fn ($v) => $v === null ? null : Carbon::parse($v)->toIso8601String();

        $hosted = DB::table('seminars')->where('host_id', $id)->orderBy('id')->get();
        $regs = DB::table('seminar_registrations')->where('user_id', $id)->orderBy('id')->get();
        $feedback = DB::table('seminar_feedback')->where('user_id', $id)->orderBy('id')->get();
        $chat = DB::table('p2p_chat')->where('user_id', $id)->orderBy('id')->get();
        $reports = DB::table('seminar_reports')->where('reporter_id', $id)->orderBy('id')->get();
        $linkReports = DB::table('seminar_link_reports')->where('user_id', $id)->get();
        $bans = DB::table('seminar_room_bans')->where('user_id', $id)->get();
        $name = $titles(array_merge($regs->pluck('seminar_id')->all(), $feedback->pluck('seminar_id')->all(), $chat->pluck('seminar_id')->all(), $reports->pluck('seminar_id')->all(), $linkReports->pluck('seminar_id')->all(), $bans->pluck('seminar_id')->all()));

        return [
            'person' => $user->email,
            'exported_at' => now()->toIso8601String(),
            'note' => 'Everything the InfoDot seminar features hold about you. Your bank account number is never stored by InfoDot, so it is not here. Records held by Dot.Billing and Paystack are not included.',
            'hosted_seminars' => $hosted->map(fn ($s) => [
                'title' => $s->title, 'summary' => $s->summary, 'description' => $s->description, 'starts_at' => $iso($s->starts_at), 'duration_minutes' => $s->duration_minutes,
                'capacity' => $s->capacity, 'status' => $s->status, 'price_cents' => $s->price_cents, 'currency' => $s->currency, 'meeting_link' => $s->join_url,
                'removed_at' => $iso($s->removed_at ?? null), 'removed_reason' => $s->removed_reason ?? null,
                'ratings_received' => DB::table('seminar_feedback')->where('seminar_id', $s->id)->get(['rating', 'comment'])->map(fn ($f) => ['rating' => $f->rating, 'note' => $f->comment])->all(),
            ])->all(),
            'registrations' => $regs->map(fn ($r) => [
                'seminar' => $name[$r->seminar_id] ?? null, 'status' => $r->status, 'attended_at' => $iso($r->attended_at),
                'payment_status' => $r->payment_status, 'amount_cents' => $r->amount_cents, 'currency' => $r->currency, 'paid_at' => $iso($r->paid_at), 'refunded_cents' => $r->refunded_cents,
                'refunded_at' => $iso($r->refunded_at), 'dispute_status' => $r->dispute_status, 'ticket_ref' => $r->ticket_ref,
            ])->all(),
            'ratings_given' => $feedback->map(fn ($f) => ['seminar' => $name[$f->seminar_id] ?? null, 'rating' => $f->rating, 'note' => $f->comment, 'given_at' => $iso($f->created_at)])->all(),
            'room_chat_messages' => $chat->map(fn ($c) => ['seminar' => $name[$c->seminar_id] ?? null, 'name_shown' => $c->name, 'message' => $c->body, 'sent_at' => $iso($c->created_at)])->all(),
            'reports_made' => $reports->map(fn ($r) => ['seminar' => $name[$r->seminar_id] ?? null, 'reason' => $r->reason, 'about_person' => $r->about_name, 'note' => $r->detail, 'status' => $r->status, 'made_at' => $iso($r->created_at)])->all(),
            'broken_link_reports_made' => $linkReports->map(fn ($r) => ['seminar' => $name[$r->seminar_id] ?? null, 'at' => $iso($r->created_at)])->all(),
            'removed_from_rooms' => $bans->map(fn ($b) => ['seminar' => $name[$b->seminar_id] ?? null, 'at' => $iso($b->created_at)])->all(),
            'removal_appeals' => DB::table('seminar_removal_appeals')->where('host_id', $id)->get()->map(fn ($a) => ['message' => $a->message, 'status' => $a->status, 'answer' => $a->resolution_note, 'sent_at' => $iso($a->created_at)])->all(),
            'payout_account' => DB::table('seminar_payout_accounts')->where('user_id', $id)->get(['bank_code', 'account_last4', 'status'])->all(),
            'seller_details' => DB::table('seminar_seller_profiles')->where('user_id', $id)->get(['trading_name', 'vat_number', 'authorises_receipts'])->all(),
            'host_agreements_accepted' => DB::table('seminar_host_agreements')->where('user_id', $id)->get(['version', 'accepted_at'])->all(),
            'debts' => DB::table('seminar_host_debts')->where('user_id', $id)->get(['amount_cents', 'recovered_cents', 'remaining_cents', 'currency', 'status', 'note', 'opened_at'])->all(),
            'commission_invoices' => DB::table('seminar_commission_invoices')->where('host_id', $id)->orderBy('id')->get(['number', 'kind', 'period', 'fee_cents', 'vat_cents', 'excl_vat_cents', 'currency', 'issued_at'])->all(),
            'refund_lines' => DB::table('seminar_refund_lines')->where('host_id', $id)->orderBy('id')->get(['amount_cents', 'reason', 'created_at'])->all(),
        ];
    }
}

<?php

namespace App\Services\Seminars;

use App\Models\Seminar;
use App\Models\SeminarCommissionInvoice;
use App\Models\SeminarRegistration;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * InfoDot's monthly commission invoice to each host (the host is the seller; InfoDot keeps a percentage of each ticket and invoices that service).
 * One per host per month, numbered in sequence per year; a month where refunds outweigh sales is a credit note. The commission is treated as
 * VAT-inclusive, so the VAT is taken out of the percentage the host was told rather than added on top (config). Nothing is issued while InfoDot's
 * own legal name, address and VAT number are not configured. NOT legal or tax advice: an accountant confirms this before it is relied on.
 */
class CommissionInvoices
{
    public function configured(): bool
    {
        $i = config('seminars.paid.issuer');

        return filled($i['name'] ?? null) && filled($i['address'] ?? null) && filled($i['vat_number'] ?? null);
    }

    /** Commission on an amount, rounded half up. */
    public static function fee(int $cents): int
    {
        return (int) floor($cents * (int) config('seminars.paid.platform_percent', 10) / 100 + 0.5);
    }

    /** @return int number of invoices issued for the month */
    public function issueMonth(Carbon $month): int
    {
        if (! $this->configured()) {
            Log::warning('Seminar commission invoices not issued: InfoDot issuer details are not configured.');

            return 0;
        }

        $n = 0;
        [$from, $to] = $this->bounds($month);
        $hosts = Seminar::whereIn('id', SeminarRegistration::whereNotNull('paid_at')->whereBetween('paid_at', [$from, $to])->select('seminar_id'))->pluck('host_id')
            ->merge(DB::table('seminar_refund_lines')->whereBetween('created_at', [$from, $to])->pluck('host_id'))->unique();

        foreach ($hosts as $hostId) {
            $host = User::find($hostId);

            // A host whose account was deleted cannot be invoiced; one missing person must not stop everyone else's invoice.
            if ($host === null) {
                Log::warning('Seminar commission invoice skipped: the host account no longer exists.', ['host_id' => $hostId, 'period' => $month->format('Y-m')]);

                continue;
            }

            $n += (int) ($this->issueFor($host, $month) !== null);
        }

        return $n;
    }

    public function issueFor(User $host, Carbon $month): ?SeminarCommissionInvoice
    {
        $period = $month->format('Y-m');

        if ($existing = SeminarCommissionInvoice::where('host_id', $host->id)->where('period', $period)->first()) {
            return $existing;
        }

        if (! $this->configured()) {
            return null;
        }

        [$from, $to] = $this->bounds($month);
        $lines = $this->lines($host, $from, $to);

        if ($lines === []) {
            return null;
        }

        $fee = (int) array_sum(array_column($lines, 'fee_cents'));
        $rate = (int) config('seminars.paid.vat_rate_percent', 15);
        $vat = config('seminars.paid.commission_includes_vat', true)
            ? (int) ($fee < 0 ? -1 : 1) * (int) floor(abs($fee) * $rate / (100 + $rate) + 0.5)
            : (int) ($fee < 0 ? -1 : 1) * (int) floor(abs($fee) * $rate / 100 + 0.5);
        $inclusive = (bool) config('seminars.paid.commission_includes_vat', true);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                return DB::transaction(function () use ($host, $month, $period, $lines, $fee, $vat, $inclusive) {
                    $year = (int) $month->format('Y');
                    $seq = (int) SeminarCommissionInvoice::where('year', $year)->max('seq') + 1;

                    return SeminarCommissionInvoice::create([
                        'host_id' => $host->id, 'period' => $period, 'year' => $year, 'seq' => $seq,
                        'number' => config('seminars.paid.invoice_prefix', 'INF').'-'.$year.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                        'kind' => $fee < 0 ? 'credit_note' : 'invoice', 'fee_cents' => $fee, 'vat_cents' => $vat,
                        'excl_vat_cents' => $inclusive ? $fee - $vat : $fee, 'currency' => config('seminars.paid.currency', 'ZAR'),
                        'lines' => $lines, 'issuer' => config('seminars.paid.issuer') + ['vat_rate_percent' => (int) config('seminars.paid.vat_rate_percent', 15), 'commission_includes_vat' => $inclusive],
                        'issued_at' => now(),
                    ]);
                });
            } catch (UniqueConstraintViolationException) {
                // Another run took the next number a moment ago, or issued this month's invoice; try again once, then return whatever exists.
            }
        }

        return SeminarCommissionInvoice::where('host_id', $host->id)->where('period', $period)->first();
    }

    /** @return list<array<string, mixed>> */
    private function lines(User $host, Carbon $from, Carbon $to): array
    {
        $lines = [];

        $sales = SeminarRegistration::join('seminars', 'seminars.id', '=', 'seminar_registrations.seminar_id')
            ->where('seminars.host_id', $host->id)->whereNotNull('seminar_registrations.paid_at')->whereBetween('seminar_registrations.paid_at', [$from, $to])
            ->orderBy('seminar_registrations.paid_at')->get(['seminar_registrations.ticket_ref', 'seminar_registrations.amount_cents', 'seminars.title', 'seminar_registrations.paid_at']);

        foreach ($sales as $s) {
            $lines[] = ['type' => 'sale', 'on' => Carbon::parse($s->paid_at)->toDateString(), 'seminar' => $s->title, 'ticket' => $s->ticket_ref, 'amount_cents' => (int) $s->amount_cents, 'fee_cents' => self::fee((int) $s->amount_cents)];
        }

        $refunds = DB::table('seminar_refund_lines as r')->join('seminar_registrations as g', 'g.id', '=', 'r.registration_id')->join('seminars as s', 's.id', '=', 'r.seminar_id')
            ->where('r.host_id', $host->id)->whereBetween('r.created_at', [$from, $to])->orderBy('r.created_at')->get(['r.amount_cents', 'r.reason', 'r.created_at', 'g.ticket_ref', 's.title']);

        foreach ($refunds as $r) {
            $lines[] = ['type' => $r->reason, 'on' => Carbon::parse($r->created_at)->toDateString(), 'seminar' => $r->title, 'ticket' => $r->ticket_ref, 'amount_cents' => -(int) $r->amount_cents, 'fee_cents' => -self::fee((int) $r->amount_cents)];
        }

        return $lines;
    }

    /** @return array{0: Carbon, 1: Carbon} the month in the app's timezone, as UTC instants */
    private function bounds(Carbon $month): array
    {
        $tz = config('app.timezone');

        return [Carbon::parse($month->format('Y-m-01'), $tz)->startOfMonth()->utc(), Carbon::parse($month->format('Y-m-01'), $tz)->endOfMonth()->utc()];
    }
}

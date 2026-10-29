<?php

namespace App\Services\Seminars;

use App\Models\SeminarHostDebt;
use App\Models\SeminarRegistration;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Support\Carbon;

/**
 * Keeps a host's view of what they owe after a lost chargeback in step with what Dot.Billing says (opened, cleared, reopened, written off), and
 * tells the host each time. Billing is the record: this only mirrors it. A callback that arrives late or twice never moves a debt backwards.
 */
class HostDebtLedger
{
    private const EVENTS = ['host_debt.opened' => 'open', 'host_debt.reopened' => 'open', 'host_debt.cleared' => 'cleared', 'host_debt.written_off' => 'written_off'];

    /** Which field carries the time of each kind of event (a body may carry more than one, so the event decides). */
    private const STAMPS = ['host_debt.opened' => 'opened_at', 'host_debt.reopened' => 'reopened_at', 'host_debt.cleared' => 'cleared_at', 'host_debt.written_off' => 'written_off_at'];

    private const NOTICES = [
        'host_debt.opened' => UserNotice::SEMINAR_DEBT_OPENED, 'host_debt.reopened' => UserNotice::SEMINAR_DEBT_REOPENED,
        'host_debt.cleared' => UserNotice::SEMINAR_DEBT_CLEARED, 'host_debt.written_off' => UserNotice::SEMINAR_DEBT_WRITTEN_OFF,
    ];

    public function __construct(private readonly InAppNotices $notices) {}

    /** @param  array<string, mixed>  $b  the callback body  @return string applied|stale|unknown|ignored */
    public function apply(array $b): string
    {
        $event = (string) ($b['event'] ?? '');

        if (! isset(self::EVENTS[$event]) || ! is_int($b['debt_id'] ?? null) || ! preg_match('/^u(\d+)$/', (string) ($b['host_ref'] ?? ''), $m)) {
            return 'ignored';
        }

        $host = User::find((int) $m[1]);

        if ($host === null) {
            return 'unknown';
        }

        $stamp = Carbon::parse($b[self::STAMPS[$event]] ?? now());
        $debt = SeminarHostDebt::firstOrNew(['billing_debt_id' => $b['debt_id']]);

        if ($debt->exists && $debt->last_event_at !== null && $debt->last_event_at->greaterThan($stamp)) {
            return 'stale';
        }

        $debt->forceFill([
            'user_id' => $host->id, 'ticket_ref' => mb_substr((string) ($b['ticket_ref'] ?? ''), 0, 64) ?: null, 'currency' => strtoupper((string) ($b['currency'] ?? 'ZAR')),
            'amount_cents' => (int) ($b['amount_cents'] ?? 0), 'recovered_cents' => (int) ($b['recovered_cents'] ?? 0), 'remaining_cents' => (int) ($b['remaining_cents'] ?? 0),
            'status' => self::EVENTS[$event], 'note' => $event === 'host_debt.written_off' ? mb_substr((string) ($b['note'] ?? ''), 0, 500) ?: null : $debt->note,
            'opened_at' => $debt->opened_at ?? ($event === 'host_debt.opened' ? $stamp : null), 'last_event_at' => $stamp,
        ])->save();

        $seminarId = SeminarRegistration::where('ticket_ref', $debt->ticket_ref)->value('seminar_id');

        if ($seminarId !== null) {
            $this->notices->record(self::NOTICES[$event], $host, 'seminar', (int) $seminarId, 'seminar:'.$seminarId.':debt:'.$debt->billing_debt_id.':'.$event.':'.$stamp->timestamp);
        }

        return 'applied';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What a host owes InfoDot after a lost chargeback, as Dot.Billing last told us. Billing is the record; this is for showing the host. */
class SeminarHostDebt extends Model
{
    protected $fillable = ['user_id', 'billing_debt_id', 'ticket_ref', 'currency', 'amount_cents', 'recovered_cents', 'remaining_cents', 'status', 'note', 'opened_at', 'last_event_at'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'last_event_at' => 'datetime', 'amount_cents' => 'integer', 'recovered_cents' => 'integer', 'remaining_cents' => 'integer'];
    }
}

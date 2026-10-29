<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One monthly tax invoice (or credit note) from InfoDot to a host for the commission it kept. A snapshot: never edited after issue. */
class SeminarCommissionInvoice extends Model
{
    protected $fillable = ['host_id', 'period', 'year', 'seq', 'number', 'kind', 'fee_cents', 'vat_cents', 'excl_vat_cents', 'currency', 'lines', 'issuer', 'issued_at'];

    protected function casts(): array
    {
        return ['lines' => 'array', 'issuer' => 'array', 'issued_at' => 'datetime', 'fee_cents' => 'integer', 'vat_cents' => 'integer', 'excl_vat_cents' => 'integer'];
    }
}

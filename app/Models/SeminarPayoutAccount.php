<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A host's payout destination as Finance knows it. Holds Finance's id and the last four digits only; never the account number. */
class SeminarPayoutAccount extends Model
{
    protected $fillable = ['user_id', 'finance_account_id', 'bank_code', 'account_last4', 'status'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What a receipt says about the seller (the host): trading name, optional VAT number, and whether they authorised InfoDot to issue receipts for them. */
class SeminarSellerProfile extends Model
{
    protected $fillable = ['user_id', 'trading_name', 'vat_number', 'authorises_receipts'];

    protected function casts(): array
    {
        return ['authorises_receipts' => 'boolean'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One platform (and optionally which of its plans) inside a bundle (R-50). */
class PlatformBundleItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}

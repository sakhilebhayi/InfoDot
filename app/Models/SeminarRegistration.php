<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeminarRegistration extends Model
{
    public const REGISTERED = 'registered';

    public const WAITLIST = 'waitlist';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['seminar_id', 'user_id', 'status', 'attended_at'];

    protected function casts(): array
    {
        return ['attended_at' => 'datetime'];
    }

    public function seminar(): BelongsTo
    {
        return $this->belongsTo(Seminar::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

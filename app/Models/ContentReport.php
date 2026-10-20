<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentReport extends Model
{
    public const REASONS = ['spam', 'duplicate', 'low_quality', 'harmful', 'other'];

    protected $fillable = ['reportable_type', 'reportable_id', 'reporter_id', 'reason', 'detail', 'status'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }
}

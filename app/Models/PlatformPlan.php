<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One way a platform can be had (R-50): free, included, or priced. Prices are nullable on purpose: no price is ever
 * invented, and a plan can be sold only when a platform administrator has approved it and it carries an amount and a
 * currency.
 *
 * @property string $platform_key
 * @property string $model
 * @property int|null $amount_minor
 * @property string|null $currency
 * @property bool $active
 */
class PlatformPlan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['limits' => 'array', 'features' => 'array', 'approved_at' => 'datetime', 'active' => 'boolean', 'amount_minor' => 'integer'];
    }

    public function isSellable(): bool
    {
        return $this->active && $this->approved_at !== null && $this->amount_minor !== null && $this->currency !== null;
    }

    /** An approved plan that costs nothing. */
    public function isFree(): bool
    {
        return $this->isSellable() && $this->model === 'free' && $this->amount_minor === 0;
    }
}

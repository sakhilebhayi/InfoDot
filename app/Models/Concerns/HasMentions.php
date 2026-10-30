<?php

namespace App\Models\Concerns;

use App\Models\Mention;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Content that people and businesses can be mentioned on (R-16): questions, answers, comments. */
trait HasMentions
{
    public static function bootHasMentions(): void
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->mentions()->delete();
        });
    }

    public function mentions(): MorphMany
    {
        return $this->morphMany(Mention::class, 'mentionable');
    }
}

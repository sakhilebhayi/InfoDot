<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Content that can carry uploaded files (R-15): questions, solutions, solution steps, answers, comments. Deleting the
 * content deletes its attachments and their files; a soft delete keeps them (the row may come back).
 */
trait HasAttachments
{
    public static function bootHasAttachments(): void
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->attachments()->get()->each->delete();
        });
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('sort_order')->orderBy('id');
    }

    /** image/pdf/video counts for the list pages' indicators, as {kind}_attachments_count. */
    public function scopeWithAttachmentCounts($query)
    {
        return $query->withCount([
            'attachments as image_attachments_count' => fn ($q) => $q->where('kind', Attachment::IMAGE),
            'attachments as pdf_attachments_count' => fn ($q) => $q->where('kind', Attachment::PDF),
            'attachments as video_attachments_count' => fn ($q) => $q->where('kind', Attachment::VIDEO),
        ]);
    }

    /** ?has=images|documents|videos on the list pages. An unknown word filters nothing. */
    public function scopeHasAttachmentKind($query, string $word)
    {
        $kind = Attachment::kindFromWord($word);

        return $kind === null ? $query : $query->whereHas('attachments', fn ($q) => $q->where('kind', $kind));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One uploaded file on a question, solution, solution step, answer or comment (R-15). A row starts unbound — only its
 * owner can see it and it expires — and is bound to its content when the form is submitted. The file itself sits on
 * the private local disk and is only reachable through AttachmentController, which asks AttachmentService whether the
 * viewer may see the content it belongs to.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $team_id
 * @property string|null $attachable_type
 * @property int|null $attachable_id
 * @property string $kind image|pdf|video
 * @property string $path
 * @property string|null $thumb_path
 * @property string $name
 * @property string $mime
 * @property int $size
 * @property string|null $caption
 * @property int $sort_order
 * @property string $status
 * @property string|null $extracted_text
 * @property array|null $meta
 * @property Carbon|null $expires_at
 */
class Attachment extends Model
{
    public const IMAGE = 'image';

    public const PDF = 'pdf';

    public const VIDEO = 'video';

    public const DISK = 'local';

    protected $fillable = ['caption', 'sort_order'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'expires_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    /** Business media (R-29): a logo, cover or gallery row that is currently on the profile. */
    public function scopeCurrentMedia(Builder $query): Builder
    {
        return $query->whereNotNull('role')->whereNull('superseded_at');
    }

    protected static function booted(): void
    {
        static::creating(fn (self $attachment) => $attachment->uuid ??= (string) Str::uuid());

        // The row is the only pointer to the files: when it goes, they go.
        static::deleting(function (self $attachment) {
            Storage::disk(self::DISK)->delete(array_filter([$attachment->path, $attachment->thumb_path]));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the file this row points at is still on the disk. A row can outlive its file (a database restored
     * without the storage folder, a failed copy on the host); the file's address then answers 404, so pages ask
     * here first and show their fallback instead of a picture that cannot load. Asked once per request.
     */
    public function onDisk(): bool
    {
        $key = 'attachment-on-disk:'.$this->path;
        if (! request()->attributes->has($key)) {
            request()->attributes->set($key, Storage::disk(self::DISK)->exists($this->path));
        }

        return (bool) request()->attributes->get($key);
    }

    /** The row when its file can be shown, otherwise null: what a page showing one picture (a logo, a cover) asks for. */
    public static function shown(?self $attachment): ?self
    {
        return $attachment?->onDisk() ? $attachment : null;
    }

    public function isBound(): bool
    {
        return $this->attachable_id !== null;
    }

    /** "2.4 MB" for the viewers and download rows. */
    public function sizeLabel(): string
    {
        $bytes = (float) $this->size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, $bytes >= 10485760 ? 0 : 1).' MB';
        }

        return $bytes >= 1024 ? round($bytes / 1024).' KB' : $bytes.' B';
    }

    /** The kind a list filter's word means ("documents" → pdf); null for anything else. */
    public static function kindFromWord(string $word): ?string
    {
        return [self::IMAGE => self::IMAGE, 'images' => self::IMAGE, self::PDF => self::PDF, 'documents' => self::PDF, self::VIDEO => self::VIDEO, 'videos' => self::VIDEO][$word] ?? null;
    }

    /** Unbound leftovers whose day has passed; attachments:prune deletes them (and their files). */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNull('attachable_id')->whereNotNull('expires_at')->where('expires_at', '<', now());
    }
}

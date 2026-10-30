<?php

namespace App\Services\Attachments;

use App\Models\Answer;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Steps;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;

/**
 * Every rule about uploaded files lives here (R-15): what may be uploaded, how much, who may see a file, and how an
 * upload becomes part of a question, solution, step, answer or comment. The controllers and composers only ask.
 *
 * The type of a file is decided by sniffing its content (fileinfo), never by its name, and the extension has to agree
 * with the sniffed type. Files are stored on the private local disk; nothing under public/.
 */
class AttachmentService
{
    public function __construct(private readonly AnswerService $answers) {}

    // ------------------------------------------------------------------------------------------------- uploads

    /** Validate and store one file for this person. The attachment starts unbound and expires unless a form binds it. */
    public function upload(User $user, UploadedFile $file): Attachment
    {
        if (! $file->isValid() || $file->getSize() <= 0) {
            throw ValidationException::withMessages(['file' => 'The upload did not arrive intact. Try again.']);
        }

        [$kind, $mime] = $this->sniff($file);

        $max = (int) config('attachments.kinds.'.$kind.'.max_bytes');
        if ($file->getSize() > $max) {
            throw ValidationException::withMessages(['file' => 'A '.$kind.' may be at most '.round($max / 1048576).' MB.']);
        }

        $this->guardDailyQuota($user, (int) $file->getSize());

        $meta = [];
        if ($kind === Attachment::IMAGE) {
            $meta = $this->imageMeta($file); // throws on decompression bombs
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $uuid = (string) Str::uuid();
        $path = $file->storeAs('attachments/'.now()->format('Y/m'), $uuid.'.'.$extension, Attachment::DISK);

        if ($path === false) {
            throw ValidationException::withMessages(['file' => 'The file could not be stored. Try again.']);
        }

        $attachment = new Attachment;
        $attachment->uuid = $uuid;
        $attachment->user_id = $user->id;
        $attachment->team_id = $user->current_team_id;
        $attachment->kind = $kind;
        $attachment->path = $path;
        $attachment->name = $this->displayName($file, $extension);
        $attachment->mime = $mime;
        $attachment->size = (int) $file->getSize();
        $attachment->meta = $meta ?: null;
        $attachment->expires_at = now()->addHours((int) config('attachments.unbound_ttl_hours'));

        if ($kind === Attachment::IMAGE) {
            $attachment->thumb_path = $this->makeThumb($path, $meta); // best-effort; null is fine
        }

        $attachment->save();

        return $attachment;
    }

    /**
     * Make this person's listed uploads part of the given content. Called inside the same transaction that creates the
     * content. $items come from the form: [['uuid' => ..., 'caption' => ...], ...] in display order.
     *
     * @param  array<int|string, array{uuid?: string|null, caption?: string|null}>  $items
     */
    public function bind(array $items, Model $attachable, User $owner): void
    {
        if ($items === []) {
            return;
        }

        if (count($items) > (int) config('attachments.max_per_item')) {
            throw ValidationException::withMessages(['attachments' => 'At most '.config('attachments.max_per_item').' attachments per item.']);
        }

        $uuids = array_values(array_unique(array_map(fn ($item) => (string) ($item['uuid'] ?? ''), $items)));
        $found = Attachment::whereIn('uuid', $uuids)
            ->where('user_id', $owner->id)
            ->whereNull('attachable_id')
            ->get()
            ->keyBy('uuid');

        if ($found->count() !== count($uuids)) {
            throw ValidationException::withMessages(['attachments' => 'One of the attachments is no longer available (uploads are kept for a day before a form uses them). Remove it and upload it again.']);
        }

        $pdfIds = [];

        foreach (array_values($items) as $sort => $item) {
            $attachment = $found[(string) $item['uuid']] ?? null;
            if (! $attachment) {
                continue; // a duplicated uuid in the form; the first occurrence won
            }

            $attachment->forceFill([
                'attachable_type' => $attachable->getMorphClass(),
                'attachable_id' => $attachable->getKey(),
                'team_id' => $attachable->team_id ?? $attachment->team_id,
                'caption' => Str::limit(trim((string) ($item['caption'] ?? '')), 497) ?: null,
                'sort_order' => $sort,
                'expires_at' => null,
            ])->save();
            $found->forget((string) $item['uuid']);

            if ($attachment->kind === Attachment::PDF && $attachment->extracted_text === null) {
                $pdfIds[] = $attachment->id;
            }
        }

        // PDF text joins the parent's search index, best-effort and after the response: a slow or broken PDF never
        // delays or fails the submission (there is no queue worker on this host; afterResponse is the pattern).
        foreach ($pdfIds as $id) {
            dispatch(function () use ($id) {
                $attachment = Attachment::find($id);
                if ($attachment) {
                    app(AttachmentService::class)->extractText($attachment);
                }
            })->afterResponse();
        }
    }

    /**
     * Pull the readable text out of a PDF so search and "more like this" can see it. Best-effort: a PDF that cannot be
     * parsed (scanned pages, encryption) simply contributes nothing — OCR would need an external service (R-15 §6).
     */
    public function extractText(Attachment $attachment): void
    {
        if ($attachment->kind !== Attachment::PDF) {
            return;
        }

        try {
            $document = (new Parser)->parseFile(Storage::disk(Attachment::DISK)->path($attachment->path));
            $text = trim(preg_replace('/\s+/u', ' ', $document->getText()) ?? '');

            $attachment->forceFill([
                'extracted_text' => $text === '' ? null : Str::limit($text, 20000, ''),
                'meta' => array_merge($attachment->meta ?? [], ['pages' => count($document->getPages())]),
            ])->save();
        } catch (\Throwable $e) {
            report($e);

            return;
        }

        // The content the text belongs to re-enters the search index with it.
        $parent = $attachment->attachable()->withoutGlobalScopes()->first();
        [$content] = $parent ? $this->resolve($parent) : [null];

        if ($content && method_exists($content, 'searchable')) {
            $content->searchable();
        }
    }

    /**
     * The hidden JSON field a Livewire form carries, turned into bind() items. Garbage in the payload is a 422, never
     * a crash: the field is written by the widget but arrives from the browser.
     *
     * @return list<array{uuid: string, caption: string}>
     */
    public static function parsePayload(?string $json): array
    {
        $json = trim((string) $json);

        if ($json === '' || $json === '[]') {
            return [];
        }

        $rows = json_decode($json, true);

        if (! is_array($rows) || count($rows) > (int) config('attachments.max_per_item')) {
            throw ValidationException::withMessages(['attachments' => 'The attachments could not be read. Remove them and add them again.']);
        }

        $items = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! Str::isUuid($row['uuid'] ?? '')) {
                throw ValidationException::withMessages(['attachments' => 'The attachments could not be read. Remove them and add them again.']);
            }
            $items[] = ['uuid' => (string) $row['uuid'], 'caption' => (string) ($row['caption'] ?? '')];
        }

        return $items;
    }

    /** The validation rules a composer form uses for its attachments[] payload. */
    public static function rules(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:'.(int) config('attachments.max_per_item')],
            'attachments.*.uuid' => ['required', 'uuid'],
            'attachments.*.caption' => ['nullable', 'string', 'max:500'],
        ];
    }

    // ------------------------------------------------------------------------------------------------- viewing

    /**
     * Whether this person may see this file. Unbound: its owner only. Bound: exactly who may see the content it is part
     * of — and when that content is hidden or withdrawn, only its author, the uploader and moderators.
     */
    public function canView(?User $user, Attachment $attachment): bool
    {
        if (! $user) {
            return false;
        }

        if (! $attachment->isBound()) {
            return $attachment->user_id === $user->id;
        }

        $parent = $attachment->attachable()->withoutGlobalScopes()->first();

        if (! $parent) {
            return false;
        }

        [$content, $restricted] = $this->resolve($parent);

        if (! $content) {
            return false;
        }

        if ($restricted || (method_exists($content, 'isHidden') && $content->isHidden())) {
            return $user->id === $attachment->user_id || $user->id === $content->getAttribute('user_id') || $user->isModerator();
        }

        return $this->answers->canSeeContent($content, $user);
    }

    /**
     * The question or solution that decides visibility, plus whether something on the way there (a hidden or withdrawn
     * answer, a step of a hidden solution) restricts the file to the people involved.
     *
     * @return array{0: Model|null, 1: bool}
     */
    private function resolve(Model $parent): array
    {
        $restricted = false;

        if ($parent instanceof Steps) {
            $parent = $parent->solution()->withoutGlobalScopes()->first();
        } elseif ($parent instanceof Comment) {
            $parent = $parent->commentable()->withoutGlobalScopes()->first();
        } elseif ($parent instanceof Answer) {
            $restricted = $parent->isHidden() || $parent->deleted_at !== null || $parent->status !== Answer::PUBLISHED;
            $question = $parent->question()->withoutGlobalScopes()->first();

            return [$question, $restricted];
        }

        if (! $parent instanceof Questions && ! $parent instanceof Solutions) {
            return [null, false];
        }

        return [$parent, $restricted];
    }

    // ------------------------------------------------------------------------------------------------- housekeeping

    /** Delete unbound uploads whose day has passed (their files go with the rows). Returns how many went. */
    public function prune(): int
    {
        $gone = 0;

        Attachment::expired()->orderBy('id')->chunkById(100, function ($batch) use (&$gone) {
            foreach ($batch as $attachment) {
                $attachment->delete();
                $gone++;
            }
        });

        return $gone;
    }

    // ------------------------------------------------------------------------------------------------- internals

    /** @return array{0: string, 1: string} kind and sniffed mime; throws when the type is not allowed or disguised */
    private function sniff(UploadedFile $file): array
    {
        $mime = strtolower((string) $file->getMimeType()); // fileinfo on the actual bytes
        $extension = strtolower($file->getClientOriginalExtension());

        foreach ((array) config('attachments.kinds') as $kind => $rules) {
            $extensions = $rules['mimes'][$mime] ?? null;

            if ($extensions !== null && in_array($extension, $extensions, true)) {
                // Stored and served under the canonical type, whatever libmagic called the container.
                return [$kind, $mime === 'application/mp4' ? 'video/mp4' : $mime];
            }
        }

        throw ValidationException::withMessages([
            'file' => 'That file type is not supported. Use images (JPG, PNG, WebP, GIF), PDF documents, or MP4/WebM video.',
        ]);
    }

    private function guardDailyQuota(User $user, int $incomingBytes): void
    {
        $today = Attachment::where('user_id', $user->id)->where('created_at', '>=', now()->subDay());
        $files = (clone $today)->count();
        $bytes = (int) (clone $today)->sum('size');

        if ($files + 1 > (int) config('attachments.daily.files') || $bytes + $incomingBytes > (int) config('attachments.daily.bytes')) {
            throw ValidationException::withMessages(['file' => 'You have reached the daily upload limit. Try again tomorrow.']);
        }
    }

    /** @return array{width: int, height: int} */
    private function imageMeta(UploadedFile $file): array
    {
        $info = @getimagesize($file->getRealPath());

        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw ValidationException::withMessages(['file' => 'That image could not be read.']);
        }

        if ((int) config('attachments.max_image_pixels') < $info[0] * $info[1]) {
            throw ValidationException::withMessages(['file' => 'That image has too many pixels to handle safely. Export a smaller copy.']);
        }

        return ['width' => $info[0], 'height' => $info[1]];
    }

    /** A small JPEG beside the original, for lists and the lightbox strip. Best-effort: null when GD cannot do it. */
    private function makeThumb(string $path, array $meta): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $max = (int) config('attachments.thumb_max');
        $width = (int) ($meta['width'] ?? 0);
        $height = (int) ($meta['height'] ?? 0);

        if ($width < 1 || $height < 1) {
            return null;
        }

        try {
            $source = @imagecreatefromstring((string) Storage::disk(Attachment::DISK)->get($path));
            if ($source === false) {
                return null;
            }

            $scale = min(1, $max / max($width, $height));
            $w = max(1, (int) round($width * $scale));
            $h = max(1, (int) round($height * $scale));

            $thumb = imagecreatetruecolor($w, $h);
            imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255)); // flatten transparency
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $w, $h, $width, $height);

            $thumbPath = preg_replace('/\.[a-z0-9]+\z/', '', $path).'.thumb.jpg';
            ob_start();
            imagejpeg($thumb, null, 75);
            $bytes = (string) ob_get_clean();
            imagedestroy($thumb);
            imagedestroy($source);

            return Storage::disk(Attachment::DISK)->put($thumbPath, $bytes) ? $thumbPath : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function displayName(UploadedFile $file, string $extension): string
    {
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $base = trim(preg_replace('/[^\pL\pN \-_.()]+/u', ' ', $base) ?? '');
        $base = $base === '' ? 'attachment' : Str::limit($base, 140, '');

        return $base.'.'.$extension;
    }
}

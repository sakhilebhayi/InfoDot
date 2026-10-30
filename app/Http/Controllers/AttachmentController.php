<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\Attachments\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Uploads and downloads for R-15 attachments. The composer widget posts one file at a time to store() and removes a
 * pending upload with destroy(); show() and thumb() are the only way a stored file reaches a browser — after
 * AttachmentService has said this viewer may see the content the file belongs to. Video needs HTTP Range (seeking),
 * so show() answers 206 for range requests.
 */
class AttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $service) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file']]);

        $attachment = $this->service->upload($request->user(), $request->file('file'));

        return response()->json([
            'uuid' => $attachment->uuid,
            'kind' => $attachment->kind,
            'name' => $attachment->name,
            'size' => $attachment->size,
            'url' => route('attachments.show', $attachment),
            'thumb' => $attachment->thumb_path ? route('attachments.thumb', $attachment) : null,
        ], 201);
    }

    /** Remove a pending upload (owner only, not yet part of any content — bound files live and die with their content). */
    public function destroy(Request $request, Attachment $attachment): Response
    {
        abort_unless($attachment->user_id === $request->user()->id && ! $attachment->isBound(), 404);

        $attachment->delete();

        return response()->noContent();
    }

    public function show(Request $request, Attachment $attachment): Response
    {
        abort_unless($this->service->canView($request->user(), $attachment), 404);

        // Images and PDFs preview inline; everything may also be fetched as a download.
        $inline = ! $request->boolean('download') && in_array($attachment->kind, [Attachment::IMAGE, Attachment::PDF, Attachment::VIDEO], true);

        return $this->stream($request, $attachment->path, $attachment->mime, $attachment->name, $inline);
    }

    public function thumb(Request $request, Attachment $attachment): Response
    {
        abort_unless($this->service->canView($request->user(), $attachment), 404);
        abort_if($attachment->thumb_path === null, 404);

        return $this->stream($request, $attachment->thumb_path, 'image/jpeg', 'thumb.jpg', true);
    }

    /**
     * Stream a file from the private disk, honouring a single-range Range header (native video players seek with it).
     * Every response carries nosniff plus a sandboxing CSP so nothing a person uploaded can run as part of InfoDot.
     */
    private function stream(Request $request, string $path, string $mime, string $name, bool $inline): Response
    {
        $disk = Storage::disk(Attachment::DISK);
        abort_unless($disk->exists($path), 404);

        $size = $disk->size($path);
        $start = 0;
        $end = $size - 1;
        $status = 200;

        $range = (string) $request->header('Range', '');
        if ($size > 0 && preg_match('/\Abytes=(\d*)-(\d*)\z/', trim($range), $m) && ($m[1] !== '' || $m[2] !== '')) {
            if ($m[1] === '') { // suffix form: the last N bytes
                $start = max(0, $size - (int) $m[2]);
            } else {
                $start = (int) $m[1];
                $end = $m[2] === '' ? $end : min($end, (int) $m[2]);
            }

            if ($start > $end || $start >= $size) {
                return response('', 416, ['Content-Range' => 'bytes */'.$size]);
            }

            $status = 206;
        }

        $headers = [
            'Content-Type' => $mime,
            'Content-Length' => (string) ($end - $start + 1),
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.addcslashes($name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ];

        // Anything that could act as a document is locked down; the browsers' own PDF viewers refuse to render
        // under a sandbox directive, so inline PDFs rely on the viewer's sandbox instead.
        if ($mime !== 'application/pdf' || ! $inline) {
            $headers['Content-Security-Policy'] = "sandbox; default-src 'none'";
        }

        if ($status === 206) {
            $headers['Content-Range'] = 'bytes '.$start.'-'.$end.'/'.$size;
        }

        return new StreamedResponse(function () use ($disk, $path, $start, $end) {
            $stream = $disk->readStream($path);
            if ($stream === null) {
                return;
            }

            fseek($stream, $start);
            $left = $end - $start + 1;

            while ($left > 0 && ! feof($stream)) {
                $chunk = fread($stream, (int) min(65536, $left));
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                $left -= strlen($chunk);
            }

            fclose($stream);
        }, $status, $headers);
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\UploadLimit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the two upload endpoints (POST /attachments and Livewire's temporary upload), answers a file that PHP marked as failed
 * with the reason: too large (and the limit), cut off on the way, or a fault on the server. Without it the validator says
 * only "The file failed to upload." for all three. The answer is an ordinary validation error on the same field, so both
 * uploaders show it where they already show errors. A server fault is logged as an error, by code, without the file name.
 */
class ExplainFailedUpload
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (Arr::dot($request->allFiles()) as $field => $file) {
            if (! $file instanceof UploadedFile || $file->isValid()) {
                continue;
            }

            if (UploadLimit::isServerFault($file->getError())) {
                Log::error('An upload failed on the server before the application saw it.', ['php_upload_error' => $file->getError(), 'field' => (string) $field]);
            }

            throw ValidationException::withMessages([(string) $field => UploadLimit::messageFor($file->getError())]);
        }

        return $next($request);
    }
}

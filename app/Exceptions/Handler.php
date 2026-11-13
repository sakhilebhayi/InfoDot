<?php

namespace App\Exceptions;

use App\Support\UploadLimit;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'account_number', // a host's bank account number is never flashed back into a form or logged
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        // Sentry was installed but nothing sent it exceptions. With no DSN configured this does nothing.
        $this->reportable(function (Throwable $e) {
            Integration::captureUnhandledException($e);
        });

        // A request larger than PHP accepts arrives empty and used to be answered with a bare 413. A JSON caller gets the reason and
        // the limit; a page request falls through to resources/views/errors/413.blade.php, which says the same.
        $this->renderable(function (PostTooLargeException $e, Request $request) {
            $message = UploadLimit::tooLargeMessage();

            // Livewire's uploader reads the response body only for a 422 and shows errors under "files.0" on the file's own field;
            // any other status becomes "failed to upload". The route is not matched yet (this is thrown before routing), so compare paths.
            $livewireUpload = Route::getRoutes()->getByName('livewire.upload-file')?->uri();

            if ($livewireUpload !== null && $request->is($livewireUpload)) {
                return response()->json(['message' => $message, 'errors' => ['files.0' => [$message]]], 422);
            }

            return $request->expectsJson() ? response()->json(['message' => $message, 'errors' => ['file' => [$message]]], 413) : null;
        });
    }

    /**
     * What every logged exception carries besides the exception itself: who was signed in (the id only), which route and which
     * method. The request id is already on every log line (AssignRequestId). The route is its name, or its pattern
     * ("question/{qid}") when it has none: never the address that was opened, the query string or anything that was sent.
     *
     * @return array<string, mixed>
     */
    protected function context()
    {
        $context = parent::context();

        try {
            if (! app()->runningInConsole() || app()->runningUnitTests()) {
                $request = request();
                $route = $request->route();

                $context += array_filter([
                    'route' => $route instanceof LaravelRoute ? ($route->getName() ?? $route->uri()) : null,
                    'method' => $request->getMethod(),
                ]);
            }
        } catch (Throwable) {
            // Context is a help, never a reason to lose the log line.
        }

        return $context;
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The web manifest, served by the application so it carries its registered content type (application/manifest+json).
 * As a physical file in the web root it was sent with whatever type the web server gives an unknown extension, and
 * the web root's own configuration is not managed from this repository. The content lives in resources/, outside the
 * web root, so no static copy can stand in front of this route; the deploy removes the copy older releases left there.
 */
class WebManifestController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $path = resource_path('site.webmanifest');
        abort_unless(is_file($path), 404);

        $response = response((string) file_get_contents($path), 200, ['Content-Type' => 'application/manifest+json; charset=UTF-8']);
        $response->setPublic()->setMaxAge(604800)->setEtag(md5_file($path) ?: '');   // a week; the address is not versioned
        $response->isNotModified($request);

        return $response;
    }
}

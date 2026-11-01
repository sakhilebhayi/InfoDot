<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * sitemap.xml (R-17 §13): the public, guest-readable pages only. Nothing behind sign-in belongs here — private
 * content is protected by authorisation, never by omission from a sitemap. Served from code so the list cannot
 * drift from the routes that actually exist.
 */
class SitemapController extends Controller
{
    /** route name => how often the page meaningfully changes */
    private const PAGES = [
        'welcome' => 'weekly',
        'about' => 'monthly',
        'features' => 'monthly',
        'faqs' => 'monthly',
        'contact' => 'yearly',
        'complains' => 'yearly',
        'terms' => 'monthly',
        'policy' => 'monthly',
        'cookies' => 'yearly',
        'security' => 'yearly',
        'businesses.index' => 'daily',
    ];

    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', 3600, function (): string {
            $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

            foreach (self::PAGES as $name => $frequency) {
                $lines[] = '  <url><loc>'.e(route($name)).'</loc><changefreq>'.$frequency.'</changefreq></url>';
            }

            // Business pages listed for everyone (R-33): the only businesses a visitor or crawler can open.
            Team::query()->listedFor(null)->with('profile:id,team_id,updated_at')->select(['id', 'uuid', 'updated_at'])
                ->orderBy('id')->limit(5000)->get()
                ->each(function (Team $team) use (&$lines) {
                    $modified = max($team->updated_at?->getTimestamp() ?? 0, $team->profile?->updated_at?->getTimestamp() ?? 0);
                    $lines[] = '  <url><loc>'.e(route('business.show', $team)).'</loc>'.($modified ? '<lastmod>'.date('Y-m-d', $modified).'</lastmod>' : '').'<changefreq>weekly</changefreq></url>';
                });

            $lines[] = '</urlset>';

            return implode("\n", $lines);
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}

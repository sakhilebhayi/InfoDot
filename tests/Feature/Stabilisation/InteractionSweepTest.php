<?php

namespace Tests\Feature\Stabilisation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Stabilisation\Support\BuildsTheYard;
use Tests\Feature\Stabilisation\Support\PageInspector;
use Tests\TestCase;

/**
 * R-49. Nothing a person can click may be dead. For each kind of viewer (a visitor, a signed-in member, a business
 * owner, a moderator) the sweep starts at the entry pages, follows every in-app link it finds, and on every page it
 * reaches checks each link, button, form, Livewire action, label, in-page anchor and picture (the rules are in
 * PageInspector). Every picture and file a page loads is requested as that viewer.
 *
 * It exists because the earlier link test covered a fixed list of pages for two viewers and looked at links and
 * form addresses only; a dead button, a broken picture or a page reached two clicks in went unseen.
 *
 * Set R49_SWEEP_REPORT to a file path to have the totals written there as JSON (the audit documents quote them).
 */
class InteractionSweepTest extends TestCase
{
    use BuildsTheYard;
    use RefreshDatabase;

    /**
     * Bounds, so a page that links to itself with ever-new query strings cannot run the walk for ever. Today the
     * largest walk opens about 300 addresses and nothing is cut off; the report says how many were if that changes.
     */
    private const MAX_PAGES = 400;

    private const MAX_VARIANTS = 400;

    private const PUBLIC_ENTRY = ['/', '/about', '/features', '/faqs', '/contact', '/complains', '/policy', '/terms', '/cookies', '/security', '/terms-of-service', '/privacy-policy', '/solution-results', '/solution-results?search=borehole', '/businesses', '/businesses/featured', '/login', '/register', '/forgot-password'];

    private const SIGNED_IN_ENTRY = ['/home', '/questions', '/questions/ask', '/solutions', '/solution/create', '/topics', '/library', '/platforms', '/people', '/analytics', '/points', '/notifications', '/inbox', '/collections', '/seminars', '/seminars/new', '/user/profile/edit', '/teams/create', '/user/confirm-password', '/go/files', '/go/docs', '/sub-services/dot-files', '/sub-services/dot-docs', '/sub-services/dot-sheets', '/sub-services/dot-press', '/sub-services/dot-forms', '/sub-services/dot-engage'];

    private const MODERATOR_ENTRY = ['/moderation', '/moderation/friction', '/moderation/topics', '/moderation/library', '/moderation/trust', '/moderation/identity', '/moderation/verifications', '/moderation/seminars'];

    /**
     * Addresses that hand the person to another Dot platform or end the session. They are requested and must
     * answer, but the page they lead to is another site's.
     */
    private const LEAVES_THE_SITE = ['/logout', '/oauth/'];

    /**
     * The only addresses a visitor may be shown that ask them to sign in first: the calls to action on the public
     * pages (ask, write, browse, a topic, write to or report a business, a seminar's short link). Anything else a
     * visitor is shown must open for a visitor, so a new sign-in wall is a decision, not an accident.
     */
    private const VISITOR_SIGN_IN_WALLS = ['/questions/ask', '/solutions', '/question/view/{id}', '/solution/view/{id}', '/solution/create', '/topics', '/topics/*', '/business/{uuid}/message', '/business/{uuid}/report', '/j/*'];

    /**
     * public/favicon.ico has been a zero-byte file since the first commit, so the app and public pages have no tab
     * icon. Which mark becomes the icon is the owner's choice (docs/programme/r49/IMAGE_AUDIT.md, decision 1); until
     * then this one empty file is named here rather than passed over in silence. Any other empty file fails.
     */
    private const EMPTY_ON_PURPOSE_UNTIL_DECIDED = ['/favicon.ico'];

    /** @var array<string, mixed> */
    private static array $report = [];

    /** @var array<string, array<string, true>> every distinct control, by type, over all walks */
    private static array $everyControl = [];

    /** @var array<string, string> every distinct file a page loaded, with how it was checked */
    private static array $everyResource = [];

    /** @var array<string, true> every Blade view some walk rendered */
    private static array $rendered = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->nameBuiltFilesBySource();
        $this->withoutMiddleware(ThrottleRequests::class); // one walk opens more pages than a person does in a minute
        $this->buildTheYard();
        View::composer('*', function ($view): void {
            self::$rendered[$view->name()] = true;
        });
    }

    public static function tearDownAfterClass(): void
    {
        $path = getenv('R49_SWEEP_REPORT');
        if ($path && self::$report !== []) {
            self::$report['views_rendered'] = array_keys(self::$rendered);
            self::$report['distinct_controls_all_walks'] = array_map('count', self::$everyControl);
            self::$report['distinct_resources_all_walks'] = array_count_values(self::$everyResource);
            file_put_contents($path, json_encode(self::$report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        parent::tearDownAfterClass();
    }

    public function test_a_visitor_finds_no_dead_control_and_no_broken_picture(): void
    {
        $this->sweep('visitor', null, array_merge(self::PUBLIC_ENTRY, [
            $this->businessUrl(), '/j/'.$this->seminar->public_code,
        ]));
    }

    public function test_a_signed_in_member_finds_no_dead_control_and_no_broken_picture(): void
    {
        $this->sweep('member', $this->member, array_merge(self::PUBLIC_ENTRY, self::SIGNED_IN_ENTRY, [
            $this->businessUrl(), $this->businessUrl('/message'), $this->businessUrl('/report'),
            '/inbox/'.$this->enquiry->uuid, '/question/view/'.$this->question->id, '/solution/view/'.$this->solution->id,
            '/seminars/'.$this->seminar->id, '/user/profile/'.$this->member->id, '/teams/'.$this->member->personalTeam()->id,
        ]));
    }

    public function test_a_business_owner_finds_no_dead_control_and_no_broken_picture(): void
    {
        $this->sweep('business owner', $this->owner, array_merge(self::PUBLIC_ENTRY, self::SIGNED_IN_ENTRY, [
            $this->businessUrl(), $this->businessUrl('/edit'), $this->businessUrl('/inbox'), $this->businessUrl('/inbox/'.$this->enquiry->uuid),
            $this->businessUrl('/insights'), $this->businessUrl('/standing'), $this->businessUrl('/verification'),
            '/question/view/'.$this->question->id, '/solution/view/'.$this->solution->id,
            '/seminars/'.$this->seminar->id, '/seminars/'.$this->seminar->id.'/edit', '/teams/'.$this->business->id,
            '/user/profile/'.$this->colleague->id,
        ]));
    }

    public function test_a_moderator_finds_no_dead_control_and_no_broken_picture(): void
    {
        $this->sweep('moderator', $this->moderator, array_merge(self::PUBLIC_ENTRY, self::SIGNED_IN_ENTRY, self::MODERATOR_ENTRY, [
            $this->businessUrl(), '/moderation/verifications/'.$this->verification->id, '/moderation/trust/investigations/'.$this->investigation->id,
            '/moderation/trust/appeals/'.$this->appeal->id, '/moderation/trust/reports/'.$this->openReport->id, '/moderation/identity/'.$this->conflict->id,
            '/business/'.$this->verifiedBusiness->uuid,
            '/question/view/'.$this->question->id, '/solution/view/'.$this->solution->id, '/seminars/'.$this->seminar->id,
        ]));
    }

    public function test_with_every_optional_feature_on_a_host_finds_no_dead_control(): void
    {
        $this->switchEverythingOn();
        $this->sweep('host, everything on', $this->owner, [
            '/seminars', '/seminars/new', '/seminars/payouts', '/seminars/invoices', '/seminars/'.$this->seminar->id, '/seminars/'.$this->seminar->id.'/edit',
            '/seminars/'.$this->videoSeminar->id, '/seminars/'.$this->videoSeminar->id.'/room',
            '/seminars/'.$this->smallRoomSeminar->id, '/seminars/'.$this->smallRoomSeminar->id.'/small-room', '/points', '/progress',
        ]);
    }

    public function test_with_every_optional_feature_on_an_attendee_and_a_moderator_find_no_dead_control(): void
    {
        $this->switchEverythingOn();
        $this->sweep('attendee, everything on', $this->member, [
            '/seminars', '/seminars/'.$this->videoSeminar->id, '/seminars/'.$this->videoSeminar->id.'/room',
            '/seminars/'.$this->smallRoomSeminar->id, '/seminars/'.$this->smallRoomSeminar->id.'/small-room', '/seminars/payouts', '/seminars/invoices', '/progress', '/home',
        ]);
        $this->sweep('moderator, everything on', $this->moderator, ['/moderation', '/moderation/seminars', '/moderation/seminar-payments', '/moderation/reputation', '/moderation/rings', '/seminars/'.$this->videoSeminar->id]);
    }

    /**
     * Pages outside the walk: the ones a person meets on the way in (verify the address, choose a new password,
     * confirm the password) and the error pages, each of which still carries a way back.
     */
    public function test_the_way_in_and_the_error_pages_have_no_dead_control(): void
    {
        $unverified = User::factory()->withPersonalTeam()->unverified()->create();
        $token = Password::broker()->createToken($this->member);
        $cases = [
            ['unverified person', $unverified, '/email/verify', 200],
            ['visitor', null, '/reset-password/'.$token.'?email='.urlencode($this->member->email), 200],
            ['member', $this->member, '/user/confirm-password', 200],
            ['visitor', null, '/no-such-page', 404],
            ['member', $this->member, '/no-such-page', 404],
            ['member', $this->member, '/moderation', 403],
            ['member', $this->member, '/business/'.$this->business->uuid.'/edit', 403],
            ['member', $this->member, '/question/view/999999', 404],
            ['member', $this->member, '/oauth/end-session', 200],
            ['member', $this->member, '/go/no-such-platform', 503],
        ];
        $inspector = new PageInspector;
        $lines = [];
        $pages = [];
        foreach ($cases as [$who, $as, $path, $expected]) {
            $this->signOut();
            $response = $as ? $this->actingAs($as, 'web')->get($path) : $this->get($path);
            $this->assertSame($expected, $response->getStatusCode(), "{$who}: {$path}");
            $found = $inspector->inspect((string) $response->getContent(), $path);
            $pages[] = "{$who} {$inspector->shape($path)} ({$expected})";
            foreach ($found['problems'] as $problem) {
                $lines[] = "  [{$who}] {$path}: {$problem}";
            }
            foreach ($found['links'] as $link) {
                $this->signOut();
                [$status, , $final] = $this->open($link, $as);
                if ($status >= 400 || ($as !== null && $final === '/login')) {
                    $lines[] = "  [{$who}] {$path}: the link {$link} answered {$status}".($final === '/login' ? ' and led to sign in' : '');
                }
            }
        }
        self::$report['way in and error pages'] = ['pages' => $pages, 'problems' => $lines];

        $this->assertTrue($lines === [], count($lines)." problem(s):\n".implode("\n", $lines)."\n");
    }

    public function test_a_visitor_is_not_offered_what_only_a_signed_in_person_can_use(): void
    {
        // The search panel on the public pages: its People, Files and Documents shortcuts belong to a person's company.
        foreach (['/about', '/faqs', '/solution-results', '/businesses'] as $page) {
            $html = (string) $this->get($page)->assertOk()->getContent();
            $this->assertFalse(str_contains($html, 'openPlatform('), "{$page} offers a visitor a platform button that does nothing for them");
            $this->assertSame(0, preg_match('#href="[^"]*/people"#', $html), "{$page} offers a visitor the People page");
        }
        $this->assertTrue(str_contains((string) $this->actingAs($this->member, 'web')->get('/home')->getContent(), 'openPlatform('), 'a signed-in person keeps the shortcuts');
    }

    /** @param list<string> $entry */
    private function sweep(string $who, ?User $as, array $entry): void
    {
        $this->signOut();
        $inspector = new PageInspector;
        $problems = [];   // message => first page it was seen on
        $queue = array_values(array_unique($entry));
        $seen = array_fill_keys($queue, 'entry');
        $variants = [];
        $pages = [];
        $resources = [];  // url => kind
        $externalHosts = [];
        $signInWalls = [];
        $counts = [];
        $distinct = [];
        $skipped = 0;

        $note = function (string $page, string $message) use (&$problems): void {
            $problems[$message] ??= ['page' => $page, 'pages' => 0];
            $problems[$message]['pages']++;
        };

        while ($queue !== []) {
            $page = array_shift($queue);
            if (count($pages) >= self::MAX_PAGES) {
                $skipped++;

                continue;
            }
            [$status, $response, $final] = $this->open($page, $as);
            $from = $seen[$page];

            if ($status >= 500) {
                $note($page, "{$inspector->shape($page)} answered {$status} (linked from {$from})");

                continue;
            }
            if ($this->startsWithAny($page, self::LEAVES_THE_SITE)) {
                if ($status >= 400) {
                    $note($page, "{$inspector->shape($page)} answered {$status} (linked from {$from})");
                }
                $pages[$page] = 'hand-off';

                continue;
            }
            if ($final === '/login' && $page !== '/login') {
                if ($as === null) {
                    $signInWalls[$inspector->shape($page)] ??= $from;
                    if (! Str::is(self::VISITOR_SIGN_IN_WALLS, $inspector->shape($page))) {
                        $note($page, "{$inspector->shape($page)} is shown to a visitor but only opens after signing in (linked from {$from})");
                    }
                } else {
                    $note($page, "{$inspector->shape($page)} sent a signed-in person to sign in (linked from {$from})");
                }

                continue;
            }
            if ($status >= 400) {
                $note($page, "{$inspector->shape($page)} answered {$status} (linked from {$from})");

                continue;
            }
            if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                $pages[$page] = 'file';

                continue;
            }

            $pages[$page] = 'page';
            $found = $inspector->inspect((string) $response->getContent(), $final);
            foreach ($found['problems'] as $problem) {
                $note($page, $problem);
            }
            foreach ($found['counts'] as $type => $n) {
                $counts[$type] = ($counts[$type] ?? 0) + $n;
            }
            foreach ($found['signatures'] as $type => $signatures) {
                foreach ($signatures as $signature) {
                    $distinct[$type][$signature] = true;
                    self::$everyControl[$type][$signature] = true;
                }
            }
            foreach ($found['resources'] as $resource) {
                $this->checkResource($inspector, $resource, $response, $as, $page, $note, $resources, $externalHosts);
            }
            foreach ($found['links'] as $link) {
                if (isset($seen[$link])) {
                    continue;
                }
                $shape = $inspector->shape($link);
                $variants[$shape] = ($variants[$shape] ?? 0) + 1;
                if ($variants[$shape] > self::MAX_VARIANTS && str_contains($link, '?')) {
                    $skipped++;

                    continue;
                }
                $seen[$link] = $page;
                $queue[] = $link;
            }
        }

        foreach ($resources as $url => $how) {
            self::$everyResource[$inspector->localPath($url) !== null ? $inspector->shape($url).'|'.$how : $url] = $how;
        }
        self::$report[$who] = [
            'pages_opened' => count($pages),
            'pages' => array_count_values($pages),
            'page_shapes' => array_values(array_unique(array_map(fn (string $p): string => $inspector->shape($p), array_keys(array_filter($pages, fn (string $k): bool => $k === 'page'))))),
            'all_shapes' => array_values(array_unique(array_map(fn (string $p): string => $inspector->shape($p), array_keys($pages)))),
            'sign_in_walls' => $signInWalls,
            'skipped_variants' => $skipped,
            'controls' => $counts,
            'distinct_controls' => array_map('count', $distinct),
            'resources' => array_count_values($resources),
            'external_hosts' => $externalHosts,
            'resource_urls' => array_map(fn (string $url): string => $inspector->shape($url).(str_contains($url, '://') ? ' '.$url : ''), array_keys($resources)),
            'problems' => $problems,
        ];

        $this->assertGreaterThan(count($entry) / 2, count($pages), "{$who}: the walk opened too few pages to mean anything");
        $lines = [];
        foreach ($problems as $message => $where) {
            $lines[] = "  [{$who}] {$where['page']}".($where['pages'] > 1 ? " (and {$where['pages']} pages in all)" : '').": {$message}";
        }
        $this->assertTrue($lines === [], count($lines)." problem(s) for the {$who}:\n".implode("\n", $lines)."\n");
    }

    /** @return array{0: int, 1: TestResponse, 2: string} status, last response, final path */
    private function open(string $path, ?User $as): array
    {
        $final = $path;
        for ($hop = 0; $hop < 6; $hop++) {
            $response = $as ? $this->actingAs($as, 'web')->get($final) : $this->get($final);
            $status = $response->getStatusCode();
            $location = $response->headers->get('Location');
            if ($status < 300 || $status >= 400 || ! $location) {
                break;
            }
            $next = (new PageInspector)->localPath($location);
            if ($next === null) {
                return [$status, $response, $final]; // handed to another site
            }
            $final = $next;
        }

        return [$status, $response, parse_url($final, PHP_URL_PATH) ?: '/'];
    }

    /**
     * @param  array{kind: string, url: string}  $resource
     * @param  array<string, string>  $resources
     * @param  array<string, list<string>>  $externalHosts
     */
    private function checkResource(PageInspector $inspector, array $resource, TestResponse $page, ?User $as, string $pagePath, callable $note, array &$resources, array &$externalHosts): void
    {
        ['kind' => $kind, 'url' => $url] = $resource;
        $local = $inspector->localPath($url);

        if ($local === null) {
            $host = (string) parse_url(str_starts_with($url, '//') ? 'https:'.$url : $url, PHP_URL_HOST);
            $directive = match ($kind) {
                'script' => 'script-src', 'style' => 'style-src', 'font' => 'font-src', 'media' => 'media-src', 'frame' => 'frame-src', default => 'img-src',
            };
            if (! in_array($directive, $externalHosts[$host] ?? [], true)) {
                $externalHosts[$host][] = $directive;
            }
            $resources[$url] = 'external '.$kind;
            if (! $this->cspAllows($page, $directive, $host)) {
                $note($pagePath, "loads a {$kind} from {$host}, which the Content-Security-Policy {$directive} does not allow: {$url}");
            }

            return;
        }
        if (isset($resources[$local])) {
            return;
        }

        $path = parse_url($local, PHP_URL_PATH) ?: '/';
        if (str_starts_with($path, '/build/source/')) {
            $resources[$local] = 'built '.$kind;
            if ($why = $this->builtFromSource($path)) {
                $note($pagePath, "loads a built {$kind} that will not ship: {$why}");
            }

            return;
        }
        if (is_file(public_path(ltrim($path, '/')))) {
            $resources[$local] = 'static '.$kind;
            if (filesize(public_path(ltrim($path, '/'))) === 0 && ! in_array($path, self::EMPTY_ON_PURPOSE_UNTIL_DECIDED, true)) {
                $note($pagePath, "loads the {$kind} {$path}, which is an empty file");
            }

            return;
        }

        $resources[$local] = 'served '.$kind;
        $response = $as ? $this->actingAs($as, 'web')->get($local) : $this->get($local);
        if ($response->getStatusCode() !== 200) {
            $note($pagePath, "shows the {$kind} {$inspector->shape($path)}, which answers {$response->getStatusCode()} for this viewer");

            return;
        }
        $type = (string) $response->headers->get('Content-Type');
        $wanted = match ($kind) {
            'script' => ['javascript'], 'style' => ['css'], 'media' => ['video/', 'audio/'], 'frame' => ['text/html', 'application/pdf'], 'font' => ['font', 'octet-stream'],
            default => ['image/'],
        };
        if (! $this->containsAny($type, $wanted)) {
            $note($pagePath, "shows the {$kind} {$inspector->shape($path)}, which is served as \"{$type}\"");
        }
    }

    private function cspAllows(TestResponse $page, string $directive, string $host): bool
    {
        $policy = (string) ($page->headers->get('Content-Security-Policy') ?? $page->headers->get('Content-Security-Policy-Report-Only'));
        $sources = [];
        foreach (explode(';', $policy) as $part) {
            $words = preg_split('/\s+/', trim($part)) ?: [];
            if (array_shift($words) === $directive) {
                $sources = $words;
            }
        }

        return in_array('https://'.$host, $sources, true) || in_array('https:', $sources, true) || in_array('*', $sources, true);
    }

    /** @param list<string> $prefixes */
    private function startsWithAny(string $value, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $needles */
    private function containsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }
}

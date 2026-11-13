<?php

namespace Tests\Feature\Stabilisation;

use App\Models\Attachment;
use App\Models\Questions;
use App\Models\Team;
use App\Models\User;
use App\Services\Attachments\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;
use Tests\Feature\Stabilisation\Support\BuildsTheYard;
use Tests\TestCase;

/**
 * R-49. A picture that cannot load must never reach a page. Three things can go wrong and each has its guard here:
 * the file behind a database row is gone (the page must show its fallback and the file's address must answer 404,
 * not 500); a private picture is asked for by someone who may not see what it belongs to; a view names a file that
 * is not where the live site serves files from.
 *
 * It exists beside the interaction sweep because the sweep only sees a healthy world; these tests break the world
 * on purpose.
 */
class ImageIntegrityTest extends TestCase
{
    use BuildsTheYard;
    use RefreshDatabase;

    /**
     * The files under public/ that views name directly. Only public/build and the files listed in the deploy
     * workflow ("static files the docroot serves") are copied to the live web root; these few predate that rule and
     * are served from copies already on the host. The list is closed: a NEW picture goes in resources/img and is
     * named with Vite::asset, or it will be missing on the live site.
     */
    private const LEGACY_PUBLIC_FILES = ['favicon.ico', 'img/icons/icon.png', 'img/logo1.png', 'img/logo_white.png'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->nameBuiltFilesBySource();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->buildTheYard();
    }

    private function as(?User $user): static
    {
        $this->signOut();

        return $user ? $this->actingAs($user, 'web') : $this;
    }

    /** Whether an element of this class holds just this letter (Livewire's block comments aside). */
    private function showsInitial(string $html, string $class, string $letter): bool
    {
        return preg_match('/class="'.preg_quote($class, '/').'[^"]*"[^>]*>(?:\s|<!--.*?-->)*'.preg_quote($letter, '/').'(?:\s|<!--.*?-->)*</s', $html) === 1;
    }

    private function forget(Attachment $attachment): void
    {
        Storage::disk(Attachment::DISK)->delete(array_filter([$attachment->path, $attachment->thumb_path]));
    }

    // ---- a row whose file is gone ---------------------------------------------------------------------------------

    public function test_a_profile_picture_whose_file_is_gone_falls_back_to_the_initial_everywhere(): void
    {
        $path = (string) $this->member->profile_photo_path;
        $url = '/storage/'.$path;
        $this->as($this->member)->get('/home')->assertOk()->assertSee($url, false);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        Storage::disk('public')->delete($path);

        $this->as(null)->get($url)->assertNotFound();
        foreach (['/home', '/user/profile/'.$this->member->id, '/user/profile/edit', '/teams/'.$this->member->personalTeam()->id, '/analytics', '/teams/create'] as $page) {
            $html = $this->as($this->member->fresh())->get($page)->assertOk()->getContent();
            $this->assertFalse(str_contains($html, 'profile-photos/'), "{$page} still shows the picture whose file is gone");
            $this->assertTrue($this->showsInitial($html, 'ix-avatar', 'M'), "{$page} does not show the initial instead");
        }
    }

    public function test_a_person_without_a_picture_is_an_initial_and_never_an_empty_image(): void
    {
        foreach (['/home', '/user/profile/'.$this->owner->id, '/teams/'.$this->business->id, '/analytics', '/question/view/'.$this->question->id] as $page) {
            $html = $this->as($this->owner)->get($page)->assertOk()->getContent();
            $this->assertSame(0, preg_match('/<img[^>]*\ssrc=""/', $html), "{$page} has an image with an empty src");
            $this->assertFalse(str_contains($html, 'gravatar.com'), "{$page} asks another site for a picture of a person");
        }
        $this->assertSame('É', User::factory()->make(['name' => 'élodie'])->initial(), 'the initial is a whole character, not half of one');
        $this->assertSame('?', User::factory()->make(['name' => ' '])->initial());
    }

    public function test_business_pictures_whose_files_are_gone_fall_back_and_their_addresses_answer_404(): void
    {
        $pictures = $this->businessPictures();
        $this->assertEqualsCanonicalizing(['cover', 'gallery', 'logo', 'offering', 'project'], array_map(fn (Attachment $a) => $a->role, $pictures));
        foreach ($pictures as $picture) {
            $this->as(null)->get('/attachments/'.$picture->uuid)->assertOk();
            $this->forget($picture);
        }

        foreach ($pictures as $picture) {
            $this->as(null)->get('/attachments/'.$picture->uuid)->assertNotFound();
            $this->as($this->owner)->get('/attachments/'.$picture->uuid.'/thumb')->assertNotFound();
        }

        $pages = [[null, $this->businessUrl()], [null, '/businesses'], [$this->member, $this->businessUrl()], [$this->member, '/inbox'], [$this->owner, $this->businessUrl('/edit')]];
        foreach ($pages as [$viewer, $page]) {
            $html = $this->as($viewer)->get($page)->assertOk()->getContent();
            $this->assertSame(0, preg_match('#<img[^>]+src="[^"]*/attachments/#', $html), "{$page} still shows a picture whose file is gone");
            $this->assertTrue(str_contains($html, 'Acme Boreholes'), "{$page} no longer names the business");
        }
        // The logo slot shows the business's initial, the same as a business that never uploaded one.
        $this->assertTrue($this->showsInitial((string) $this->as(null)->get($this->businessUrl())->getContent(), 'ix-biz__logo', 'A'), 'the business page does not show the initial');
        $this->assertTrue($this->showsInitial((string) $this->as(null)->get('/businesses')->getContent(), 'ix-biz__logo', 'A'), 'the directory card does not show the initial');
    }

    public function test_a_question_or_solution_picture_whose_file_is_gone_is_said_not_shown(): void
    {
        $onQuestion = $this->question->attachments()->firstOrFail();
        $onSolution = $this->solution->attachments()->firstOrFail();
        $this->forget($onQuestion);
        $this->forget($onSolution);

        $this->as($this->member)->get('/attachments/'.$onQuestion->uuid)->assertNotFound();
        $this->as($this->member)->get('/attachments/'.$onQuestion->uuid.'/thumb')->assertNotFound();

        $question = $this->as($this->member)->get('/question/view/'.$this->question->id)->assertOk()->assertSee('One attached file could not be loaded.')->getContent();
        $this->assertFalse(str_contains($question, '/attachments/'.$onQuestion->uuid), 'the question still names the lost file');

        $solution = $this->as($this->member)->get('/solution/view/'.$this->solution->id)->assertOk()->assertSee('One attached file could not be loaded.')->getContent();
        $this->assertFalse(str_contains($solution, '/attachments/'.$onSolution->uuid), 'the solution still names the lost file');
        $this->assertSame(1, preg_match('#<img[^>]+src="[^"]*/attachments/#', $solution), "the step's own picture is untouched and should still be shown");
    }

    public function test_a_brochure_whose_file_is_gone_is_not_offered_for_download(): void
    {
        $brochure = Attachment::where('role', 'brochure')->firstOrFail();
        $this->as(null)->get($this->businessUrl())->assertOk()->assertSee('/attachments/'.$brochure->uuid, false);

        $this->forget($brochure);

        $this->as(null)->get($this->businessUrl())->assertOk()->assertDontSee('/attachments/'.$brochure->uuid, false);
        $this->as(null)->get('/attachments/'.$brochure->uuid)->assertNotFound();
    }

    // ---- private pictures -----------------------------------------------------------------------------------------

    public function test_pictures_are_served_only_to_people_who_may_see_what_they_belong_to(): void
    {
        $logo = Attachment::where('role', 'logo')->firstOrFail();
        $evidence = Attachment::where('attachable_type', 'business_verification')->firstOrFail();

        // A company-only question with a picture: its own company sees it, nobody else does.
        $private = Questions::withoutSyncingToSearch(fn () => Questions::create(['user_id' => $this->owner->id, 'team_id' => $this->business->id, 'question' => 'Which rig goes to Hazyview?', 'description' => 'For the team only.', 'status' => 0]));
        $picture = app(AttachmentService::class)->upload($this->owner, $this->picture('rota.jpg'));
        app(AttachmentService::class)->bind([['uuid' => $picture->uuid]], $private, $this->owner);

        $this->as($this->owner)->get('/attachments/'.$picture->uuid)->assertOk();
        $this->as($this->colleague)->get('/attachments/'.$picture->uuid)->assertOk();
        $this->as($this->member)->get('/attachments/'.$picture->uuid)->assertNotFound();
        $this->as($this->member)->get('/attachments/'.$picture->uuid.'/thumb')->assertNotFound();
        $this->as(null)->get('/attachments/'.$picture->uuid)->assertRedirect('/login');

        // Verification evidence: the business's decision-makers and reviewers only.
        $this->as($this->owner)->get('/attachments/'.$evidence->uuid)->assertOk();
        $this->as($this->moderator)->get('/attachments/'.$evidence->uuid)->assertOk();
        $this->as($this->member)->get('/attachments/'.$evidence->uuid)->assertNotFound();
        $this->as(null)->get('/attachments/'.$evidence->uuid)->assertRedirect('/login');

        // A public business's logo is public; once the business goes private it is not, and a replaced logo never is.
        $this->as(null)->get('/attachments/'.$logo->uuid)->assertOk();
        $this->as($this->owner)->post($this->businessUrl('/media'), ['role' => 'logo', 'file' => $this->picture('new-logo.jpg', 400, 400)])->assertRedirect();
        $this->as(null)->get('/attachments/'.$logo->uuid)->assertRedirect('/login');
        $this->as($this->member)->get('/attachments/'.$logo->uuid)->assertNotFound();

        $current = Team::findOrFail($this->business->id)->currentMedia('logo');
        $this->business->profileOrNew()->forceFill(['visibility' => 'private'])->save();
        $this->as(null)->get('/attachments/'.$current->uuid)->assertRedirect('/login');
        $this->as($this->member)->get('/attachments/'.$current->uuid)->assertNotFound();
        $this->as($this->colleague)->get('/attachments/'.$current->uuid)->assertOk();
    }

    public function test_only_profile_pictures_are_served_from_the_storage_address(): void
    {
        Storage::disk('public')->put('other/secret.jpg', 'x');
        Storage::disk('public')->put('profile-photos/notes.txt', 'x');

        $this->as(null)->get('/storage/other/secret.jpg')->assertNotFound();
        $this->as(null)->get('/storage/profile-photos/notes.txt')->assertNotFound();
        $this->as(null)->get('/storage/profile-photos/..%2Fother%2Fsecret.jpg')->assertNotFound();
        $this->as(null)->get('/storage/profile-photos/missing.jpg')->assertNotFound();
    }

    // ---- what the views name --------------------------------------------------------------------------------------

    public function test_every_file_a_view_names_under_public_exists_and_no_new_one_has_been_added(): void
    {
        $named = [];
        foreach ($this->sources() as $file => $source) {
            preg_match_all('/(?<!::)\basset\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $source, $m);
            foreach ($m[1] as $path) {
                $named[ltrim($path, '/')][] = $file;
            }
            // A hard-coded address under the web root, in an attribute or a style.
            preg_match_all('#(?:src|href|poster|content)="/((?:img|css|js|fonts|images)/[^"{}]+)"|url\([\'"]?/((?:img|css|js|fonts|images)/[^\'"){}]+)#', $source, $h);
            foreach (array_filter(array_merge($h[1], $h[2])) as $path) {
                $named[$path][] = $file;
            }
        }
        ksort($named);

        foreach ($named as $path => $files) {
            $this->assertFileExists(public_path($path), "{$path} is named by ".implode(', ', array_unique($files)).' but is not in public/');
        }
        $this->assertSame(self::LEGACY_PUBLIC_FILES, array_keys($named),
            'A view names a file under public/ that is not on the closed list. The live web root only receives public/build: put new pictures in resources/img and name them with Vite::asset.');
    }

    public function test_every_picture_named_through_vite_is_a_source_file_the_build_picks_up(): void
    {
        $checked = 0;
        foreach ($this->sources() as $file => $source) {
            preg_match_all('/Vite::asset\(\s*[\'"]([^\'"$]+)[\'"]\s*\)/', $source, $m);
            foreach ($m[1] as $path) {
                $this->assertNull($this->builtFromSource('/build/source/'.$path), "{$file}: ".$this->builtFromSource('/build/source/'.$path));
                $checked++;
            }
        }
        $this->assertGreaterThan(4, $checked);

        // Logos named by a platform key: every platform a person can be shown has its logo in both sets.
        foreach (array_keys(config('ecosystem.platforms')) as $key) {
            foreach (['apps', 'landing/platforms'] as $folder) {
                if (is_file(resource_path("img/{$folder}/{$key}.png"))) {
                    $this->assertNull($this->builtFromSource("/build/source/resources/img/{$folder}/{$key}.png"));
                }
            }
        }
    }

    public function test_no_view_shows_a_person_except_through_the_shared_avatar(): void
    {
        foreach ($this->sources() as $file => $source) {
            if (str_contains($file, 'components/ix/avatar') || ! str_ends_with($file, '.blade.php')) {
                continue;
            }
            $this->assertStringNotContainsString('profile_photo_url', $source, "{$file} builds a profile picture by hand; use <x-ix.avatar> or User::photoUrl(), which fall back to the initial when the file is gone");
            $this->assertStringNotContainsString('gravatar', strtolower($source), "{$file} asks another site for a picture of a person");
        }
    }

    public function test_no_view_carries_an_inline_handler_or_a_placeholder_link(): void
    {
        foreach ($this->sources() as $file => $source) {
            if (! str_ends_with($file, '.blade.php') || str_contains($file, '/vendor/mail/')) {
                continue;
            }
            // script-src-attr 'none': a native on*= attribute never runs, so the control it sits on is dead.
            $this->assertSame(0, preg_match('/<[a-z](?:[^>]|(?<=[-=])>)*\s(onclick|onsubmit|onchange|oninput|onfocus|onblur|onload|onerror|onkeydown|onkeyup)\s*=/i', $source), "{$file} uses an inline event handler, which the Content-Security-Policy blocks; use an Alpine directive");
            // A ">" straight after "-" or "=" is Blade's own (->, =>), not the end of the tag.
            preg_match_all('/<a\b(?:[^>]|(?<=[-=])>)*\shref="(#|javascript:[^"]*|)"(?:[^>]|(?<=[-=])>)*>/i', $source, $m);
            foreach ($m[0] as $tag) {
                $this->assertSame(1, preg_match('/(@click|x-on:click|wire:click)/', $tag), "{$file} has a link to nowhere: {$tag}");
            }
        }
    }

    public function test_every_outside_host_a_page_loads_from_is_allowed_by_the_content_security_policy(): void
    {
        $policy = (string) $this->as(null)->get('/')->headers->get('Content-Security-Policy');
        $allowed = function (string $directive, string $host) use ($policy): bool {
            foreach (explode(';', $policy) as $part) {
                $words = preg_split('/\s+/', trim($part)) ?: [];
                if (array_shift($words) === $directive) {
                    return in_array('https://'.$host, $words, true) || in_array('https:', $words, true);
                }
            }

            return false;
        };

        $found = [];
        foreach ($this->sources() as $file => $source) {
            if (! str_ends_with($file, '.blade.php') || str_contains($file, '/vendor/mail/') || str_contains($file, '/emails/')) {
                continue; // an email is opened in a mail program, which this site's policy does not govern
            }
            preg_match_all('#<(img|script|iframe|source|video|audio)\b[^>]*\ssrc="https?://([^/"]+)#i', $source, $tags, PREG_SET_ORDER);
            foreach ($tags as $t) {
                $found[] = [match (strtolower($t[1])) {
                    'script' => 'script-src', 'iframe' => 'frame-src', 'video', 'audio', 'source' => 'media-src', default => 'img-src'
                }, $t[2], $file];
            }
            preg_match_all('#<link\b[^>]*\brel="stylesheet"[^>]*\shref="https?://([^/"]+)|<link\b[^>]*\shref="https?://([^/"]+)[^>]*\brel="stylesheet"#i', $source, $links, PREG_SET_ORDER);
            foreach ($links as $l) {
                $found[] = ['style-src', $l[1] ?: $l[2], $file];
            }
            preg_match_all('#url\([\'"]?https?://([^/\'")]+)#i', $source, $urls);
            foreach ($urls[1] as $host) {
                $found[] = ['img-src', $host, $file];
            }
        }
        // The stylesheets the fonts come from name their font files on these hosts.
        foreach (['fonts.gstatic.com', 'cdnjs.cloudflare.com'] as $fontHost) {
            $found[] = ['font-src', $fontHost, 'the Google Fonts and Font Awesome stylesheets'];
        }
        foreach ((new Finder)->files()->in(resource_path('css'))->name('*.css') as $css) {
            preg_match_all('#(?:url\(|@import\s+)[\'"]?https?://([^/\'")]+)#i', $css->getContents(), $urls);
            foreach ($urls[1] as $host) {
                $found[] = [str_contains($host, 'font') ? 'font-src' : 'img-src', $host, $css->getRelativePathname()];
            }
        }

        $this->assertNotEmpty($found);
        foreach ($found as [$directive, $host, $file]) {
            $this->assertTrue($allowed($directive, $host), "{$file} loads from {$host}, which the Content-Security-Policy {$directive} does not allow");
        }
    }

    /** @return array<string, string> every Blade view and every PHP file that can name a file, by relative path */
    private function sources(): array
    {
        $sources = [];
        foreach ((new Finder)->files()->in([resource_path('views'), app_path()])->name('*.php') as $file) {
            $sources[str_replace(base_path().'/', '', $file->getRealPath())] = $file->getContents();
        }

        return $sources;
    }
}

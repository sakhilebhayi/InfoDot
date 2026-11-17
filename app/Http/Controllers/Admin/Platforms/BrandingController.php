<?php

namespace App\Http\Controllers\Admin\Platforms;

use App\Http\Controllers\Admin\Platforms\Concerns\ActsAsAdministrator;
use App\Http\Controllers\Controller;
use App\Models\DotPlatform;
use App\Models\PlatformBranding;
use App\Models\PlatformCatalogueEvent;
use App\Services\Platforms\EntitlementRefused;
use App\Services\Sso\BrandingAdmin;
use App\Support\Branding\PlatformBrandingView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * R-54: how each platform looks on the central sign-in and registration pages. For platform administrators (route
 * middleware, and the service asks the gate again). Edit, preview without saving, then publish: a publish needs a
 * written reason, moves the version by one and is kept in the audit trail.
 */
class BrandingController extends Controller
{
    use ActsAsAdministrator;

    public function __construct(private readonly BrandingAdmin $branding) {}

    public function index(): View
    {
        return view('admin.platforms.branding', [
            'platforms' => DotPlatform::query()->with('branding')->orderBy('slug')->get(),
            'registry' => array_keys((array) config('ecosystem.platforms', [])),
        ]);
    }

    public function edit(DotPlatform $platform): View
    {
        return view('admin.platforms.branding-edit', $this->page($platform, $platform->branding ?? new PlatformBranding(['mode' => 'light', 'active' => true])));
    }

    /** Draws the page as it would look with the submitted values. Saves nothing and stores no file. */
    public function preview(Request $request, DotPlatform $platform): View
    {
        $data = $this->validated($request, withReason: false);
        $row = $platform->branding ? clone $platform->branding : new PlatformBranding(['dot_platform_id' => $platform->id]);
        $row->fill(collect($data)->only(BrandingAdmin::FIELDS)->all());
        $row->setRelation('platform', $platform);
        $row->active = true;

        return view('admin.platforms.branding-edit', $this->page($platform, $row, preview: true));
    }

    public function publish(Request $request, DotPlatform $platform): RedirectResponse
    {
        $data = $this->validated($request, withReason: true);
        $back = redirect()->route('admin.platforms.branding.edit', $platform);

        return $this->attempt($back, function () use ($data, $platform, $request) {
            $before = $platform->branding?->version;
            $row = $this->branding->publish(
                $platform,
                $data,
                array_filter(['logo' => $request->file('logo'), 'hero' => $request->file('hero'), 'background' => $request->file('background')]),
                array_keys(array_filter(['logo' => $request->boolean('remove_logo'), 'hero' => $request->boolean('remove_hero'), 'background' => $request->boolean('remove_background')])),
                $request->user(),
                $data['reason'],
            );

            return $row->version === $before ? 'Nothing was different, so nothing was changed.' : 'Published as version '.$row->version.'. The change is in the audit trail.';
        });
    }

    /** @return array<string, mixed> */
    private function page(DotPlatform $platform, PlatformBranding $row, bool $preview = false): array
    {
        $row->setRelation('platform', $platform);

        return [
            'platform' => $platform,
            'row' => $row,
            'view' => PlatformBrandingView::preview($platform, $row),
            'preview' => $preview,
            'events' => PlatformCatalogueEvent::with('actor')->where('subject_type', 'branding')->where('subject_key', $platform->slug)->orderByDesc('id')->limit(30)->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $withReason): array
    {
        $hex = ['nullable', 'string', 'regex:'.PlatformBranding::HEX];
        $image = fn (string $kind) => ['nullable', 'file', 'max:'.(int) ($kind === 'logo' ? 512 : 2048), 'mimes:png,jpg,jpeg,webp'];

        $rules = [
            'display_name' => ['nullable', 'string', 'max:80'],
            'primary_color' => $hex, 'secondary_color' => $hex, 'accent_color' => $hex,
            'mode' => ['required', Rule::in(PlatformBranding::MODES)],
            'hero_alt' => ['nullable', 'string', 'max:200'],
            'headline_login' => ['nullable', 'string', 'max:120'],
            'headline_register' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:300'],
            'promo_text' => ['nullable', 'string', 'max:200'],
            'cta_login' => ['nullable', 'string', 'max:40'],
            'cta_register' => ['nullable', 'string', 'max:40'],
            'approved_redirect_domains' => ['nullable', 'string', 'max:1000'],
            'active' => ['nullable', 'boolean'],
            'logo' => $image('logo'), 'hero' => $image('hero'), 'background' => $image('background'),
        ];

        if ($withReason) {
            $rules['reason'] = self::REASON;
        }

        $data = $request->validate($rules, self::REASON_MESSAGES + [
            'primary_color.regex' => 'Colours are written as #rrggbb, for example #1d5fbf.',
            'secondary_color.regex' => 'Colours are written as #rrggbb, for example #1d5fbf.',
            'accent_color.regex' => 'Colours are written as #rrggbb, for example #1d5fbf.',
        ]);

        $data['active'] = (bool) ($data['active'] ?? false);
        $data['approved_redirect_domains'] = preg_split('/[\s,]+/', (string) ($data['approved_redirect_domains'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach (['display_name', 'hero_alt', 'headline_login', 'headline_register', 'description', 'promo_text', 'cta_login', 'cta_register'] as $text) {
            $data[$text] = isset($data[$text]) ? trim(strip_tags((string) $data[$text])) ?: null : null;
        }

        try {
            if (($domains = $data['approved_redirect_domains']) !== []) {
                $platform = $request->route('platform');
                app(BrandingAdmin::class)->domains($platform, $domains);
            }
        } catch (EntitlementRefused $e) {
            throw ValidationException::withMessages(['approved_redirect_domains' => $e->getMessage()]);
        }

        return $data;
    }
}

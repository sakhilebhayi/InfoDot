<?php

namespace App\Services\Sso;

use App\Models\DotPlatform;
use App\Models\PlatformBranding;
use App\Models\PlatformCatalogueEvent;
use App\Models\User;
use App\Services\Platforms\EntitlementRefused;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * R-54: the only writer of platform branding once it exists. Platform administrators only (the gate is asked again
 * here, not just at the route), every publish needs a written reason, and what changed, who changed it and why are
 * appended to the same audit trail as the catalogue, in the same transaction. A publish that changes nothing writes
 * nothing and does not move the version; one that changes anything moves it by exactly one.
 */
class BrandingAdmin
{
    public const FIELDS = [
        'display_name', 'primary_color', 'secondary_color', 'accent_color', 'mode', 'hero_alt', 'headline_login', 'headline_register',
        'description', 'promo_text', 'cta_login', 'cta_register', 'approved_redirect_domains', 'active',
    ];

    private const IMAGE_COLUMNS = ['logo' => 'logo_path', 'hero' => 'hero_path', 'background' => 'background_path'];

    public function __construct(private readonly BrandingImages $images) {}

    /**
     * @param  array<string, mixed>  $values  validated field values (see FIELDS)
     * @param  array<string, UploadedFile|null>  $uploads  keyed logo | hero | background
     * @param  list<string>  $removeImages  kinds to clear
     */
    public function publish(DotPlatform $platform, array $values, array $uploads, array $removeImages, User $actor, string $reason): PlatformBranding
    {
        Gate::forUser($actor)->authorize('administer-platforms');

        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new EntitlementRefused('Write why the change is being made, in at least 10 characters. It is kept in the audit trail.');
        }

        $values = array_intersect_key($values, array_flip(self::FIELDS));
        $values['approved_redirect_domains'] = $this->domains($platform, (array) ($values['approved_redirect_domains'] ?? []));

        // Files are checked and written first: a refused image changes nothing else.
        foreach (self::IMAGE_COLUMNS as $kind => $column) {
            if (($uploads[$kind] ?? null) instanceof UploadedFile) {
                $values[$column] = $this->images->store($uploads[$kind], (string) $platform->slug, $kind, $kind);
            } elseif (in_array($kind, $removeImages, true)) {
                $values[$column] = null;
            }
        }

        return DB::transaction(function () use ($platform, $values, $actor, $reason) {
            $row = PlatformBranding::query()->where('dot_platform_id', $platform->id)->lockForUpdate()->first()
                ?? new PlatformBranding(['dot_platform_id' => $platform->id, 'version' => 0]);

            $created = ! $row->exists;
            $row->fill($values);
            $changed = array_keys($row->getDirty());

            if (! $created && $changed === []) {
                return $row;
            }

            $before = $created ? [] : $this->plain(array_intersect_key($row->getRawOriginal(), array_flip($changed)));
            $row->version = (int) $row->version + 1;
            $row->updated_by = $actor->id;
            $row->save();

            PlatformCatalogueEvent::create([
                'actor_id' => $actor->id, 'subject_type' => 'branding', 'subject_key' => (string) $platform->slug,
                'type' => $created ? 'branding_created' : 'branding_published',
                'before' => $before ?: null,
                'after' => $this->plain(array_intersect_key($row->getAttributes(), array_flip($changed))) + ['version' => $row->version],
                'reason' => Str::limit($reason, 250, ''), 'created_at' => now(),
            ]);

            return $row;
        });
    }

    /**
     * The platform's own host and its subdomains, nothing else: a branding row can never approve a foreign domain.
     *
     * @param  array<int, mixed>  $domains
     * @return list<string>
     */
    public function domains(DotPlatform $platform, array $domains): array
    {
        $own = strtolower((string) parse_url((string) $platform->domain, PHP_URL_HOST));
        $clean = [];

        foreach ($domains as $domain) {
            $domain = strtolower(trim((string) $domain));

            if ($domain === '') {
                continue;
            }

            $valid = preg_match('/\A(?=.{1,253}\z)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/', $domain) === 1
                || preg_match('/\A[a-z0-9-]+\.(test|localhost)\z/', $domain) === 1;

            if (! $valid || $own === '' || ($domain !== $own && ! str_ends_with($domain, '.'.$own))) {
                throw new EntitlementRefused("'{$domain}' is not the platform's own address or one of its subdomains, so it cannot be approved.");
            }

            $clean[$domain] = $domain;
        }

        return array_values($clean);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function plain(array $values): array
    {
        unset($values['updated_at'], $values['created_at']);

        return array_map(fn ($value) => is_string($value) && str_starts_with($value, '[') ? (json_decode($value, true) ?? $value) : $value, $values);
    }
}

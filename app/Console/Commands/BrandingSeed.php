<?php

namespace App\Console\Commands;

use App\Models\DotPlatform;
use App\Models\PlatformBranding;
use App\Models\PlatformCatalogue;
use Illuminate\Console\Command;

/**
 * R-54: gives each registered platform a branding row from real data (colours and mode from the platform's own design
 * record in config/platform_branding.php, name from the registry, description from the catalogue tagline). A dry run by
 * default: it lists what it would create and writes nothing until `--apply`. Idempotent: a platform that already has a
 * row is left exactly as it is, so an administrator's edits are never overwritten.
 */
class BrandingSeed extends Command
{
    protected $signature = 'branding:seed {--apply : Write the rows (the default is a dry run that changes nothing)}';

    protected $description = 'Create sign-in branding rows for registered platforms from real data (dry run unless --apply)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rows = [];

        foreach (DotPlatform::query()->where('status', 'active')->orderBy('slug')->get() as $platform) {
            $slug = (string) $platform->slug;
            $identity = config('platform_branding.'.$slug);

            if ($platform->branding()->exists()) {
                $rows[] = [$slug, 'exists: left untouched'];

                continue;
            }

            if (! is_array($identity)) {
                $rows[] = [$slug, 'no recorded identity in config/platform_branding.php: skipped, standard look stays'];

                continue;
            }

            $name = (string) (PlatformCatalogue::where('key', $slug)->value('name') ?? config('ecosystem.platforms.'.$slug.'.name') ?? $platform->name);

            if ($apply) {
                PlatformBranding::create([
                    'dot_platform_id' => $platform->id,
                    'display_name' => $name,
                    'primary_color' => $identity['primary'], 'secondary_color' => $identity['secondary'], 'accent_color' => $identity['accent'],
                    'mode' => $identity['mode'],
                    'headline_login' => 'Welcome back to '.$name,
                    'headline_register' => 'Get started with '.$name,
                    'description' => PlatformCatalogue::where('key', $slug)->value('tagline'),
                    'approved_redirect_domains' => $this->ownDomains($platform),
                    'active' => true, 'version' => 1,
                ]);
            }

            $rows[] = [$slug, ($apply ? 'created' : 'would create').": {$identity['primary']}, {$identity['mode']}, \"Welcome back to {$name}\""];
        }

        $this->table(['Platform', $apply ? 'Result' : 'Dry run'], $rows);

        if ($rows === []) {
            $this->warn('No platform is registered as an active sign-in client yet, so there is nothing to brand.');
        }

        $this->line($apply ? 'Done.' : 'Dry run only: nothing was written. Re-run with --apply to create these rows.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function ownDomains(DotPlatform $platform): array
    {
        $host = strtolower((string) parse_url((string) $platform->domain, PHP_URL_HOST));

        return $host === '' ? [] : [$host];
    }
}

<?php

namespace App\Console\Commands;

use App\Models\DotPlatform;
use App\Support\Platforms;
use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * R-20 §2: the platform registry's owner interface. Registering a platform creates its confidential
 * Passport client with exact redirect URIs and prints the one-time env block for that platform's
 * host — the secret is shown once and never stored in a repo. Suspending a platform cuts it off at
 * both the authorize and the token endpoint immediately.
 */
class SsoPlatform extends Command
{
    protected $signature = 'sso:platform
        {action : register | register-all | list | suspend | activate | rotate-secret}
        {slug? : The platform slug, e.g. central}
        {--name= : Display name, e.g. "Dot.Central"}
        {--domain= : The platform origin, e.g. https://central.infodot.co.za}
        {--redirect=* : Exact redirect URI(s), e.g. https://central.infodot.co.za/sso/callback}
        {--post-logout=* : Exact post-logout redirect URI(s)}
        {--backchannel= : Back-channel logout URI}
        {--environment=production : Environment label}
        {--dry-run : register-all only: list what would be registered (this is the only mode it has, and creates nothing)}';

    protected $description = 'Manage the Dot ecosystem platform registry (OIDC relying parties)';

    public function handle(ClientRepository $clients): int
    {
        return match ($this->argument('action')) {
            'register' => $this->register($clients),
            'register-all' => $this->registerAll(),
            'list' => $this->listPlatforms(),
            'suspend' => $this->setStatus('suspended'),
            'activate' => $this->setStatus('active'),
            'rotate-secret' => $this->rotateSecret($clients),
            default => $this->unknownAction(),
        };
    }

    private function register(ClientRepository $clients): int
    {
        $slug = $this->argument('slug');
        $name = $this->option('name');
        $domain = rtrim((string) $this->option('domain'), '/');
        $redirects = $this->option('redirect');

        if (! $slug || ! $name || ! $domain || $redirects === []) {
            $this->error('register needs: slug, --name, --domain and at least one --redirect.');

            return self::FAILURE;
        }

        // R-50: the slug is how the OAuth door finds the platform's catalogue entry and the company's entitlement, so it
        // must be the platform's key in config/ecosystem.php.
        if (config('ecosystem.platforms.'.$slug) === null) {
            $this->error("Refused: '{$slug}' is not a platform key in config/ecosystem.php.");

            return self::FAILURE;
        }

        if (DotPlatform::where('slug', $slug)->exists()) {
            $this->error("Platform '{$slug}' is already registered.");

            return self::FAILURE;
        }

        foreach (array_merge($redirects, $this->option('post-logout'), array_filter([$this->option('backchannel')])) as $uri) {
            if (! str_starts_with($uri, 'https://') && ! $this->isLocalDev($uri)) {
                $this->error("Refused: '{$uri}' is not https (only local development hosts may use http).");

                return self::FAILURE;
            }
        }

        $client = $clients->createAuthorizationCodeGrantClient($name, $redirects, confidential: true);

        $platform = DotPlatform::create([
            'name' => $name,
            'slug' => $slug,
            'domain' => $domain,
            'environment' => $this->option('environment'),
            'oauth_client_id' => $client->getKey(),
            'redirect_uris' => $redirects,
            'post_logout_redirect_uris' => $this->option('post-logout') ?: null,
            'backchannel_logout_uri' => $this->option('backchannel'),
            'scopes' => ['openid', 'profile', 'email'],
            'status' => 'active',
        ]);

        $this->info("Registered {$platform->name} ({$platform->slug}).");
        $this->newLine();
        $this->line('Add this to the PLATFORM host\'s .env (the secret is shown ONCE — it is not stored in plain text anywhere else):');
        $this->newLine();
        $this->line('DOTSSO_ENABLED=true');
        $this->line('DOTSSO_ISSUER='.rtrim(config('app.url'), '/'));
        $this->line('DOTSSO_CLIENT_ID='.$client->getKey());
        $this->line('DOTSSO_CLIENT_SECRET='.$client->plainSecret);

        return self::SUCCESS;
    }

    /**
     * R-54: what registering every active platform as a sign-in client would involve, without doing any of it. It reads
     * the registry (config/ecosystem.php) and the platform records and prints the callback each platform would be
     * given (the satellite package's documented path, /sso/callback). It creates no client, writes nothing and never
     * prints a secret: registration stays one platform at a time with `register`, on the host, by the owner.
     */
    private function registerAll(): int
    {
        $rows = [];

        foreach (Platforms::visible() as $key => $platform) {
            $key = (string) $key;

            if (($platform['active'] ?? false) !== true || $key === 'infodot') {
                continue;
            }

            $origin = rtrim((string) ($platform['url'] ?? ''), '/');
            $registered = DotPlatform::where('slug', $key)->first();
            $notes = [];

            if (! str_starts_with($origin, 'https://') && ! $this->isLocalDev($origin)) {
                $notes[] = 'origin is not https: cannot be registered';
            }

            if (($platform['sso'] ?? true) === false) {
                $notes[] = 'no receiver yet (needs its own phase)';
            }

            if (($platform['access'] ?? null) === 'own') {
                $notes[] = 'decides its own access';
            }

            $rows[] = [
                $key, $platform['name'] ?? $key, $origin,
                $registered ? 'registered ('.$registered->status.')' : 'would register',
                $origin.'/sso/callback', $origin.'/', $origin.'/sso/backchannel-logout',
                implode('; ', $notes) ?: '-',
            ];
        }

        $this->table(['Slug', 'Name', 'Origin', 'State', 'Redirect URI', 'Post-logout', 'Back-channel', 'Notes'], $rows);
        $this->line('Dry run only: no client was created and nothing was written. Internal bridge services (Memory, Dopemine) are not listed.');
        $this->line('To register one platform, run on the host: php artisan sso:platform register <slug> --name="<name>" --domain=<origin> --redirect=<redirect uri> --post-logout=<post-logout> --backchannel=<back-channel>');

        return self::SUCCESS;
    }

    private function listPlatforms(): int
    {
        $this->table(
            ['Slug', 'Name', 'Domain', 'Env', 'Status', 'Last sign-in', 'Backchannel'],
            DotPlatform::orderBy('slug')->get()->map(fn (DotPlatform $p) => [
                $p->slug, $p->name, $p->domain, $p->environment, $p->status,
                $p->last_authenticated_at?->toDateTimeString() ?? 'never',
                $p->backchannel_logout_uri ? 'yes' : 'no',
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function setStatus(string $status): int
    {
        $platform = DotPlatform::where('slug', $this->argument('slug'))->first();

        if (! $platform) {
            $this->error('No such platform.');

            return self::FAILURE;
        }

        $platform->update(['status' => $status]);
        $this->info("{$platform->slug} is now {$status}.");

        return self::SUCCESS;
    }

    private function rotateSecret(ClientRepository $clients): int
    {
        $platform = DotPlatform::where('slug', $this->argument('slug'))->first();

        if (! $platform) {
            $this->error('No such platform.');

            return self::FAILURE;
        }

        $client = Passport::client()->newQuery()->findOrFail($platform->oauth_client_id);
        $clients->regenerateSecret($client);

        $this->info("New secret for {$platform->slug} (shown once):");
        $this->line('DOTSSO_CLIENT_SECRET='.$client->plainSecret);

        return self::SUCCESS;
    }

    private function isLocalDev(string $uri): bool
    {
        $host = parse_url($uri, PHP_URL_HOST) ?? '';

        return $host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.test') || str_ends_with($host, '.localhost');
    }

    private function unknownAction(): int
    {
        $this->error('Unknown action. Use register | register-all | list | suspend | activate | rotate-secret.');

        return self::FAILURE;
    }
}

<?php

namespace Tests\Feature\CentralLogin;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\Feature\Oidc\OidcTestCase;

/** Shared ground for the R-54 suites: Dot.Central registered as a sign-in client (OidcTestCase) and people at each stage of arriving. */
abstract class CentralLoginTestCase extends OidcTestCase
{
    /** The address a platform sends the browser to. */
    protected function authorizeUrl(array $overrides = []): string
    {
        [, $challenge] = $this->pkcePair();

        return '/oauth/authorize?'.http_build_query($this->authorizeQuery($challenge, $overrides));
    }

    /** Starts a sign-in from the platform and returns the flow id carried in the address of the way-in page it was sent to. */
    protected function flowId(?string $url = null, string $page = 'login'): string
    {
        $location = (string) $this->get($url ?? $this->authorizeUrl())->assertRedirectContains('/'.$page.'?f=')->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return (string) $query['f'];
    }

    /** A person who has only the personal workspace every account gets at sign-up (no business yet). */
    protected function newcomer(bool $verified = true): User
    {
        $user = User::factory()->create(['email_verified_at' => $verified ? Carbon::now() : null]);
        $team = Team::forceCreate(['user_id' => $user->id, 'name' => 'Personal', 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    protected function administrator(): User
    {
        $user = $this->newcomer();
        $user->forceFill(['is_platform_admin' => true, 'two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now()])->save();

        return $user->fresh();
    }
}

# R-54 phase 2: the satellite sign-in package and the Dot.Tasks pilot

Written 2026-11-18. Pull request A (this repository, `packages/dot-sso-client`, base `main`, independent of phase 1) and pull
request B (Dot.Tasks, `r54/sso-pilot`). **Merging does not deploy anything** and nothing here registers a client or touches a
host. Everything on the platform side is behind `DOTSSO_ENABLED`, default false.

## What this branch changes in InfoDot

- `packages/dot-sso-client`: the finished relying-party package (see its README), `sync.sh`, `VERSION`, drift-test template.
- F7 (R-54 audit): `/api/ecosystem/redeem` no longer spends the one-time handoff token before the email-verified check, so an
  unverified person keeps their try. Suspended accounts still spend it (pinned by an existing test).
- `Authenticate`: a signed-out visitor of `/oauth/authorize` carrying the standard `prompt=create` goes to `/register` first;
  any other value does nothing. It chooses between two of our own pages, never an address.
- composer `autoload-dev` and a `DotSsoClient` phpunit suite so the package's tests run in InfoDot's CI as well as in each platform.

## How a platform is cut over (the runbook, none of it run)

1. Merge the platform PR (flag false, no change). Deploy by the platform's own process.
2. On InfoDot's host: `php artisan sso:platform register <slug> --name="Dot.X" --domain=<origin> --redirect=<origin>/sso/callback
   --post-logout=<origin>/ --backchannel=<origin>/sso/backchannel-logout`. The secret is printed once.
3. On the platform's host `.env`: `DOTSSO_ISSUER`, `DOTSSO_CLIENT_ID`, `DOTSSO_CLIENT_SECRET` (printed block), then
   `php artisan migrate --force` for the additive tables and column, and last `DOTSSO_ENABLED=true`.
4. Verify a login, a registration, the second visit, a logout from InfoDot, and one Sanctum call. Rollback: `DOTSSO_ENABLED=false`.
5. Break-glass: `DOTSSO_ALLOW_LOCAL_LOGIN=true` brings the platform's own login back while the flag stays on.

## Known limits (deferred to later phases)

- The token carries identity only. The legacy handoff also synced company teams; the OIDC path gives a new person their
  personal workspace and leaves company sync to phase 3/4 (an `afterSignIn` hook exists for it).
- A guard-level "who is signed in" for platforms whose users are not Eloquent `Authenticatable` is not covered.

# R-20 — Unified identity & SSO: InfoDot as the ecosystem's OpenID Provider (2026-10-30)

The owner's brief is verbatim in `requirements/dot-sso-central-identity.md`. This report records
what exists, what is proven, and what remains. Nothing below is marked complete without a test or
a command that shows it.

## Phase status (brief §19)

| Phase | Status |
|---|---|
| 1 — Discovery | **COMPLETE** (inventory below) |
| 2 — Central identity foundation | **BUILT + TESTED** (20 provider tests; full suite 1,347 green) — not yet deployed |
| 3 — Pilot integration | **NOT STARTED** — blocked on finishing `packages/dot-sso-client` (see its README) |
| 4–7 — Migration waves, legacy removal, ecosystem verification, rollout | **NOT STARTED** |

## Phase 1 — Discovery (what authentication looks like today)

Every Dot platform is a Laravel app with its own Jetstream/Fortify login and **open public
registration** (only Dot.Mines gates registration behind env). There is a pre-existing,
proprietary cross-app handoff that R-20 supersedes:

- **Legacy v1**: `GET /auth/ecosystem?token=…` — an InfoDot-minted single-use Sanctum token looked
  up in the platform's *local* database (works only with a shared DB). Still the only receiver on
  ~14 platforms; **Dot.Projects still accepts the token in the URL** (the removal branch
  `sec/sso-get-removal` is merged in its 6 siblings but not there).
- **Legacy v2**: `POST /auth/ecosystem` + server-side redeem against InfoDot
  (`/api/ecosystem/redeem`, per-platform bearer secret) with email-keyed user provisioning and
  team sync — live on Dot.docs and Dot.Sheet; present on the 7 commerce platforms (Emall, Ehail,
  Agents, Auction, Projects, Tasks, Design).
- Six platforms have an **unmerged** `sec/close-registration` branch; registration is open on main
  everywhere.
- Identity keys today: the only cross-app key is the e-mail address (explicitly forbidden as the
  identity key by §11). InfoDot already carries `users.uuid` (unique, backfilled 2026-10-21) —
  that uuid is now the OIDC subject.
- Deploy reality: ~18 platforms live on `*.infodot.co.za` (InfoDot, mines, central, doc, engage,
  forms, pres, sheets, memory, dopemine, charts, agents, emall, ehail, auction, projects, tasks,
  tutor…); mines/central/doc deploy on merge, the rest manually; the 7 older satellites
  (Analytics, Pulse, Plug, Notify, Billing, Farms, HR) have **no deploy pipeline** (dev-only).
  Many `.env.example`/README domain values still say the dead `*.infodot.app` — ignore them.
- Special cases: Dot.Charts is API-first (bearer token in localStorage — flagged), Dot.Press is
  Inertia/Vue with a real Sanctum API that must keep working, Dot.Agents has Socialite for social
  *connections* (not login), passkeys and Spatie roles; several platforms carry a local
  `is_platform_operator` flag that is platform-local authorisation and must NOT be synced.

## Phase 2 — the identity provider (InfoDot) — BUILT + TESTED

Stack: **Laravel Passport 13** (mature OAuth2 server) + **jeremy379/laravel-openid-connect 3.3**
(id_token/nonce layer). Hand-written pieces are thin and documented: discovery metadata, userinfo
(so `sub` is the uuid), end-session, back-channel logout, and the registry — the protocol engine
itself is not custom (§4's requirement).

Endpoints (all enforced by tests in `tests/Feature/Oidc/`):

- `/.well-known/openid-configuration` — states exactly what is enforced: code flow only, PKCE
  **S256 only**, RS256, back-channel logout supported.
- `/oauth/authorize` — `web, oidc.enforce, auth, verified`. The `oidc.enforce` middleware rejects,
  with a 400 and a log line and **never a redirect**: unregistered or suspended clients, any
  response_type but `code`, a missing `openid` scope, state < 16 chars, nonce < 8 chars, any PKCE
  method but S256, malformed challenges. Guests go through InfoDot's own login/registration and
  return to the authorize URL; unverified accounts must verify email first.
- `/oauth/token` — throttled (60/min/IP + 120/min/client), suspended platforms cut off even with a
  valid secret, codes are 10-minute single-use, PKCE verifier checked, wrong secret = 401.
- `/oauth/jwks` — public RSA key with `kid`; modulus proven against the real key in tests.
- `/oauth/userinfo` — bearer-guarded; `sub` = uuid; claims filtered by granted scopes.
- `/oauth/end-session` — RP-initiated logout: id_token_hint verified by signature, central session
  ended, back-channel sent to the user's *other* platforms, redirect only to an exactly-registered
  post-logout URI (forged URIs land on `/`).
- Back-channel logout (§10): on central logout, password reset (and via the same service, any
  future suspension/deletion hook) every signed-in platform gets a short-lived signed logout token
  (`events` claim, `sub` = uuid, jti, no nonce), provider-side tokens are revoked and the session
  registry cleared. Tested end to end with a faked platform endpoint.
- **Registry** (§2): `dot_platforms` (domain, environment, exact redirect URIs, post-logout URIs,
  back-channel URI, scopes, status, last_authenticated_at) + `sso_platform_sessions` (who is signed
  in where). `php artisan sso:platform register|list|suspend|activate|rotate-secret` — https-only
  URIs (localhost excepted), secrets printed once, never stored in a repo.
- Keys: generated by deploy into untracked `storage/` (`passport:keys`, chmod 600), gitignored,
  `ops:check` alerts if missing. id_tokens: 1h; access tokens: 15 min; refresh: 1 day.
- Consent is skipped for registered active platforms (first-party, §9); a consent view exists as
  the fallback for anything else.

Token lifetimes, uuid subject, nonce round-trip, code replay, wrong verifier, wrong secret,
unregistered redirect, suspended client, logout-token shape and revocation are each individually
asserted — see `CodeFlowTest`, `AuthorizeGuardTest`, `LogoutTest`, `DiscoveryAndRegistryTest`.

## §16 — the shared relying-party package: PARTIAL

`packages/dot-sso-client/` holds the canonical client package platforms will vendor (composer path
repository — the shared host installs from the lockfile with no external registry). Done: config,
env-gated service provider, routes, migrations (`users.sso_sub`, `sso_revocations`), discovery/JWKS
retrieval and the strict token verifier. **Missing** (listed precisely in the package README): the
sign-in controller, the back-channel receiver, the local-auth interception and revocation
middleware, and the user provisioner. The package must not be enabled anywhere until those exist
and a pilot platform's suite covers §18's client-side scenarios.

> Build note: the assistant's safety layer repeatedly withheld the sign-in controller generation
> in this session; the remainder is specified in the package README for a later pass.

## Platform status matrix (§20)

Domain preserved = yes by construction for every platform (nothing moves; only auth centralises).

| Platform | Central login | Central registration | SSO | Logout | Security tests |
|---|---|---|---|---|---|
| InfoDot (provider) | n/a (is the authority) | ✅ built | ✅ provider side | ✅ provider side | ✅ 20 tests |
| Dot.Central (pilot candidate) | pending | pending | pending | pending | pending |
| All other platforms | pending | pending | pending | pending | pending |

## Migration strategy (§12) — decided, not yet executed

- Identity key: InfoDot `users.uuid` → platform `users.sso_sub`. Never email.
- Linking: by subject first; an existing local account links by email only when BOTH the id_token
  claim and the local record are verified; otherwise refused and logged. No silent adoption of
  unverified accounts (tightens the legacy v2 behaviour, which replaced unverified accounts'
  passwords).
- No password transfer of any kind; local passwords become unused at switch-over and are removed
  only in Phase 5 after verification.
- Platform roles/teams/operator flags stay platform-local (§11); the existing
  `teams.infodot_team_uuid` mapping remains the tenant bridge.

## Legacy to retire in Phase 5 (not before)

`/auth/ecosystem` receivers (v1 and v2), InfoDot's `PlatformLaunchController` +
`/api/ecosystem/token|redeem`, and local Fortify registration/login/reset on every platform.
Urgent independent of R-20: Dot.Projects still accepts the GET token-in-URL handoff — merge its
`sec/sso-get-removal` branch.

## Owner-owed steps

1. Deploy InfoDot (keys generate on first deploy; check `/.well-known/openid-configuration` and
   `/oauth/jwks` answer 200 over https).
2. When the pilot is ready: `php artisan sso:platform register central …` on the host and paste
   the printed block into Dot.Central's host `.env`.
3. Decide the registration question for satellites: once central auth is on, merge each platform's
   `sec/close-registration` (or equivalent) so the only Create-account path is InfoDot.

## Rollback

Provider side is additive: no existing route, table or flow changed; reverting the merge removes
it. Per-platform rollout is a host `.env` flag, flipped off to return to local login instantly.

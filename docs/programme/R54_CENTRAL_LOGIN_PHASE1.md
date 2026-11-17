# R-54 phase 1: platform identity, branding registry and onboarding step

Written 2026-11-17. Phase 1a (this document, backend) and 1b (the branded pages) are two stacked pull requests. Nothing here
deploys by itself: merging does not deploy (the deploy workflow is manual), and nothing in it registers a client or touches a
host database. The satellite package `packages/dot-sso-client` is phase 2 and is not touched.

## 1. How a page knows which platform started the sign-in

`App\Services\Sso\PlatformContext` is the only place that decides. Its single source is a pending `/oauth/authorize`
request held **server-side in the visitor's session** (`url.intended` from the guest redirect, or the copy the authorize
route remembers under `sso.pending_authorize`). It is believed only when ALL of these hold:

- the path is exactly `/oauth/authorize` on the host the person is on (no other host, no credentials in the address);
- `client_id` is a registered `dot_platforms` row with status `active`;
- the same house rules the authorize endpoint applies pass (`EnforceOidcRules::rejectionReason`: `response_type=code`,
  `openid` scope, `state` >= 16, `nonce` >= 8, PKCE `S256` with a well-formed challenge);
- `redirect_uri` is **exactly** one of that platform's registered redirect URIs.

Anything else (malformed, unknown, suspended, tampered, wrong host) resolves to `null`, the standard InfoDot page, and writes
one log line carrying a reason code only (`sso.pending_ignored`). **No query parameter of `/login` or `/register` is ever
read**: `?platform=` and `?client=` do nothing. A spoofed parameter cannot override a real pending request either.

Supporting changes:

- `RememberPendingAuthorize` (route alias `sso.pending`) stores a valid request for the detour login, registration,
  verification and the business step, and forgets it when the endpoint redirects away from InfoDot (the code, or an error,
  going back to the platform).
- Laravel always runs `Authenticate` ahead of middleware that is not on its priority list. `app/Http/Kernel.php` now lists
  `EnforceOidcRules` and `RememberPendingAuthorize` ahead of it, so a malformed request is refused with 400 BEFORE a signed-out
  visitor is parked at sign-in (it used to be saved as the place to return to and refused only after sign-in).
- `RedirectIfAuthenticated`: a signed-in person who opens `/login` or `/register` while a valid request is pending continues
  that request instead of landing on Home.
- Verification: `VerifiedEmailResponse` continues the pending request after the address is confirmed.

## 2. The branding registry

Table `platform_branding` (additive, 1:1 on `dot_platforms`, valid on SQLite and MySQL): display name, logo path, primary,
secondary and accent colour (`#rrggbb` only), `light|dark`, background and hero image paths, hero alt text, login and
registration headlines, description, promo line, two button labels, `approved_redirect_domains` (json), `active`, `version`,
`updated_by`.

`App\Support\Branding\PlatformBrandingView` is the only thing a Blade view receives (`$platformBranding`, null on a direct
visit). It has no property for a client id, secret, redirect URI or callback; colours are re-validated when it is built, words
have tags stripped, and `cssVariables()` can only print four validated colours. With no row (or a switched-off row) it falls
back to the catalogue name and tagline and the registry accent, so a registered platform always has a name.

Images are uploaded only through `BrandingImages`: type read from the bytes (PNG, JPEG, WebP; **SVG refused**), size and
dimension limits (logo 64-2400 px, 512 KB; hero and background 800x500-3840x2560 px, 2 MB), decoded and re-encoded (so
metadata, appended payloads and polyglots do not survive), stored on the private disk under
`platform-branding/<slug>/<kind>-<hash>.png|webp`, and served by `GET /brand/platform/{slug}/{logo|hero|background}` from
InfoDot's own origin, from the path in the database only, with `nosniff` and a long immutable cache (the version is in the
address).

Admin: `/admin/platforms/branding` (platform administrators only, the `administer-platforms` gate at the route and again in
`BrandingAdmin`). Edit, preview without saving, publish with a written reason (10-255 characters); a publish that changes
something moves `version` by exactly one and writes a `branding_created` / `branding_published` entry in the same audit trail as
the catalogue (`platform_catalogue_events`, subject `branding`). Approved redirect domains must be the platform's own host or
its subdomains. The list never changes where a sign-in returns: that is fixed by the registered redirect URIs.

## 3. After registration: the business step

Register, then verify the email, then the pending request continues. If the platform refuses with `NO_PERSONAL_ACCESS` and
the person has **no business at all**, they are sent to `/onboarding/business` ("Set up your business") instead of a dead
refusal. The step uses the existing `CreateTeam` action (same validation, same profile row, same audit line, and the choice of
who sees the page is asked, never assumed), then returns to the pending request, where the platform's entitlement check runs
exactly as before (a new business has not switched the platform on; the existing page offers that). It adds no licence or
entitlement rule. A person who already has a business, or whose request is not a platform's, is not sent there.

## 4. Rate limits and cookies (reviewed)

| Control | Before | Now |
|---|---|---|
| Login | 5/min per address+email, 30/min per address | same, plus the email is lower-cased and trimmed (case variants shared no counter), plus 20 per 15 min per account across all addresses |
| Forgot / reset password | 6 / 10 per 10 min per address | same, plus 5 per 10 min per email address across all addresses |
| Register | 30 per 10 min per address | unchanged |
| Verification resend / link | `throttle:6,1` (Fortify) | unchanged (tested) |
| Two-factor | 5/min per login session | unchanged |
| Business step | n/a | 10/min |

Session cookie: `HttpOnly`, `SameSite=Lax`, `Secure` in production, host-only (no domain), 120 minutes idle. CSRF (`web` group)
covers every way-in form and the business step; no sign-in path is on the CSRF exception list. These are asserted in
`tests/Feature/CentralLogin/AuthHardeningTest.php`.

## 5. Owner runbook (on the host, after the PRs are merged and deployed)

Nothing below has been run anywhere. Read-only first.

1. See what would be registered: `php artisan sso:platform register-all --dry-run`. It lists every active, non-internal
   platform with the redirect URI it would get (`<origin>/sso/callback`, the satellite package's path), post-logout and
   back-channel addresses. It creates nothing and prints no secret. Charts shows "no receiver yet" (its own phase).
2. Register ONE platform at a time, only when that platform's phase-2 receiver is ready (a registered client with no receiver
   is harmless but pointless): `php artisan sso:platform register <slug> --name="Dot.X" --domain=<origin>
   --redirect=<origin>/sso/callback --post-logout=<origin>/ --backchannel=<origin>/sso/backchannel-logout`. The secret is
   printed once for that platform's `.env`.
3. Preview the branding rows: `php artisan branding:seed` (dry run). Create them: `php artisan branding:seed --apply`. Rows
   exist only for registered, active platforms, are never overwritten, and use the colours and mode recorded from each
   platform's own design record (`config/platform_branding.php`), the registry name and the catalogue tagline.
4. Edit or upload real logos and screens in Platform admin, Sign-in branding. Until a platform has an upload, its page uses
   the platform logo that ships with the app and neutral token surfaces.
5. Run the migration (`php artisan migrate`): one additive table.
6. Env flags: none are required by this phase.

Open item for the owner: Dot.docs has two palettes (gold landing, green app); gold is used until you decide.

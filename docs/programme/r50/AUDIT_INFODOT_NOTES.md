# R-50 audit notes: InfoDot access model (main 2fcbe3f, read 2026-11-13)

Condensed from the read-only audit. File references are to InfoDot main.

## Registry
- `config/ecosystem.php:30-56`: 25 keys (name, url, icon, accent, active); all active. `sso => false`: charts, mines.
  `internal => true`: dopemine, memory (+ hard-coded `brain` in `app/Support/Platforms.php:16`).
- No category, description, price or plan in config. Groups hard-coded twice: `app/Livewire/EcosystemWidget.php:29-35`
  and `resources/views/livewire/dot-switcher.blade.php:4-12`.
- Naming: key `sheet` / "Dot.Sheet" / host `sheets.`; key `docs` / "Dot.docs" / host `doc.`; key `press` / host `pres.`.
- Logos by key: `resources/img/apps/<key>.png`, `resources/img/landing/platforms/<key>.png`, `resources/img/platforms/{files,docs}.png`.
- Second registry `dot_platforms` (OIDC only: slug, domain, client id, status) is NOT joined to config anywhere; can drift.

## Launch and listing
- Single choke point: `App\Support\EcosystemHandoff::launchForm` / `launchUrl` (used by /go, switcher, Platforms page,
  Home doors, search, /open/{resource}, seats button). Today it checks only: not suspended, platform exists, active,
  trusted https host. No team, role or plan check.
- `/go/{platform}` refusal today = 503 "unavailable" view (would read as an outage if reused for entitlement).
- Redeem payload: user id/uuid/name/email, ALL teams (id, uuid, name, personal, role, infodot_role), current team. No
  entitlement field. OIDC carries no team at all.
- Platforms page = `EcosystemWidget` (visible minus infodot; button enabled if active). Analytics tab "Platform
  coverage" = same config list as live vs registered (`AnalyticsController.php:98`, `analytics/dashboard.blade.php:230-251`).

## Existing commercial code
- Seats only: `team_licences` (included_seats, purchased_seats, payment_status, payment_ref, grace_until) and
  append-only `team_licence_events`. `FinanceEntitlements` pulls `GET {finance}/api/entitlements/{team_uuid}`,
  HMAC, 3 s, cached 300 s, fails safe. Off by default (`licensing.finance_connected`). Cashier installed, unused.
- Policies: `viewLicence` owner|admin; `changeLicence` owner only. Personal teams unlicensed.
- Recommendation signals available: `teams.industry`, `team_profiles` (industry, category, products_services,
  location, regions, size, business_type), `business_categories` pivot, `business_services`.

## Sync and audit patterns to reuse
- Outbox tables (reward_outbox, participation_outbox, library_outcomes): unique event_id, attempts, next_attempt_at,
  sent_at, failed_at; `insertOrIgnore`; backoff 30 s … 6 h, 25 attempts; flushed by scheduler every minute.
- Membership webhook: HMAC over `ts.body` with the per-platform secret, POST `/api/ecosystem/membership`, best effort.
- Only elevated flag: `users.is_moderator`. No admin role. No generic audit table; `team_licence_events` is the model.

## Constraints
No queue worker; every outbound call 3-8 s and fail-safe; users.id INT UNSIGNED; SQLite tests + MySQL CI job;
guarded migrations; CSP self-only; public repo; demo teams excluded from Finance; registry tests pin keys/groups/logos.

## Decisions the code makes obvious
1. Entitlement per company (team uuid), never personal workspaces; owner changes, admin views.
2. Enforce inside `EcosystemHandoff` and again at redeem (and OIDC authorize), with its own refusal page, not the 503.
3. Push changes through a fourth outbox + the existing signed webhook; record in an append-only events table.
4. Payment stays pull-and-verify server to server.

## Open decisions (owner)
- Default for existing companies: keep what they have (grandfather) or start from nothing.
- Which platforms are free / included for every company (Files and Docs are built into navigation today).
- Who administers catalogue and pricing (today only "moderator" exists).
- Prices: none approved.
- `sso => false` platforms (Charts, Mines) cannot be enforced by InfoDot: they have their own sign-in.

## Risks
- Gating only /go is cosmetic: OIDC authorize, redeem and each platform's own login also need the check.
- A company locked out of Finance could not pay to get back in: Finance (seat/payment pages) must always be reachable.
- Fail-open vs fail-closed when the payment service is down: entitlement records live in InfoDot, so a Finance
  outage must not lock anyone out.

# R-19 — CSP hardening, HTTP methods, security disclosure (2026-10-30)

The owner's brief is verbatim in `requirements/infodot-csp-hardening.md`. Every finding was
investigated at its source, fixed where the stack allows, and regression-proved with the full test
suite (1,327 green) plus a real browser run against a local instance **with the new policy
enforced** — login, password toggle, the ask composer (idea buttons, @mention dropdown, uploader),
and the solution page's Livewire sections all exercised with zero application CSP violations.

## Finding statuses

### 1. `script-src 'unsafe-inline'` — **FIXED — PENDING EXTERNAL VERIFICATION**
Root cause: a blanket allowance covering four hand-written inline `<script>` blocks, eleven native
`on*=` handler attributes, and the framework asset tags. Remediation: a per-request nonce is
registered with Vite in the `SecurityHeaders` middleware (`Vite::useCspNonce()`), which flows to
every `@vite` tag automatically and to Livewire's asset tags (its `FrontendAssets` falls back to
`Vite::cspNonce()` natively — verified in vendor source and by test). The four inline scripts now
declare the nonce; one (a dead `toggleNavbar` helper nothing called) was deleted instead. All
eleven native handlers were converted to Alpine directives (`@click`/`@keydown`/`@submit` with the
same behaviour, incl. the confirm-before-submit forms), which are attributes Alpine evaluates, not
native handlers — so **`script-src-attr 'none'`** is also set, banning every `on*=` attribute
outright. `'unsafe-inline'` is gone from script-src. JSON-LD blocks need no nonce (non-executing
script type). `style-src` keeps `'unsafe-inline'`: the Yard views use style attributes throughout,
and the attack surface that matters — script injection — is covered; noted as accepted.

### 2. `script-src 'unsafe-eval'` — **MITIGATED / OUTSTANDING (stack constraint, documented)**
Root cause identified precisely: Livewire 3 bundles Alpine.js, whose expression evaluator uses the
`Function` constructor; Livewire has no supported CSP build (Alpine's own CSP build cannot be used
under Livewire 3). Every interactive surface of InfoDot is Livewire/Alpine, so removing
`'unsafe-eval'` means replacing the frontend stack — estimated as a multi-week rewrite touching
every view, not a configuration change. The brief's own rule applies: nothing was removed blindly
to leave broken functionality. Compensating controls now in place: nonce-only inline scripts,
`script-src-attr 'none'`, strict host allowlist, every user-generated string HTML-escaped (so
attacker markup cannot introduce Alpine attributes or scripts), and violation reporting. Revisit if
Livewire ships CSP support.

### 3. `object-src` — **FIXED — PENDING EXTERNAL VERIFICATION** — set to `'none'`; nothing uses
`<object>`/`<embed>` (PDFs preview via same-origin `<iframe>`, now pinned by `frame-src 'self'`).

### 4. Deprecated `report-uri` — **FIXED** — the modern pair is in place: a `Reporting-Endpoints:
csp="…/csp-report"` header plus `report-to csp` in the policy; `report-uri` is kept alongside for
older browsers (spec-sanctioned). The endpoint already parsed both report formats, is throttled,
accepts no session, and logs only the directive and blocked host — resource-exhaustion and
data-retention concerns were already covered by its tests.

### 5. HTTP OPTIONS returning 200 `Allow: GET,HEAD` — **FALSE POSITIVE — EVIDENCE PROVIDED**
Verified live: this is Laravel's router answering an OPTIONS request truthfully for the route's
real methods; it processes nothing and leaks nothing, and the Allow list is accurate (now pinned by
test). CORS was reviewed in the same pass and **tightened**: `allowed_origins` was `*` and is now
restricted to the app's own hosts (`api/*` is server-to-server HMAC; no browser calls it
cross-origin). State-changing routes all sit behind auth + CSRF (standing test coverage).

### 6. security.txt — **FIXED — PENDING EXTERNAL VERIFICATION**
`public/.well-known/security.txt` per RFC 9116: `Contact: mailto:privacy@infodot.co.za` (the real,
monitored address named on the legal pages — no fabricated contacts), `Expires:
2027-04-01T00:00:00.000Z`, `Preferred-Languages: en`, Canonical for apex and www. No Policy line:
a dedicated disclosure-policy page does not exist yet — recorded as an owner/business follow-up
rather than inventing one. Served three ways: the physical file, the deploy's docroot static
refresh (allowlist extended, with subdirectory support), and a Laravel route fallback. Expiry is
watched: `ops:check` now alerts daily from 30 days out (`security_txt_expiring_30d`).

### 7. Login interface on /solution/view/8 — **FALSE POSITIVE — EVIDENCE PROVIDED**
Verified live: a guest requesting that URL gets `302 → /login`; the scanner followed the redirect
and attributed the login form to the original URL. The auth gate is exactly the intended behaviour
(solution pages require sign-in; visibility inside is tenant-scoped). The access matrix the brief
asks for is standing test coverage: guests redirected, members see public content, company content
invisible cross-tenant both directions, hidden content restricted (R-15/R-16 suites). Brute-force,
enumeration-safe responses, session flags and reset flows: covered by Fortify config + the live
cookie evidence in R-18. Nothing was hidden to please the scanner.

## Regression evidence

Full suite 1,327 passed; PHPStan level 5 clean; Vite build clean. Browser run with the policy
ENFORCED locally: sign-in works (Livewire form), password visibility toggle works (it is Alpine,
not a native handler), idea buttons fill the title (converted handlers), the @mention dropdown
searches and renders, the attachment widget renders, the solution page's tried-it and improvements
sections work, improvement form opens. Zero CSP violations from application code; the only console
violation was local dev's plaintext `ws:` websocket, which production never uses (wss\:)— a
dev-only `ws:` allowance is added when `APP_ENV=local`.

## Post-deploy verification (one curl each)

- `Content-Security-Policy` has `'nonce-…'`, no `unsafe-inline` in script-src, `script-src-attr
  'none'`, `object-src 'none'`, `report-to csp`; `Reporting-Endpoints` present.
- `https://infodot.co.za/.well-known/security.txt` → 200, correct fields.
- Watch the daily log for `csp.violation` entries for a day or two; the kill-switch
  (`SECURITY_CSP_REPORT_ONLY=true`) reverts to report-only without a deploy if anything real shows.

## Rollback

Everything is config/middleware/view-level: revert the merge commit, or flip the CSP kill-switch
env for the policy alone. No migrations, no data changes, no host changes.

# Security assessment (baseline 2026-10-19)

Scope of this baseline: **dependency advisories only**, plus configuration facts already verified during the
13-platform deployment. No penetration test, no manual authorisation review of the 14 platforms outside that
work, no host hardening review and no secrets-in-history scan has been done. Absence of a finding below is not
a security claim.

## S-1 Dependency advisories (verified: `composer audit --locked`, `npm audit --package-lock-only --omit=dev`, default-branch lockfiles, 2026-10-19)

| Package | Advisory | Severity (feed) | Notes |
|---|---|---|---|
| guzzlehttp/guzzle (Pulse) | CVE-2026-69246 noncanonical host bypasses host checks | **high** | plus 5 medium cookie/redirect/proxy-header advisories (CVE-2026-69245, -67354, -67355, -67353, -67339) |
| livewire/livewire | CVE-2026-81887 DOM-based XSS in client-side state handling | medium | affects Livewire 3.x up to the fixed release; installed 3.8.2 in the sampled repo |
| laravel/framework | CVE-2026-102279 XSS in the debug page | low | the deploy pipelines force `APP_DEBUG=false`, which removes the exposure |
| league/flysystem | CVE-2026-102601 path-normaliser control-character issue | low | |
| symfony/http-foundation | CVE-2026-108082 ReDoS in `HeaderUtils::split()` | unrated | |
| symfony/yaml | CVE-2026-109413 unbounded recursion in inline lexer | unrated | exposure depends on parsing untrusted YAML |
| axios (npm, prod) | 1.0.0 to 1.19.0 range | **high** | Analytics, Files; `npm audit fix` available |

Repositories with advisories (17): Auction, Billing, Design, Dopemine, Ehail, Emall, Farms, Files, Finance, HR,
Memory, Notify, Plug, Projects, Pulse, Tasks, Tutor (4 to 5 advisories each; Pulse 10). Clean in this audit (9):
Agents, Analytics (composer), Central, Engage, Forms, Mines, Press, Sheet, docs. Dot.Charts' Laravel app is in `backend/` and was missed here at first; the later audit found 48
advisories across 15 packages, 14 high (see REMEDIATION_ROADMAP.md R-2). Dot.Brain (JavaScript) was not audited.

Remediation: `composer update` of the named packages (with dependencies), full suite per repository, PR, deploy.
Tracked as R-2 in the roadmap. No upgrade is applied to production without the suite and the deploy smoke check.

## S-2 Verified controls (from the 13-platform work, 2026-10-18)

- Production invariants forced by every deploy: `APP_DEBUG=false`, `APP_ENV=production`.
- Smoke check on every deploy: POST `/up` must answer 405; `/<key>/.env`, `artisan` and the SQLite file must not be
  downloadable (all 13 returned 403 on 2026-10-18).
- Signed server-to-server calls (HMAC-SHA256 over timestamp + body) for Notify, Analytics and Billing; unsigned
  Analytics ingest answers 401 (verified).
- Sign-in redeem refuses a wrong bearer (422) and platforms not marked active.
- Paystack code refuses `sk_live_` keys; webhook signatures and idempotency are tested in the repositories.
- Secrets live in a mode-600 local file and host `.env` files; they were not committed (GitGuardian passes on the
  InfoDot and Analytics PRs).

## S-2b Sign-in token in the URL (found by the owner 2026-10-20; FIXED and deployed 2026-10-20)

**Finding (high).** Switching platform sent the browser to `https://<app>/auth/ecosystem?token=<credential>&redirect=...`.
The single-use sign-in credential therefore sat in the address bar, browser history, server access logs and
Referer headers, and when the destination refused the sign-in (403/503) the person was left on a URL showing it.
Present in InfoDot and all 24 receiving platforms. The token was already audience-bound, single-use and short
lived (5 minutes), which limited but did not remove the exposure.

**Fix.** InfoDot opens a platform through its own `/go/{platform}` page, which mints a 60-second single-use token
and POSTs it in a form body. Receivers accept the POST (origin-checked, so a form on another site cannot sign a
visitor in as someone else), and any refused sign-in redirects (303) to the plain login page with a generic
message. Mines and Charts have their own sign-in and no receiver, so they now open at their own login and no token
is minted. PRs: InfoDot #54 and one "Sign-in token: accept a POST, never show it" PR per receiving platform.
Tests: `PlatformLaunchTest` (InfoDot) and `EcosystemSsoPostTest` (each platform) cover token-never-in-URL,
cross-site POST refused without spending the token, nothing echoed on failure, CSRF exclusion, exact-origin check.

**Rollout (done).** The 25 receivers were deployed one at a time, then InfoDot. A first receiver deploy exposed an HTTP 419
on the Laravel 13 apps (the new `PreventRequestForgery` middleware had to be excluded as well; unit tests skip CSRF, so the
test now runs in production mode); fixed and redeployed. Live checks on every receiver: a cross-site POST and a bad GET
both answer 303 to `/login`. **Not verified live:** a full signed-in click-through from InfoDot into a platform (needs a real
session); the owner should do one. A follow-up removes the legacy GET path from the receivers.

**Limits of this fix.** The token is in the response body of the launch page (hidden form field) for the signed-in
person for a moment, and in the POST body in transit (HTTPS). Access logs no longer record it. Old bookmarked or
already-sent links with `?token=` still work until the legacy GET is removed; they are spent on use or expire in
5 minutes.

## S-2c Application folders served over HTTP (found 2026-10-20 by a deploy smoke check; contained the same day)

**Finding (critical, pre-existing).** Dopemine, Engage, Memory and Sheet answered HTTP 200 for `/<key>/database/database.sqlite`
(real SQLite databases of 0.3 to 21 MB), `/<key>/storage/logs/laravel.log` (Engage 1.5 MB, Sheet 0.7 MB), `composer.json`,
`composer.lock`, `vendor/autoload.php` and `phpunit.xml`. `.env` was 403 on all. The newer platforms had a
`Require all denied` `.htaccess` in the app folder; these four never did. **Unknown: for how long, and whether anyone
downloaded them** (the owner must check the raw access logs). Treat the data as copied until that is shown otherwise:
sessions, API tokens, password resets and any secrets in the logs or databases need rotating, and a POPIA section 22
assessment is needed if personal information may have been accessed.

**Containment (done).** App Folder Guard (InfoDot `.github/workflows/app-folder-guard.yml`, `tools/probe-exposure.sh`)
wrote the deny file into the four folders; afterwards every probed path is 403 and the sites and built assets still load.
`tools/probe-exposure.sh` over all 27 platforms: nothing readable. **Open:** add the probe to every deploy pipeline, make
`prepare-host` and the older pipelines create the deny file, and move app folders out of the docroot.

Also seen: during one Tasks deploy the smoke check briefly saw `.env`, `artisan` and the SQLite file answer 200 (403 again on every
later probe); cause unknown, so a short exposure window on Tasks during that deploy cannot be ruled out.

## S-2d App-folder `.htaccess` not applied for a short window after a deploy (observed 2026-10-22; second layer built, not yet applied)

**Observed (facts).** On 2026-10-22, right after `artisan up` on the first deploy that touched the app-folder guard, the public
probe saw HTTP 200 for `/<key>/.env`, `artisan`, `composer.json`, `database/database.sqlite` and `vendor/autoload.php` on two
apps, Dopemine and Ehail, for a few seconds. On both, a minute later, and on every later deploy, the same paths answered 403.
A similar brief sighting during a Tasks deploy is recorded in S-2c. Whether anything requested those paths in those seconds is
unknown; the raw access logs for them are where to look.

**Suspected, not established.** The `Require all denied` `.htaccess` inside the app folder is sometimes not applied for a short
time after the directory tree changed (LiteSpeed per-directory `.htaccess` cache). We have no measurement of the mechanism.

**Second layer (built, not applied).** App Folder Guard modes `docroot-check` and `docroot` (`.github/workflows/app-folder-guard.yml`,
host side `tools/docroot-guard-host.sh`) maintain a delimited block (`# BEGIN dot-app-folder-guard <key>` to `# END ...`) at the
top of the DOCROOT's own `~/<host>.infodot.co.za/.htaccess`, a file deploys do not touch. It is one `mod_rewrite` rule,
`^<key>/(.env*, .git*, artisan, composer.json/lock, package(-lock).json, phpunit.xml*, database/, storage/, vendor/, bootstrap/, config/, tests/, node_modules/)`
answered with 403, matched case-insensitively and only directly under `/<key>/`; nothing else a route or asset could use.
`probe-exposure.sh` now takes `PROBE_RETRIES` and `PROBE_WAIT` and prints every attempt, so "exposed for N seconds" and "persistently
exposed" are told apart (any 200 still fails the probe). Tested against a temp docroot only (`tools/test-docroot-guard.sh`, CI job
"docroot guard (fake host)"). **Not verified:** how LiteSpeed applies the rule on the real host, and whether it closes the window.

**Owner steps, in order.**
1. Dispatch `App Folder Guard` with mode `docroot-check` (empty `apps` = all platforms) and read the table: per pair, whether the
   block would be written, replaced or is current, and the public probe timeline. Skipped lines mean the host label or app folder
   in the pair is wrong (Files is `files:dotfiles`, Press `pres:press`, Sheet `sheets:sheet`, Docs `doc:doc`).
2. Dispatch mode `docroot` with the pairs you accept (start with one, e.g. `dopemine:dopemine`). The run records `/`, `/up`, `/login`
   before, writes the block, compares after, and restores the previous docroot `.htaccess` (or removes the file if there was none)
   and fails if any of the three changed. A backup `.htaccess.before-docroot-guard` is kept on first change.
3. Repeat for the rest; then on the next deploy of an app, watch the post-deploy probe timeline for a 200.
4. Still open from S-2c: check the raw access logs for 200s on those paths, and move app folders out of the docroot.

## S-3 Not yet assessed (no evidence either way)

Tenant-isolation test coverage on the older platforms, MFA availability and enforcement, rate limiting on login and
API routes, security headers and CSP, file-upload handling beyond Design and Emall, backup encryption, log
redaction, admin/operator privilege boundaries on the older platforms, dependency review for Dot.Brain, WAF
behaviour of the hosting edge (a `waf-probe` workflow exists in Dot.Brain), and every item in the brief's sections
8.5 and 8.6 (SAST, SBOM, licence checks, incident procedures, disclosure policy).

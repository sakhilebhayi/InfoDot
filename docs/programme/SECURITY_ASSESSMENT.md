# Security assessment (baseline 2026-10-19, updated 2026-10-23)

Sections S-2b to S-2f were added after the baseline; each states its own date. S-1 and S-3 are as of 2026-10-19 except where a status line says otherwise.

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

**Status (2026-10-23):** every 2026-10-19 advisory in this table was fixed and deployed on 2026-10-19; newer npm advisories (vite, launch-editor,
follow-redirects) were cleared with lockfile-only `npm audit fix` in Central, Engage, Press, Sheet, docs and Charts; Forms #45 is open; Charts' dev-server-only
Vite advisories and Tailwind 3 transitive build-tool advisories need a major upgrade and are undecided. See REMEDIATION_ROADMAP.md R-2.

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

**Follow-up (done 2026-10-22 to 2026-10-23).** The legacy `GET /auth/ecosystem?token=` path was removed from all 25 receivers (one "Remove the legacy GET
?token= sign-in path" PR each, merged and deployed): a GET now redirects to `/login` without reading the token, which is read only from the POST body.
Verified 2026-10-23: a GET with a dummy token answered 302 to `/login` on every one of the 25 hosts (`curl -I`). The code-level claim that the token is
not read comes from the PRs and their tests, not from the black-box check. Not verified live: a full signed-in click-through (owner step).

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

## S-2d App-folder `.htaccess` not applied for a short window after a deploy (observed 2026-10-22 and 2026-10-23; second layer applied 2026-10-23 and did not prevent it)

**Observed (facts).** On 2026-10-22, right after `artisan up` on the first deploy that touched the app-folder guard, the public
probe saw HTTP 200 for `/<key>/.env`, `artisan`, `composer.json`, `database/database.sqlite` and `vendor/autoload.php` on two
apps, Dopemine and Ehail, for a few seconds. On both, a minute later, and on every later deploy, the same paths answered 403.
A similar brief sighting during a Tasks deploy is recorded in S-2c. Whether anything requested those paths in those seconds is
unknown; the raw access logs for them are where to look.

**Suspected, not established.** The `Require all denied` `.htaccess` inside the app folder is sometimes not applied for a short
time after the directory tree changed (LiteSpeed per-directory `.htaccess` cache). We have no measurement of the mechanism.

**Observed again 2026-10-23 (facts from the deploy runs).** The post-deploy probe found the app folder answering HTTP 200 right after a deploy
on Tasks (16:44 UTC, smoke check: `.env`, `artisan`, `database/database.sqlite`), then, after the docroot rule below had been written on every
platform, on Finance (17:54 UTC), HR (18:16) and Pulse (18:28): nine sensitive paths each (including `.env`, `composer.json`, `database/database.sqlite`,
`storage/logs/laravel.log`, `vendor/autoload.php`) answered 200 during one probe pass of about 5 to 6 seconds. Those three deploys had finished every
earlier step, including the smoke check, and failed only at the final probe step, so they are red in Actions although the code was in place. The deploy
probe makes one pass and does not retry, so **how long the paths stayed readable is not known**, and whether anything requested them is unknown (raw
access logs). No later probe of those three sites is recorded in the workflow history; their current state was **not** re-probed by this update. Earlier
sightings: Dopemine and Ehail on 2026-10-22 (above). Treat the production `.env` of the apps seen exposed (Dopemine, Ehail, Tasks, Finance, HR, Pulse) as
possibly read until the access logs show otherwise: that is the owner's rotation decision (S-2f lists the open owner actions).

**Second layer (applied 2026-10-23; did not prevent the window).** Dispatched by the owner: `docroot-check` (run 39990697168, 16:54 UTC); a first `docroot` run
failed before any write because of a host-list read bug (run 39991231839, fixed in InfoDot #75); `docroot` on Dopemine alone (run 39991992409, 17:04); then on all 25
platforms (run 39992191690, 17:05 to 17:11). Each run recorded `/`, `/up` and `/login` before and after, saw no sensitive path readable, and reported "the sites
answer exactly what they answered before". The Finance, HR and Pulse sightings above came after that, so the block did not close the post-deploy window.
The text that follows was written before it was applied.

**Second layer (built, as designed before it was applied).** App Folder Guard modes `docroot-check` and `docroot` (`.github/workflows/app-folder-guard.yml`,
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
   `refs-outside-block=N` means N other lines in that docroot `.htaccess` mention `/<key>/`; read them on the host before applying,
   because the new rule sits above them and would also catch an internal rewrite that lands on one of the listed names
   (for example a router mapping `/storage/...` to `<key>/storage/app/public/...`). `/`, `/up` and `/login` would not show that.
2. Dispatch mode `docroot` with the pairs you accept (start with one, e.g. `dopemine:dopemine`). The run records `/`, `/up`, `/login`
   before, writes the block, compares after, and restores the previous docroot `.htaccess` (or removes the file if there was none)
   and fails if any of the three changed. A backup `.htaccess.before-docroot-guard` is kept on first change.
3. Repeat for the rest; then on the next deploy of an app, watch the post-deploy probe timeline for a 200.
4. Still open from S-2c: check the raw access logs for 200s on those paths, and move app folders out of the docroot.

## S-2e Document-root move: inventory (built; run 2026-10-23)

**Plan (the owner moves each subdomain's document root; this section only prepares it).**
1. Today the docroot is `~/<host>.infodot.co.za/` (an `index.php` shim, a copy of Laravel's `.htaccess`, symlinks such as `build -> <key>/public/build`) and the app folder `<key>/` sits inside it, so `.env`, databases, `vendor/` and logs are under the web root and rely on `Require all denied` files (S-2c, S-2d).
2. Target: the docroot is `~/<host>.infodot.co.za/<key>/public` (Laravel's standard layout). The app folder path does not change, so cron lines that use it keep working.
3. Phase 1, this inventory (read-only): per app, what the move depends on. Phase 2: move one low-risk app, compare `/`, `/up`, `/login` and the assets before and after. Phase 3: the rest, one at a time, then retire the docroot shim, the docroot `.htaccess` copy and the `dot-app-folder-guard` blocks.
4. Rollback per app: set the document root back to `~/<host>.infodot.co.za/` in cPanel (Domains). The old shim, `.htaccess` and symlinks stay in place until Phase 3.
5. The inventory is mode `docroot-inventory` of `App Folder Guard`: host side `tools/docroot-inventory-host.sh`, tests `tools/test-docroot-inventory.sh` (CI job "docroot inventory (fake host, read-only)").

**What it reports per pair** (names, sizes, modes and counts only; no file contents, no env values, no crontab lines): layout (`standard`, `renamed-folder`, `outside-docroot`, `docroot-is-app`, none; Projects is reported, not failed), the docroot top-level entries with symlink targets, the `public/` facts (`index.php`, `.htaccess`, `build`, `storage`, `images`, favicons, `robots.txt`), the `index.php` shim as `standard` or `custom`, the `.htaccess` directive names of the docroot and of `public/` with counts and a names-only difference, `.user.ini`/`php.ini`, crontab line counts, `public/` size and the file count under `public/storage`, uploads served through a symlink, and a verdict `READY`, `NEEDS-ATTENTION(<reasons>)` or `UNKNOWN-LAYOUT(<reason>)`. A `NEEDS-ATTENTION` is expected where cPanel's MultiPHP handler block or extra `Header` lines live only in the docroot `.htaccess`: those would stop applying after the move unless copied into the new docroot's `.htaccess`.

**Read-only guarantee.** The host script writes, creates, removes, renames and changes the mode of nothing, takes no lock, and reads the shared crontab with `crontab -l` only (once per run, retried when it answers empty; an empty answer is reported as `unknown`, never as 0 lines). The test greps the script for write operations and redirections, compares a checksum of the whole temp HOME (names, modes, mtimes, contents, link targets) before and after, runs it on a HOME with all write permission removed, and fails if the `crontab` stub is called with anything but `-l`. **Not verified:** behaviour on the real host (cPanel/LiteSpeed, the real `crontab`, GNU `awk` flavour there).

**Owner step (done).** Dispatch `App Folder Guard` with mode `docroot-inventory` and empty `apps` (all platforms), open the job summary table and the reasons list, and keep the raw output (artifact `docroot-inventory`, 30 days). Nothing is changed on the host by this run.

**Shim report (added 2026-10-23, `ops/inventory-shim-diff`).** The first run reported `custom-shim` for Forms, Files, Agents, Engage, Press, Projects, Sheet and docs. Once the document root is `<key>/public`, the docroot `index.php` is no longer used and `public/index.php` runs alone, so whatever a custom shim does (a maintenance include, `ini_set`, `putenv`, `define`, `header`, `$_SERVER` changes, another app path, extra requires) would be lost silently. For every pair whose shim is `custom` the inventory now also prints a sanitised classification: `shim_extra:` lines (line count and byte size, then one line per statement that is not the standard `<?php` or the standard require, at most 40 per pair), the flags `shim_requires_public_index`, `shim_requires_other`, `shim_sets_ini_or_env`, `shim_sends_headers`, `shim_has_maintenance_include`, `shim_app_path_differs`, `shim_defines`, `shim_other_code`, and a verdict `SHIM-SAFE-TO-DROP` (equivalent to the standard shim apart from comments, whitespace and the maintenance include, and `public/index.php` already has the maintenance check) or `SHIM-DOES-SOMETHING(<flags>)`. A statement is shown by its construct or function name only (`ini_set`, `header`, `$_SERVER[...] assignment`); for `require`/`include` the relative path is shown with the home directory as `~`, the query string cut and any key/secret/password/token-like, long hex or base64-like piece replaced by `<redacted>`. String literals, comments and array contents are never printed. The mode stays read-only (same static no-write test and the same before/after checksum of the whole temp HOME); the job summary has a per-app shim column plus a flags table and the statement list. **Owner step:** re-dispatch `docroot-inventory` after merging, read the shim table, and move a pair only once its shim is `SHIM-SAFE-TO-DROP` or each flag is accounted for in `public/index.php`. **Not verified:** the classification on the real shims (it is tested only on a fake host).

**Result (run 40005518270, 2026-10-23 18:42 UTC, 25 pairs): READY=2, NEEDS-ATTENTION=23, UNKNOWN-LAYOUT=0.** Only Dopemine and Memory are READY. Reasons reported:
- `.user.ini` and `php.ini` sit in the docroot, not in `public/`, and would stop applying after the move: Analytics, Billing, Farms, HR, Notify, Plug, Pulse.
- `public/storage` is a broken link: Forms, Agents, Engage, Press, Sheet, docs.
- A custom `index.php` shim, not the standard one: Files, Forms, Agents, Engage, Press, Projects, Sheet, docs.
- Projects' layout differs (the app folder is `main`, not `projects`).
- Tasks: docroot `.htaccess` directives and the authorization-header passthrough are not in `public/`, and `public/.htaccess` was missing (Dot.Tasks #26, merged 2026-10-23, tracks `public/.htaccess`).
- Files other than the app in the docroot (source archives, `LICENSE`, `index.html`, `styles.css`, logos, `hot`, `index.coming-soon.bak`) on most of the others; the archives are S-2f.
No document root has been moved. The plan above is unchanged; each `NEEDS-ATTENTION` reason has to be resolved or accepted per app first.

## S-2f Source and build archives in public web roots (found 2026-10-23 by the inventory; moved by the owner the same day)

**Finding (high, pre-existing).** The S-2e inventory listed 14 archive files at the top of public web roots, each served by the web server as a static file: five tarballs (Forms `Dot.Forms.tar.gz`, 513 MB; Engage
`Dot.Engage.tar.gz`, 218 MB; Agents `dot-agents-full.tar.gz`, 53 MB; Sheet `Dot.Sheet-all-files-built-2026-03-30.tar.gz`, 49.5 MB; Press `dotpress_latest.tar.gz`, 0.6 MB) and nine small GitHub source zips (`Dot.<Name>-main.zip`, 28 KB to 276 KB:
Tasks, Auction, Central, Design, Ehail, Emall, Finance, Projects, Tutor). The Sheet name says "all files built", so that archive may hold a complete build. **What is inside any of them has not been inspected by this programme**, and whether any was downloaded is unknown.

**Action taken (owner-reported, not re-verified by this update).** On 2026-10-23 the owner moved the archives to a private quarantine folder outside the web roots and confirmed from outside that they now answer 404. This update did not re-probe the archive URLs. Count to reconcile: the owner's note says 13 archives (8 small zips); the inventory log lists 14 (9 small zips). The difference is not explained here.

**Open owner actions.**
1. List each archive's contents (file names only) to learn what could have been downloaded: `.env` files, databases, `vendor/`, keys.
2. Review the raw web-server access logs for requests to those archive names, and for the paths in S-2c and S-2d.
3. Rotate every secret that was inside an archive or an exposed `.env`, including `APP_KEY` (care: encrypted data and sessions depend on it), plus sessions and API tokens.
4. Make the POPIA section 22 assessment if personal information may have been accessed.
5. Add a check to the inventory or the deploy probe so an archive in a docroot fails the run.


## S-2g Registration closed and read-only role (2026-10-21 to 2026-10-23)

Public registration is closed on the 25 platform apps: `ALLOW_REGISTRATION` defaults to false and `/register` redirects to `/login` (accounts come from InfoDot). Dopemine first (#28,
2026-10-21), Memory next (#34), then the rest on 2026-10-23. **Verified live 2026-10-23** (`curl -I https://<host>.infodot.co.za/register`): 302 to `/login` on agents, analytics,
auction, billing, central, design, ehail, emall, engage, farms, files, finance, forms, hr, memory, notify, plug, pres, projects, pulse, sheets, tasks, tutor and doc (24 hosts); 404 on
dopemine. Not one of the 25 answered anything else. Outside the 25: `charts` answers 404 and **`mines` answers 200 on `/register`** (Mines has its own sign-in and is not one of
the 25 receivers; whether its registration should be closed is undecided). Host names: the Docs app is `doc`, Press is `pres`, Sheet is `sheets`; `brain.infodot.co.za` does not resolve (Dot.Brain has no host).

A real read-only `viewer` role was added and enforced on every write path in Agents (#16), Engage (#46), Press (#48), Projects (#22) and Sheet (#54, a team role); Forms already had one; Central
maps InfoDot's viewer to its read-only manager role. Agents also maps InfoDot companies to organisations (#17).

## S-3 Not yet assessed (no evidence either way)

Tenant-isolation test coverage on the older platforms, MFA availability and enforcement, rate limiting on login and
API routes, security headers and CSP, file-upload handling beyond Design and Emall, backup encryption, log
redaction, admin/operator privilege boundaries on the older platforms, dependency review for Dot.Brain, WAF
behaviour of the hosting edge (a `waf-probe` workflow exists in Dot.Brain), and every item in the brief's sections
8.5 and 8.6 (SAST, SBOM, licence checks, incident procedures, disclosure policy).

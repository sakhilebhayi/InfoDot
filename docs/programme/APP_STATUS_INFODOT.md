# InfoDot: item-by-item status (one app at a time)

Started 2026-10-28 at the owner's request: apply the whole programme list to one platform until it is done, then move on. Each line
says **done** (with the evidence), **waiting on the owner**, or **decided not to do** (with the reason). Nothing here is claimed without
a check named in the line. InfoDot lives at `~/public_html/main` (docroot `~/public_html`), repository `sakhilebhayi/InfoDot`, default
branch `main`, deployed only by dispatch.

## A. Security and exposure

| Item | Status | Evidence |
|---|---|---|
| Laravel/Livewire/Symfony security advisories (1 critical, 2 high) | **done and deployed** | InfoDot #103; deploy run 40582727103 (2026-10-27 ~21:00 UTC); `composer audit --locked`: no advisories |
| npm advisories (5 high, dev/build tree) | **done, not yet deployed** | #106 (lock only); production npm tree was already clean; 2 moderate left (`@tailwindcss/typography`, needs a semver-major change) |
| App folder `~/public_html/main` served (log, artisan, composer, vendor) | **done** | allow-list rule at the top of `~/public_html/.htaccess` (`^main/(?!public/)` refused); outside probes 403; deploy now probes `/main/...` after every release (#93); S-2h |
| Loose archives in the web root | **done** (moved); contents not yet reviewed | `InfoDot-main.zip` and `.well-known.zip` in `~/quarantine-infodot-docroot/`; 404 from outside. **Owner:** list names (commands in the checklist), then delete |
| Secrets possibly exposed by the unexplained 2026-10-05 `/.env` answer | **done (precaution)** | DB password, mailbox password and `APP_KEY` rotated 2026-10-27; sessions cleared; handoff checked |
| `.DS_Store` files (served at `/.DS_Store`, `/img/.DS_Store`) | repo **done**, host copies **waiting on the owner** | #108 removes the tracked files and ignores them; the copies in `~/public_html` are removed by hand (command below) |
| Primary document root cannot move to `main/public` (plan limit) | **waiting on the owner** | ask the host; until then the allow-list rule is the control |
| Access-log re-check | **later** | re-run `access-log-review.yml` with `since=2026-10-28` in about a week |

Host command for the `.DS_Store` copies and old docroot leftovers that are safe to remove (they are not part of the live site):
`rm -f ~/public_html/.DS_Store ~/public_html/img/.DS_Store ~/public_html/css/.DS_Store ~/public_html/js/.DS_Store`

## B. Backups and reliability

| Item | Status | Evidence |
|---|---|---|
| Backed up nightly (02:40), verified, copied off-host (Mac, 04:15) | **done** | inventory 2026-10-28 14:56 UTC: infodot archive `20261028-024015`, age 12 h, 26 apps, 0 flagged |
| Restore proven | **done** | `restore-test key=infodot`: 0.50 s, `dump_footer=ok`, database 79,043 bytes, env present |
| `/up` checks the database | **done and deployed** | #91 (merged 2026-10-27), deployed with run 40582727103; POST `/up` 405 |
| Scheduler/cron | **done** | Cron Runner status 2026-10-28 14:55 UTC: `schedule=26 queue=18 failed=0`, InfoDot's `schedule:run` took 6 s; `.dot-cron.d/infodot.cron` present |
| Flaky CI test (random user name containing "rank") | **done** | #105; six local runs pass |
| Fleet Dependency Audit did not include InfoDot | **done** | #107 |
| Weekly Dependency Audit red | not InfoDot | caused by Mines (phpspreadsheet, #648 open) and Analytics (1 prod npm advisory) |

## C. Quality and brand (Brand Feeling Engine, profile `infodot`, analysis of 2026-10-28)

| Check | Count | Status |
|---|---|---|
| a11y labels (`d8`) | 6 to 2 | **done** (#109): contact form ids, home search label. The 2 left are false positives (a dynamic `role="{{ ... }}"` read as `" "`) |
| Heading structure (`d3`) | 3 to 1 | **done** (#109). The 1 left is Jetstream's 2FA partial |
| Route inventory vs claims (`d5`) | 16 | **done** (#110): each claim checked against code; 4 were wrong or overstated and are corrected (real-time notifications, social graph, likes, Pusher/Echo); the other 12 are real features named differently (engine keyword false positives) |
| Colour tokens (`d9`) | 10 | **decided not to change blindly.** `#434656` sits in four legacy Tailwind views (`livewire/question`, `solution-list`, `question-list`, `notifications/index`) that no route or view renders (only tests reference them) and use a different palette from The Yard: tokenising them would change their look, deleting them is the real fix. `var(--tone)` is set inline in the views (engine false positive). `#f6f4ee`/`#f1f3f3` are two intentional inks. **Owner decision:** delete the four dead legacy views and their tests? |
| Honest states (`d6`, all info) | 26 | 15 are forms and partials, not lists. Real list screens without an empty state: `search_results` (legacy page with social links and a third-party placeholder image), `people`, `api-token-manager`. **Owner decision** with the legacy-page redesign (R-10) |
| Font deny list (`d2`) | 1 | **waiting on the owner:** "Inter" via Google Fonts in `layouts/guest`; part of the self-host decision below |
| Spacing/type scale (`d4`) | 2 info | the stylesheet declares no spacing or type scale tokens: a design-system task, not a defect |

## D. Privacy (POPIA) for InfoDot

| Item | Status |
|---|---|
| Inventory | InfoDot: 34 tables, no special-personal-information columns by name, 0 columns cast encrypted (`POPIA_INVENTORY.md`) |
| Third-party loads | Google Fonts (guest, app, welcome, error layouts), Font Awesome via cdnjs and Tailwind via unpkg (contact page), apexcharts via jsDelivr and Gravatar (analytics dashboard: an MD5 of the email goes to Gravatar), Unsplash images (home hero, authentication card, solution list), a placeholder image from cdn.tuk.dev (search results). **Owner decision** (POPIA_READINESS section 4, item 6): self-host, or accept and disclose |
| Mail templates | the Laravel logo is only used when `APP_NAME` is "Laravel"; production sends the app name as text, so nothing third-party loads in emails |
| Privacy policy text | names "BluePin Inc" as responsible party (spelling vs BluPin is an owner question) |
| Consent for interaction tracking (R-12) | not started |

## E. Deploy

Merged after the last deploy and **waiting for the next InfoDot deploy**: #105 (test), #106 (npm lock), #107 (audit script), #108 (.DS_Store), #109 (a11y), #110 (docs). None changes behaviour except #109 (labels, one h1/h2, ids) and #106 (build tooling). Needs the owner's authorisation.

## F. Later, in order

R-12 User Interaction Intelligence (InfoDot first, behind a flag), R-9 remaining increments (acceptance run, expertise areas, non-email notifications), R-10 landing page last.

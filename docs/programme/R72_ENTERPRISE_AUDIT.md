# R-72: enterprise readiness audit — Phase 1 baseline and remediation plan

Brief: `R72_BRIEF.md` (owner, 2026-11-26, verbatim; reusable copy for every Dot platform at `~/Dot/briefs/ENTERPRISE_READINESS_AUDIT.md`).
Audited: `main` at `40106a7` (production runs `1f382bf`; the difference is the cookie banner and a hero label, both merged, not deployed).
Method: five read-only audits run in parallel (security and tenancy; email and notifications; performance, database and code quality;
operations and CI; functional inventory), the local Playwright suite (desktop Chromium, axe-core), direct checks of the live site,
DNS and WHOIS, and spot-checks of every High finding against the code. Nothing was changed in production and no real data was touched.
The earlier programme (`enterprise/`, 2026-10-30, 78/100 on a different model) is the history; this report re-measures from today's code.

## 1. Measured baseline

| Measure | Value | Source |
| --- | --- | --- |
| Size | 374 routes, 96 controllers, 19 Livewire components, 115 models, 152 services, 123 migrations, 324 views, 53k lines of PHP | `route:list`, file counts |
| Feature/unit tests | 3,126 tests, 3,113 pass, 9 incomplete, 4 skipped, 215 s, on PHP 8.4 + SQLite | latest deploy gate (run 45792189454) |
| Browser tests (local only, not in CI) | desktop Chromium: **22 passed, 8 failed, 6 not run** | `npm run test:browser`, 2026-11-26 |
| Static analysis | PHPStan level 5: 0 new errors; baseline 134 ignored errors (cannot grow) | `phpstan analyse` |
| Dependencies | `npm audit`: 0. `composer audit`: **47 advisories, all with severity "None"**, so the CI gate (High/Critical only) passes them | `composer audit --format=json` |
| CI on `main` | 55 of last 60 runs passed; 11 required checks, admins included; 1 flaky test observed | `gh run list`, branch protection API |
| Live site | TLS 1.3, HSTS 180 days, nonce CSP, nosniff, frame-ancestors, secure/httponly/lax session cookie, no debug leakage, `/up` and `/ready` 200, `security.txt` valid | `curl` |
| Domain | renewed: registry expiry **2027-11-27** | `whois.registry.net.za` |
| Mail DNS | SPF present (`~all`), DKIM `default` present, DMARC present (`p=none`, no reporting address) | `dig` against all four authoritative servers |

## 2. Initial scorecard (brief's weights)

| Area | Weight | Initial | Basis |
| --- | ---: | ---: | --- |
| Security and data protection | 20% | **7.5** | No Critical/High. Tenant isolation held on every company route traced; OIDC, redirects, uploads and server-side fetch are sound. Lost on the dependency gate (S-01), no step-up for admins (S-02), password policy (S-03), enumeration, throttles and sign-in alerts (S-04–S-06). |
| Functional correctness and regression stability | 15% | **8.0** | Static integrity complete (no broken routes, handlers, components or links; all 69 public links and 15 sitemap URLs answer). Lost on a dead end in licence management (F-01), browser journeys failing (B-01..B-03) and a 2FA test gap. |
| UX/UI quality and usability | 15% | **7.0** (partially verified) | Public pages and sign-in were reworked and checked this week; the signed-in application has not had a full rendered pass at three widths yet. |
| Accessibility | 10% | **6.5** (partially verified) | R-64 screens: 0 contrast findings, labels, one h1, reduced motion. The public/sign-up axe scan did not complete (B-01), the Dot Apps menu does not close with Escape (B-02), browser accessibility tests are outside CI, and mail links fail contrast (E-13). |
| Email and notification reliability | 10% | **6.5** | Careful design (database-level dedupe, unsuppressible security mail, preferences, digest, unsubscribe, a test per mail class). Lost on security and business notices never emailed (E-03), missing standard mails (E-04), user text injected into mail (E-05), all mail sent inside requests (E-06), failures not reaching alerts (E-09). Delivery itself not yet proven by a live send. |
| Performance and scalability | 10% | **5.0** | Writes are locked and serialised correctly; but the busiest public pages scan and sort in PHP (P-20..P-23), every signed-in page runs uncached counts (P-25), file cache on shared hosting (P-29), and **no response time has ever been measured**. |
| Code quality and architecture | 10% | **7.0** | Consistent service layer, PHPStan gate, ~94% line coverage, no dead routes. Lost on one policy and inline authorization/validation everywhere (Q-20, Q-21), several 500–1,000-line classes (Q-23). |
| Infrastructure and operational reliability | 10% | **6.0** | Gated, self-verifying deploy with rollback copy; encrypted nightly backups verified before each deploy; heartbeat, `/ready`, outside uptime probe. Lost on the MySQL restore never drilled (O-01), alerts and Sentry not reaching a person (O-02), deploy takes no automatic database snapshot (O-03). |
| **Weighted total** | 100% | **6.9 / 10** | |

No Critical issue was found. The score is held down by breadth (email, performance, operations), not by a single failure.

## 3. Findings register

Severity per the brief. Owner = needs the owner's access, credentials, DNS, money or a decision.

### Security and data protection
| ID | Sev | Finding | Evidence |
| --- | --- | --- | --- |
| S-01 | Medium | 47 advisories pass CI because they carry no severity (symfony/mailer STARTTLS, symfony/routing, http-kernel, serializer; webauthn/cbor/pki via fortify passkeys) | `composer audit`; `tools/ci-composer-audit.sh` blocks High/Critical only |
| S-02 | Medium | Platform admins and moderators need only a password session; no two-factor or step-up | `EnsurePlatformAdmin.php:13`, `EnsureModerator` |
| S-03 | Medium | Passwords: 8 characters minimum, no breached-password check | `PasswordValidationRules.php:16` (Fortify default) |
| S-04 | Low | Forgot-password and register reveal whether an account exists | `lang/en/passwords.php:20`, `CreateNewUser.php:38` |
| S-05 | Low | No throttle on `user/confirm-password` and `PUT user/password` | route list |
| S-06 | Low | No email on a sign-in from a new device or address | `AppServiceProvider.php:133` |
| S-07 | Low | CSP `connect-src`/`form-action` allow any https origin; HSTS 180 days without subdomains | live headers |
| S-08 | Low | Ecosystem launch-code redeem is not atomic (two concurrent redeems by the audience platform) | `EcosystemRedeemController` |
| S-09 | Low | Carried: wide `$fillable` (A-13), meeting-link redirect (B-12), HMAC not covering method/path (B-14), committed Pusher secret not compared with live (B-01, owner) | `enterprise/FINDINGS.md` |
| F-02 | Low | Anyone can inflate a business's insight counters without signing in (cookie-less clients skip the per-session limit) | `routes/web.php:109`, `BusinessInsights.php:29` |
| F-03 | Low | Unused Cashier would expose `/stripe/webhook` if a Stripe secret were ever set | `composer.json`, `AppServiceProvider.php:76` |
| E-05 | Medium | Company names, subjects and customer text go into Markdown email unescaped: a link can be injected into mail sent from InfoDot's domain | `team-invitation.blade.php:2`, `EnquiryActivity.php:39`, `LicenceNotice.php:28` |
| E-07 | Medium | Changing the account email needs no password and takes effect at once; resets then go to the new address | `UpdateUserProfileInformation.php:24` |
| E-08 | Low | The daily digest can show a private question's title to someone who has left that company | `NoticeDigests.php:66` |

### Functional correctness
| ID | Sev | Finding | Evidence |
| --- | --- | --- | --- |
| B-01 | High (test) | Browser journey A and the public/sign-up accessibility scan fail at "Verify your email": the heading became "One last step" in R-67, and the specs were not updated, so the sign-up accessibility scan has not run since | `a-new-user.spec.ts:157`; run 2026-11-26 |
| B-02 | Medium | The Dot Apps menu does not close with Escape or return focus (keyboard users are trapped in it) | `i-keyboard.spec.ts:179` |
| B-03 | Medium (test) | Platform access and catalogue journeys (K, L) look for "Switch on Dot.Forms" links whose labels changed | `l-platform-catalogue.spec.ts:50` |
| F-01 | Medium | "Reduce at renewal" and "Stop renewing" lead to a page with no way to submit ("cannot be done online yet") | `teams/_seats.blade.php:89` |
| F-04 | Low | Business size is free text, so the directory's Size filter will fill with inconsistent values | `business/edit.blade.php:148` |
| F-05 | Low | Homepage library links send visitors straight to sign-in without saying so | live `/solution/view/43` → 302 |
| F-06 | Low | No HTTP test of the two-factor sign-in challenge (TOTP and recovery code) | `tests/Feature` |
| O-05 | Medium | Flaky test: `ModerationApprovalTest` failed then passed on the same commit | runs 45688966357 / 45689261087 |

### Email and notifications
| ID | Sev | Finding | Evidence |
| --- | --- | --- | --- |
| E-03 | High | Security and business notices (identity alerts, ownership, standing, verification outcomes) are never emailed, though the settings page says security warnings are "always emailed at once" | only `BookingNotifier` and `EnquiryService` call `instantEmail()` |
| E-04 | Medium | Missing mails: new sign-in, role changed, account deleted, company deleted, suspension, welcome | `SendMembershipChange.php:26`, `DeleteUser.php` |
| E-06 | Medium | Production sends every email inside the web request (`QUEUE_CONNECTION=sync`); booking mail loops over staff | `.env.example:22`, `Kernel.php:58` (worker scheduled but unused) |
| E-09 | Medium | Mail failures are logged at warning, below what the ops alert counts; `failed_jobs` not monitored; alerts use the same SMTP | `AccountMail.php:56`, `ErrorLog.php:14` |
| E-01 | Medium | DMARC is `p=none` with no reporting address; SPF is softfail (`~all`) | `dig` (the audit agent's "records missing" was wrong: all four name servers return DKIM and DMARC) |
| E-10/11 | Low | Some failures log exception messages that contain addresses; a verification code is in a subject line | `InviteTeamMember.php:63`, `VerificationEmailCode.php:23` |
| E-12 | Low | Enquiry mail goes to unverified addresses; one failed licence recipient re-sends to all | `EnquiryService.php:404`, `LicenceReminders` |
| E-13 | Low | Email link colour 2.28:1 contrast; framework mails keep default wording; invitation shows "Create Account" before "Accept"; instant mails have no settings link | `vendor/mail/html/themes/default.css:39` |
| E-14 | Low | `.env.example` mail sender and APP_URL drift; seminar reminder times in UTC | `.env.example:40` |

### Performance and scalability
| ID | Sev | Finding | Evidence |
| --- | --- | --- | --- |
| P-20 | High | Directory loads up to 600 businesses with no order, then sorts and pages in PHP: wrong results and empty pages beyond 600 | `BusinessDirectory.php:93` (`CANDIDATES = 600`) |
| P-21 | High | `/businesses` runs search, browse, places and sizes on every visit, uncached even for guests | `BusinessDirectoryController.php:42` |
| P-22 | High | Directory search ORs `LIKE '%w%'` across four tables per word | `BusinessDirectory.php:76` |
| P-23 | High | Header search scans `LIKE '%q%'` on long text per keystroke burst and fans out to other platforms, though FULLTEXT and Scout exist | `Livewire/Search.php:126`, `PlatformSearch.php:37` |
| P-24..P-28 | Medium | Notices counts in PHP; uncached shell counts on every signed-in page; a count query inside nested loops on answers; no lazy-loading guard; places grouped in PHP | `InAppNotices.php:104`, `navigation-menu.blade.php:48`, `answers.blade.php:35` |
| P-29 | Medium | File cache on shared hosting: expired files never deleted, increments not atomic | `config/cache.php:18` |
| P-30 | Medium | Five minute-jobs in one pass with no time budget under a 115 s kill; a killed job is silenced 30 min | `Kernel.php:31-80` |
| P-31/32/33 | Medium/Low | Inbox stats in PHP and missing indexes; duplicate guard outside the transaction; unescaped LIKE wildcards | `EnquiryService.php:57,296` |
| P-34 | Low | 200 KB app.js always ships Alpine/Echo; 4.3 MB of large JPG/PNG; 40 images without lazy loading | `public/build` |
| M-01 | High | No response time has ever been measured at any data size | scorecard history |

### Code quality
| ID | Sev | Finding |
| --- | --- | --- |
| Q-20 | Medium | 110 inline validations; the only two FormRequests are unused |
| Q-21 | Medium | One policy; 35 inline 403 checks repeated across controllers |
| Q-22/23/24 | Low | Duplicated booking time parsing; 500–1,071-line services and 100+ line methods; three unreferenced classes |
| Q-27 | Low | PHPStan level 5 with 134 baselined errors that have not shrunk |

### Operations and CI
| ID | Sev | Finding | Owner |
| --- | --- | --- | --- |
| O-01 | High | The production MySQL restore has never been drilled; no RTO | **Owner**: create a scratch database in cPanel, then the runbook |
| O-02 | High | `/ready` is not watched from outside; Sentry DSN and the ops alert mailbox unverified; deploy does not tag releases | **Owner**: Sentry project, DSN and `OPS_ALERT_ADDRESS` in the host `.env` |
| O-03 | Medium | Deploy takes no database snapshot itself (relies on a manual backup first); rollback undrilled | Partly owner (drill) |
| O-04 | Medium | Smoke check omits `/ready`; a failed smoke leaves the release live; a failed maintenance-mode entry is ignored | |
| O-06/07 | Medium | Browser and accessibility tests not in CI; coverage measured but not gated | |
| O-08 | Medium | Weekly dependency audit red for six weeks because of other repositories; no Dependabot config | |
| O-09 | Medium | Only off-host copy is a pull to one laptop; BACKUPS.md contradicts itself; passphrase copy not evidenced | **Owner**: bucket, password manager |
| O-10..O-15 | Low | CI only PHP 8.4/SQLite; host resets to `origin/main` not the tested SHA; `LOG_LEVEL=debug` default; HSTS scope; stale deployment guide and incident roles; no GitHub production environment | **Owner** for PHP version, incident contact, environment |

## 4. Remediation plan (one PR per phase, in the brief's priority order)

| Phase | Scope | Findings |
| --- | --- | --- |
| R72-1 Security | Update the flagged packages and make the audit gate block unrated advisories; two-factor required for admins and moderators; stronger passwords; no account enumeration; throttles; atomic redeem; escape user text in mail; email change needs the password; insight-counter abuse; remove unused Cashier routes | S-01..S-08, E-05, E-07, F-02, F-03 |
| R72-2 Email and notifications | Email the security and business notices; add the missing mails (new sign-in, role change, account deleted, company deleted, suspension, welcome); queue all mail (`QUEUE_CONNECTION=database`, worker already scheduled); failure logging and `failed_jobs` in ops checks; digest privacy; mail log hygiene; template contrast and wording; then a live delivery test to controlled inboxes | E-03, E-04, E-06, E-08..E-14, S-06 |
| R72-3 Functional and tests | Update the stale browser specs and run the full suite; fix the Dot Apps Escape trap; licence buttons; size bands; sign-in labels on library links; 2FA challenge tests; fix the flaky test; run the browser accessibility suite in CI; gate coverage | B-01..B-03, F-01, F-04..F-06, O-05..O-07 |
| R72-4 Performance | Directory sorted and paged in SQL with FULLTEXT; header search on Scout/FULLTEXT with cached fan-out; cached shell counts; notices counts in SQL; answers N+1; database cache store; minute-job budgets; indexes; lazy-loading guard and query budgets for five pages | P-20..P-34 |
| R72-5 UX and accessibility pass | Rendered pass of every signed-in screen at desktop, tablet and phone; axe on all pages, keyboard pass; fix what it finds | Phases 4–5 |
| R72-6 Code quality | Enquiry, Booking and Business policies; FormRequests for the larger write endpoints; split the largest services where it pays; delete dead classes; shrink the PHPStan baseline | Q-20..Q-27 |
| R72-7 Operations | Deploy takes its own backup before migrating, checks `/ready`, resets to the tested commit, enforces `LOG_LEVEL`; `/ready` in the uptime probe; Dependabot; correct BACKUPS.md, the deployment guide and one runbook index | O-03, O-04, O-08, O-10..O-14 |
| R72-8 Measurement | Seeded staging copy and a load test (k6): p50/p95/p99 for the directory, search, dashboard, inbox, bookings and catalogue, before and after R72-4 | M-01 |
| R72-9 Verification | Independent re-audit and the final scorecard with the brief's evidence categories | Phase 12–13 |

### Needs the owner (cannot be done from the repository)
1. **Restore drill** (O-01): create a scratch MySQL database and user in cPanel; then run `docs/programme/runbooks/mysql-restore-drill.md`.
2. **Alerting** (O-02): create a Sentry project; put `SENTRY_LARAVEL_DSN` and `OPS_ALERT_ADDRESS` (a mailbox you read) in the host `.env`.
3. **Off-host backups** (O-09): a storage bucket for a second backup copy; the backup passphrase in a password manager.
4. **Staging** (R72-8): a staging subdomain with its own MySQL database on the same hosting, for load tests.
5. **Mail DNS** (E-01): after a few weeks of DMARC reports, move DMARC to `quarantine` and SPF to `-all` (exact records in R72-2).
6. **Repository settings** (O-15): a GitHub `production` environment with you as reviewer; confirm the host's PHP version; name a backup incident contact.

### Decisions for the owner before R72-1 and R72-2
- **Two-factor for admins and moderators**: required (you will need to turn on two-factor on your own account first).
- **Breached-password check**: uses the public Pwned Passwords service, which only ever receives the first five characters of a password's SHA-1 hash.
- **Queued email in production**: switch the host to `QUEUE_CONNECTION=database`; the worker already runs every minute.

### Decided by the owner, 2026-11-26
- Two-factor is **required** for platform admins and moderators (the owner switches it on for their own account first).
- New passwords: **at least 12 characters and a breached-password check** (Pwned Passwords k-anonymity range lookup).
- Production mail moves to the **background queue** (`QUEUE_CONNECTION=database`) with the R72-2 deploy.

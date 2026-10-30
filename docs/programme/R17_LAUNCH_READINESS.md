# R-17 — InfoDot Enterprise Readiness Report (2026-10-30)

The owner's brief is verbatim in `requirements/infodot-enterprise-launch.md`. This run re-audited
the platform after R-13..R-16 landed, remediated what the audit found, and re-scored InfoDot under
the brief's §19 weighted model. It builds on — and does not repeat — the enterprise programme
evidence in `docs/programme/enterprise/` (READINESS_MODEL, BASELINE, FINDINGS ~80 rows, SCORECARD,
policies, 2026-10-30).

Tooling note (§6): Laravel Boost 2.5 is installed in the repo. Its MCP server was not wired into
this session, so inspection used the equivalent native surface — `route:list`, config and schema
reads, the full test suite, PHPStan level 5, Pint, and the Vite build — which covers the same
ground. Boost remains available for future sessions.

## A. Executive summary

**Score: 76/100 under the §19 weights — "Approaching market readiness; important gaps remain."**

**InfoDot is NOT declared market-ready**, on the brief's own blocker rule, because two critical
items remain unresolved and both are owner-run:

1. **The MySQL backup has never been restored** (Drill B, `enterprise/policies/RECOVERY_DRILL.md`).
   A backup is not a backup until a restore has succeeded. ~30 minutes with a scratch database.
2. **The paid-seminar payment path has never been exercised against Paystack**, even in test mode
   (checkout, webhook, refund of a split payment). `SEMINARS_CHECKOUT_ENABLED` stays off until done.

Everything else found by this audit was either fixed in this change set, already evidenced by the
enterprise programme, or is listed in §I with severity and effort. The engineering posture is
strong: 1,324 tests, 11 required CI checks on a protected main, PHPStan clean, tenant isolation
tested in both directions, honest content policies enforced in code and docs.

## B. What this run audited and found (delta over the enterprise baseline)

| Area | Finding | Outcome |
| --- | --- | --- |
| Session cookies | `secure` flag had no default — production relied on an env nobody had set | **Fixed**: secure-by-default when `APP_ENV=production`, env still overrides |
| Password hashing | bcrypt cost 10 (Laravel's current default is 12) | **Fixed**: default 12; existing hashes rehash on next login. Argon2id not flipped: availability on the cPanel PHP build is unverified, and bcrypt@12 is a supported, adequate default (§7.7 allows a configured modern alternative) |
| Git history secrets | Full-history pattern scan: one hit — a default `postgres/postgres` credential for a local/CI database; not a live secret. The historically committed APP_KEY (B-01) remains in history; owner verified 2026-10-30 that the live key differs, so it is dead. `.env` files were never committed. GitGuardian scans every PR | **Reviewed — no action**; history rewrite not warranted |
| robots.txt | Allowed everything, no sitemap, no crawler policy | **Fixed**: explicit policy (below) |
| Sitemap | None | **Fixed**: `/sitemap.xml`, code-generated, public pages only |
| Canonicals/social meta | None; Jetstream's auto-registered `/terms-of-service` + `/privacy-policy` duplicated the canonical legal pages | **Fixed**: canonical + OG/Twitter on every public page; description guaranteed by a default; duplicates 301-redirect via a pre-routing middleware (Jetstream's routes register before the app's) |
| Structured data | None | **Fixed**: Organization + WebSite on the home page; FAQPage on /faqs **generated from the same array that renders the visible answers**, so the markup cannot say what the page does not |
| Cookies/consent (§8) | — | **Position documented**: InfoDot sets only strictly necessary cookies (session, XSRF, remember-me) and runs no analytics or third-party trackers (verified by code search). No consent banner is required for strictly necessary cookies under POPIA/GDPR; the existing `/cookies` policy page states exactly this and is accurate. If a tracker is ever added, a consent manager becomes mandatory first |
| Mass assignment (new models) | Reviewed R-14..16 models: status/ownership/tenant fields excluded from `$fillable` or set via guarded code paths; `is_moderator` not fillable | **Clean** |
| Rate limiting | New R-16 endpoints throttled at the route + in-service limits; Fortify login throttle on | **Clean** |
| Mail templates | 4 real templates (contact, points digest, points expiry, team invitation), SMTP via no-reply@infodot.co.za with a 10 s timeout | **Clean** |

Crawler policy implemented (each evaluated, per §13.1): search engines and AI answer-fetchers are
allowed on public pages; bulk **training** crawlers (GPTBot, CCBot, Google-Extended, anthropic-ai,
ClaudeBot, meta-externalagent, Bytespider) are **blocked by default** — they collect content
without sending visitors. This is a default the owner can reverse per crawler in `public/robots.txt`.
robots.txt is guidance only; privacy is enforced by sign-in and server-side authorisation.

## C. Security report (summary; detail in enterprise/FINDINGS + the R-15/R-16 records)

Authentication: Fortify + 2FA available, login/registration/reset throttled, account enumeration
guarded by framework responses. Authorisation: policies + `HasKnowledgeVisibility` global scope;
tenant isolation tested both directions (R-16 similar-content tests, R-15 attachment matrix,
hardening suite). Sessions: database driver, HttpOnly, SameSite lax, secure-by-default in
production (this run), regeneration handled by Fortify. Secrets: none in code or history (scan
above), GitGuardian on every PR, advisories gate fail-closed. Uploads: content-sniffed allowlist,
no SVG, private disk, permission-checked streaming with sandbox CSP, caps and quotas (R-15).
Headers: nosniff, SAMEORIGIN, referrer, permissions, HSTS, CSP report-only with /csp-report.
APIs: HMAC-signed server-to-server contracts, fail closed when unset; Sanctum handoff hardened
(#161-#169). Encryption: TLS in transit; at rest relies on host disk — field-level encryption not
needed for current data classes (no card data stored; Paystack holds payment instruments).
**Open (owner)**: TRUSTED_PROXIES, Sentry DSN, malware-scan service decision (R-15 §6), argon2
availability check if desired, adviser sign-off on legal pages.

## D. Product quality report

All workflows covered by the suites: journeys (register → company → ask → answer → accept →
verify → library), R-13 seminars/tiers/licensing, R-14 library seeding, R-15 attachments matrix,
R-16 validation/mentions/improvements/search. Browser passes on the new UI (R-15, R-16) with real
clicks on a local run. Accessibility: baseline test (labels, landmarks) + WCAG-minded components;
a full WCAG 2.2 AA audit by a specialist remains an owner option before enterprise sales.
Responsive: key flows checked at desktop width in browser passes; full device-matrix testing not
performed this run (listed in §I). Content: public pages carry unique titles/descriptions; legal
pages are real documents awaiting adviser sign-off; the official library (R-14) cites sources and
review dates; no invented numbers anywhere (enforced by the content rules in R14/R16 docs).

## E. Performance report

Evidenced: hot-query indexes (`hot_*`, `legacy_*`), query budgets in tests (lists bounded, similar
≤12 queries, identical searches answered from cache), eager loading on the heavy pages, debounced
and throttled lookups, nightly recalcs sliced to the 115 s cron ceiling, pcov coverage job.
**Not evidenced, stated plainly: no load test and no measured capacity.** The host is shared cPanel
(1 GB, ~30 entry processes across 26 apps). A staging copy with seeded volume is the prerequisite
for honest numbers (owner item since the enterprise programme). No capacity claims are made.

## F. SEO and discoverability report

robots.txt policy (above) · `/sitemap.xml` (9 public pages, cached, excludes everything private —
tested) · canonical + meta description + OG/Twitter on every public page with uniqueness tested ·
FAQPage JSON-LD generated from the visible content · Organization/WebSite JSON-LD on home ·
duplicate legal URLs 301 to canonicals · private paths disallowed for crawlers and absent from the
sitemap. **After the next deploy (owner, ~15 min):** verify in Google Search Console (submit the
sitemap, confirm indexing of the 9 pages) and spot-check structured data in Google's Rich Results
test. Public Q&S detail pages are sign-in-only today, so the indexable surface is the marketing/
legal set; opening read-only public solution pages to crawlers is a product decision, not a bug
(§I, owner decision).

## G. Commercial readiness report

Entitlements (tiers, seats, licensing) are enforced server-side with tests (R-13, #143/#144 line);
`SEMINARS_CHECKOUT_ENABLED` gates new ticket sales narrowly; billing callbacks signed and
throttled; receipts/invoices implemented. **Blocker: the Paystack path has never been run even in
test mode** — checkout, webhook delivery to production, refund of a split payment. Until that
one-hour owner session happens, paid seminars must stay off. Free-tier commerce (registration →
company → usage) is fully tested. Admin: a real moderation suite exists (queue, library,
reputation, rings, seminar payments, topics, UX friction) plus `ops:check`/heartbeat; a dedicated
billing-admin panel is roadmap, not launch-blocking, since billing admin currently runs through
Paystack's own dashboard + host commands.

## H. Deployment and operational report

CI: 11 required checks on protected main (tests, MySQL+concurrency, advisories fail-closed,
PHPStan ceiling, Pint, OpenAPI, frontend+npm audit, fake-host jobs, coverage). Deploy: manual
dispatch, rollback tarball, maintenance window, live smoke; failure before maintenance leaves the
site untouched (observed twice, including this week). Ops: `/ready`, `/up`, `ops:check` every
15 min with scheduler heartbeat, request-id logging, Sentry wired (DSN pending). Backups: built
and scheduled; **restore untested — the release blocker**. Monitoring: uptime monitor on `/ready`
still pending (owner).

## I. Outstanding actions (severity · owner · effort)

| # | Item | Severity | Who | Effort |
| --- | --- | --- | --- | --- |
| 1 | Run the MySQL restore drill (Drill B) | **Critical — release blocker** | Owner | ~30 min |
| 2 | Paystack test-mode run incl. split-payment refund | **Critical for paid seminars** | Owner | ~1 h |
| 3 | Uptime monitor on `/ready` + Sentry DSN and alert rule | High | Owner | ~30 min |
| 4 | Search Console: submit sitemap, verify indexing + rich results | Medium | Owner | ~15 min |
| 5 | `TRUSTED_PROXIES` on the host; confirm argon2 availability if the hash driver should move | Medium | Owner | ~15 min |
| 6 | Adviser sign-off: privacy/terms/cookies wording, PAIA manual, Information Officer registration | Medium (legal) | Owner + adviser | external |
| 7 | Staging copy with seeded volume → first honest load numbers | Medium | Owner | ~half day |
| 8 | Malware-scan service decision for uploads (R-15 §6) | Medium | Owner | decision + ~2 h |
| 9 | Device-matrix responsive pass + specialist WCAG 2.2 AA audit | Medium | Either | ~1 day |
| 10 | Product decision: public read-only solution pages for crawlers | Low (opportunity) | Owner | decision, then ~1 day |
| 11 | Billing-admin panel beyond Paystack dashboard + host commands | Low | Engineering | roadmap |
| 12 | AI-training crawler policy: confirm or reverse the block defaults | Low | Owner | decision |

## §19 score (evidence-weighted)

| Area | Weight | Score | Weighted |
| --- | ---: | ---: | ---: |
| Core functionality and workflow reliability | 15% | 85 | 12.8 |
| Security and privacy | 20% | 80 | 16.0 |
| UI/UX and accessibility | 10% | 75 | 7.5 |
| Content quality and credibility | 10% | 85 | 8.5 |
| Performance and scalability | 10% | 55 | 5.5 |
| Infrastructure and operational reliability | 10% | 65 | 6.5 |
| API and integration readiness | 5% | 75 | 3.8 |
| SEO and discoverability | 5% | 80 | 4.0 |
| Commercial and subscription readiness | 5% | 60 | 3.0 |
| Testing, documentation and maintainability | 10% | 85 | 8.5 |
| **Total** | **100%** | | **76.1 ≈ 76** |

Scores follow the brief's classification discipline: implemented-and-tested earns points;
implemented-but-unverified does not (which is why performance, infrastructure and commercial sit
where they do — the code exists, the proof does not yet). The two critical blockers cap the
launch recommendation regardless of the number.

**Launch recommendation:** complete items 1–3 (≈2 hours of owner time), then InfoDot can open to
the public with paid seminars still gated; switch paid seminars on only after item 2.

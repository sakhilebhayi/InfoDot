# Dot.Pulse: monetisation fact audit (R-60, read-only)

Source basis: `origin/main` of /Users/sakhilebhayi/Dot/Dot.Pulse (tip a533716, R-52 rollout), extracted read-only with `git archive`. NOTE: the local checkout (`main`, ad43c20, Aug 2026) is 71 commits behind `origin/main` and predates R-41, so the checked-out files are NOT what is deployed. Paths are relative to the repo root on `origin/main`. Tags: EXISTING / PROPOSED / NEEDS DEVELOPMENT.

## 1. What it does
- EXISTING: a business social network built on "signals": offers, roles (jobs), needs, doors and talent profiles are indexed (`pulse_signals`) and rule-matched to people (`pulse_matches`), always with a stated reason (`app/Services/Pulse/Discovery/Matcher.php`, `docs/programme/R41_READINESS.md`).
- EXISTING: "The Floor": a discovery surface with at most 20 beats per round, modes Trade/Work/Local/Learn, plus search over signals and pages (`Discovery/Floor.php`, `SearchService.php`).
- EXISTING: business pages (`pulse_entities`) with offers (`pulse_offers`), roles (`pulse_roles`), team members and invitations, response stats, verification ladder from real data only.
- EXISTING: "Lines": consented conversations (`pulse_conversations` kind dm/enquiry/quote/application/intro/collab/invite), a four-field first "knock", quote slips (`pulse_quotes`), outcomes counted only when BOTH sides confirm (`outcome`, `outcome_initiator_at`, `outcome_recipient_at`), blind reviews (`pulse_reviews`).
- EXISTING: legacy community layer retained: posts, comments, reactions, polls, events, communities, badges, marketplace items (`pulse_posts`, `communities`, `pulse_events`, `pulse_marketplace_items`) with moderation (`Moderation/ModerationPipeline.php`, `LocalRules.php`).

## 2. Most valuable features (measurable business value)
- Both-confirmed outcomes (won/hired/partnered): the strongest, hardest-to-fake value metric (`pulse_conversations.outcome` + both timestamps; `app/Services/Pulse/Lines/OutcomeService.php`; operator metrics `Operator/Metrics.php` count only these).
- Explainable matches that reach the right buyer/employer/supplier (`pulse_matches.score`, `reasons`, `seen_at`, `dismissed_at`).
- Qualified first contact with a consent gate and spam control (`Lines/ContactGate.php`, `KnockBudget.php`, `Lines/ConsentLedger.php`).
- Quotes from up to three providers side by side (`Lines/QuoteService.php`, `pulse_quotes`).
- Response-time stats shown on a business page (`Lines/ResponseStats.php`, `first_response_at`).
- Hiring (roles with 60-day hiring signal) and anonymous talent cohorts (k >= 5, `Entities/TalentCohort.php`, `Discovery/AnonymousCohort.php`).

## 3. Existing limits, quotas, plans, entitlement, billing
- Entitlement: EXISTING boolean only. `app/Http/Middleware/EnsureCompanyEntitled.php` (applied in `routes/web.php` lines 46, 139, 149, 182, 212, 265 and on the broadcasting channels in `bootstrap/app.php:19`) uses `app/Services/InfoDot/EntitlementClient.php`: GET `{infodot}/api/ecosystem/companies/{uuid}/platforms?platform=pulse`, cached 600 s (answer) / 60 s (failure), `ecosystem.infodot.entitlement_check` toggle. Only an explicit `entitled:false` refuses; any failure carries on. Personal workspaces / teams without `infodot_team_uuid` are never asked; sign-in's answer about the personal workspace is kept in session (`infodot.personal_entitled`). If a person belongs to other entitled companies the refusal offers a switch (middleware lines ~78-93). Pulse is a global network, so most users are individuals, not companies. No quantity/allocation is read.
- Limits EXISTING (`config/pulse.php`): businesses (entities) per user 5; offers per entity 100; roles per entity 50; open needs per user 10; offer images 8 per offer; media max 5 MB and 16 MP, 40 uploads/hour; need TTL 30 days; hiring signal 60 days; message max 2000 chars; first-contact ("knock") budget 7 per week established / 3 for accounts under 7 days, refillable by replies up to +5, shrinks on confirmed spam (`Lines/KnockBudget.php`, table `pulse_knock_budgets`); quote validity max 90 days; per-minute rate limits for post/comment/react/report/message/knock/search; digest max 6 items. Age gate 18+.
- Design rule found in code: `KnockBudget.php` docblock states the knock allowance "cannot be bought: there is no method here that adds to it for money". That is a deliberate anti-spam/fairness decision to respect when proposing units.
- Plans/subscriptions/billing code: none (no Paystack, plan, invoice, credit code in app/, config/, routes/, database/). `pulse_marketplace_items` / `pulse_marketplace_installs` exist from the legacy layer but contain no payment code.
- Verification: InfoDot exposes only team membership, so no "verified business" claim (`Entities/InfoDotLink.php`, `VerificationService.php`).

## 4. Resources consumed or stored and where they can be metered
| Resource | Meter source today | Status |
|---|---|---|
| Business pages | `pulse_entities` (owner/members via `pulse_entity_members`), `count per user` already enforced | EXISTING |
| Offers / roles / needs live | `pulse_offers`, `pulse_roles`, `pulse_needs`, and the indexed `pulse_signals` | EXISTING |
| Media storage | `pulse_media_files.bytes` (largest variant only; `variants` json holds thumb/medium/large bytes), `purpose`, `entity_id`, `user_id`; disk `PULSE_MEDIA_DISK` | EXISTING (bytes understates total since 3 variants stored) |
| Knocks (first contacts) | `pulse_knock_budgets` (`spent`, `bonus`, `penalty`) + `pulse_conversations.status = requested` | EXISTING |
| Conversations / messages | `pulse_conversations`, `pulse_messages` | EXISTING |
| Quotes | `pulse_quotes` | EXISTING |
| Confirmed outcomes | `pulse_conversations.outcome` + both timestamps | EXISTING |
| Match computation (CPU) | `pulse_matches.computed_at`; `pulse:match` every 10 min, full recompute of 20k people ~25 min (`R41_READINESS.md`); no per-user cost row | NEEDS DEVELOPMENT for per-company attribution |
| Moderation model calls | `pulse_post_enrichments`, `pulse_moderation_logs` (model second opinion only when `ANTHROPIC_API_KEY` set; local rules otherwise); no token columns | NEEDS DEVELOPMENT |
| Emails (notifications, digest, invitations) | `notifications` table (in-app) + `Lines/Notifier.php` (mail only if prefs allow, not quiet hours) + `Discovery/Digest/DigestSender.php` (hourly `pulse:digest`); no send counter | NEEDS DEVELOPMENT |
| Seats (page team members) | `pulse_entity_members`, `pulse_entity_invitations` | EXISTING |
| Platform analytics | `app/Services/Analytics/MetricsCollector.php` exports `posts_7d`, `active_users_7d`, `held_posts` only (platform-wide, no company) plus `Operator/Metrics.php` extended aggregates (inert until Analytics settings exist) | EXISTING, not usable for billing |
| Search | LIKE scan (fine to ~100k signals per readiness report); throttled 90/min | EXISTING |

## 5. Third-party services and cost drivers
- Anthropic Messages API (`config/services.php` `anthropic`, model default `claude-sonnet-4-6`, 10 s timeout): optional moderation second opinion on held posts only, queued job `app/Jobs/ModeratePost.php`; local rules decide without it. Low volume; cost scales with held posts.
- Email transport (`MAIL_*`): digests (hourly), line notifications, entity invitations. Main per-transaction cost.
- Laravel Reverb websockets (`config/reverb.php`) self-hosted; short polling is used for most real-time (30 s badge, 10 s thread).
- Database (PostgreSQL in production): `pulse:match` and `pulse:index` batch load; benchmark: 20k people, 34k signals, 60k lines, 464k messages (`docs/programme/R41_PERFORMANCE.md`). Largest cost driver is compute/DB, not third-party APIs.
- Disk for images (three stored sizes per upload).
- Built-in gazetteer (~900 places, `pulse:places:seed`); no external maps/geocoding call.
- InfoDot (identity, entitlement, team sync), Dot.Analytics (optional). No Dot.Files, Dot.Notify or Dot.Finance integration.

## 6. Candidate purchase units (PROPOSED; no prices)
- Capacity (business side): number of business pages beyond a free one, live offers/roles beyond a free count, media storage beyond a floor, team seats on a page. Reason: all already counted and capped in `config/pulse.php`; they cost the owner storage/index/match compute.
- Credit (optional, careful): "boosted reach" or extra visibility rounds for a live offer/role would create pay-to-be-seen, which conflicts with the explainable-matching principle and with the report's "always says why" rule; if pursued it must be labelled and kept out of the match score. PROPOSED only as an owner decision.
- Transaction: a fee-bearing "confirmed outcome" is the strongest value signal (both parties confirm), but money settlement is not in Pulse (Money.php only formats; no payments) so a per-outcome fee needs Dot.Finance or a payment provider. NEEDS DEVELOPMENT.
- Credit: hiring tools (role posts beyond free, applicant handling), quote-slip volume for providers, analytics/insights views for a page.
- Feature: verification badge once InfoDot exposes business verification; advanced response-stats and exports for a page.
- Keep FREE (PROPOSED): individual profile and onboarding, Floor browsing and search, creating needs and doors, receiving and answering lines, blocks/mutes/reports/privacy export and erase (POPIA obligations), all safety and moderation, one business page with a modest number of offers/roles, the existing knock budget (must remain unbuyable, by code comment), reviews, outcomes. Individuals should never pay to be contacted or to see why they matched.
- On exhaustion (PROPOSED): block only creation of NEW paid-capacity items (e.g. a 6th page, offers over the cap, uploads over storage); existing listings stay live until their natural expiry; existing lines, messages and outcomes are untouched; over-quota offers are unpublished to draft, never deleted.

## 7. Cross-platform dependencies and shareable resources
- Depends on InfoDot only (sign-in, entitlement, `SyncTeamsFromInfoDot`). Pulse stores no cross-platform links to Emall, Finance, Files, HR, Tutor, though the product overlaps in theory with Emall (listings) and HR (hiring).
- Shareable (PROPOSED): storage (Dot.Files pool vs the app-local media disk), comms credits (a single email/SMS meter via Dot.Notify), seats (company licence); AI credits only marginally (moderation second opinion is operator-side and should stay platform cost, not a customer-billed resource).
- Platform-specific: business-page count, listing counts, match/discovery capacity.

## 8. Gaps to build (NEEDS DEVELOPMENT)
- Allocation read from InfoDot (limits per company) beyond `entitled` bool, in `EntitlementClient.php`, plus a mapping of a Pulse business page (`pulse_entities`) to an InfoDot company: pages are owned by users with optional team link (`pulse_entity_members`), while entitlement is per company; the billable subject (user vs page vs company) must be decided.
- Enforce at the creation services (`EntityService`, `OfferService`, `MediaService`, `NeedService`) rather than only via config caps; caps are today global constants, not per-company allocations.
- Usage ledger (append-only, company or entity keyed) for storage bytes, email sends, model calls, plus accurate total media bytes (sum of variants).
- Email send counter around `Notifier.php` / `DigestSender.php`; token capture for the moderation model call.
- Payment/settlement route for any per-outcome or capacity purchase (none in Pulse).
- Because most Pulse users are individuals and entitlement is fail-open for personal workspaces, monetising capacity requires deciding what an unentitled individual keeps (recommended: everything personal stays).
- Audit: Pulse already has an immutable audit log (`pulse_audit_log`, `Lines/AuditLog.php`); allocation changes and capacity enforcement events should write to it.

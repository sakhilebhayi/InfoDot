# Dot.Analytics: monetisation fact base (R-60, read-only audit)

Audit basis. The local checkout `/Users/sakhilebhayi/Dot/Dot.Analytics` is on stale branch `fix/ci-trigger-branch-coverage` (c7c9eee, 2026-09-23). The live code is the default branch `origin/feature/ecosystem-sso` (50e659a, 2026-11-15, as of the last fetch; I did not fetch). All paths below are repo-relative on that branch unless marked "(local only)". Status tags: EXISTING = verified in code, PROPOSED = my suggestion, NEEDS DEVELOPMENT = not built.

## 1. What it does
- Two products in one app. (a) A public, free intelligence library built from open sources: indicators, countries, industries, newsroom, briefings, sources, search (`routes/public.php`, `config/intelligence_sources.php`). EXISTING.
- (b) A per-company workspace: Dot platforms push signed daily snapshots, which become metrics, alerts, AI insights, executive briefings, Business DNA, saved reports (`app/Http/Controllers/Api/IngestSnapshotController.php`, `app/Services/Metrics/*`, `routes/api.php`). EXISTING.
- (c) A personal "library" per user: watchlist, saved items, alerts, email digests (`routes/me.php`, `app/Services/Library/*`). EXISTING.
- The earlier natural-language SQL, connectors, pipelines, knowledge graph and feature flags were dropped (`database/migrations/2026_11_07_100000_remove_unreachable_features.php`). The stale local checkout still has them; ignore them.

## 2. Most valuable features (measurable business value)
- Company metric workspace and cross-platform view: `ComputeMetrics`, `MetricReader`, `MetricAnomalyDetector`, workspace pages (`app/Http/Controllers/WorkspaceController.php`). EXISTING.
- Anomaly alerts with dedupe (`analytics_alerts`, migration `2026_10_18_210000_add_dedupe_key_to_analytics_alerts.php`). EXISTING.
- AI executive briefings (daily, weekly, monthly), AI cross-platform insights, Business DNA, recommendations (`app/Jobs/Analytics/GenerateExecutiveBriefingJob.php`, `app/Services/CrossPlatformIntelligenceService.php`, `app/Services/BusinessDnaService.php`). EXISTING. These carry the real model cost.
- Scheduled reports and CSV/HTML export (`app/Console/Commands/RunScheduledReportsCommand.php`, `app/Services/ReportGenerationService.php`). EXISTING.
- SA-relevant public data (Africa newsroom feed, Stats SA feed is present but `enabled => false`). EXISTING.

## 3. Existing limits, quotas, plans, entitlement checks, billing
- InfoDot entitlement (EXISTING): `app/Http/Middleware/EnsureCompanyEntitled.php` runs on the signed-in route group (`routes/web.php:42`, `routes/me.php:15`), is a Livewire persistent middleware (`app/Providers/AppServiceProvider.php:53`) and guards broadcast channels (`routes/channels.php:31,36`). It asks InfoDot through `app/Services/InfoDot/EntitlementCheck.php` -> `IdentityClient::companyPlatforms($uuid)`, cached 10 minutes (60 s on failure). Only a clear "no" blocks, with HTTP 403 and `EntitlementRefusal`. Unknown never locks anyone out. A personal workspace is judged by the `entitled` flag remembered at sign-in (`PERSONAL` session key). Data export and erasure (`me.export`, `me.erase`) are exempt. The tenant link is `teams.infodot_team_uuid` (`database/migrations/2026_10_18_100000_add_infodot_team_uuid_to_teams_table.php`). Not applied to the public library, the machine API (`/api/v1`), or snapshot ingest. This is an access gate (company holds the platform or not), not a quantity meter.
- Library limits (EXISTING): `config/intelligence_plans.php` and `app/Services/Library/Entitlements.php`. Free = 25 watches, 100 saved, 200 alerts kept. A "plus" plan (150 / 1000 / 2000) is "designed, not on sale" (`for_sale => false`). `Entitlements::for()` always returns `free` ("No billing exists yet"). Enforced at `Me/WatchController.php:36`, `Me/SavedController.php:46`, `Library/AlertScanner.php:121`. The file states a policy: published figures are never gated and a test checks nothing else can be added to a plan ("always_free" list).
- AI budget caps (EXISTING): `app/Services/AiModelRouter.php:99-110,217-227`. `completeStrict` refuses when the team has spent USD 5 this month (`AI_TEAM_MONTHLY_BUDGET_USD`) or the whole platform has spent USD 50 (`AI_PLATFORM_MONTHLY_BUDGET_USD`; `config/services.php:46-47`). Measured from `ai_model_usage.cost_usd`. This is a protective ceiling, not a purchasable allocation, and it is in USD.
- Rate limits (EXISTING): `analytics-api` 120/min, `analytics-ai` 10/min per user, `analytics-ingest` 300/min (`app/Providers/AppServiceProvider.php`); `analytics-ingest-edge` for snapshot ingest; body cap 65536 bytes (`config analytics.ingest.max_body_bytes`); `public-search` throttle.
- Billing code: none (EXISTING absence). No plan table, subscription model, Stripe, Paystack or wallet in this repo.

## 4. Resources consumed or stored, and where to count them today
| Resource | Where it can be read today | Status |
|---|---|---|
| AI calls, tokens, USD cost, by team, model, capability, engine | table `ai_model_usage` (`app/Models/AiModelUsage.php`; columns team_id, provider, model, capability, engine, input_tokens, output_tokens, cost_usd). API `GET /api/v1/metrics/ai-usage` (`MetricsController::aiUsage`). Caveat: `trackUsage` returns early when `teamId` is null, so calls with no team are not recorded | EXISTING |
| Briefings generated | `executive_briefings` | EXISTING |
| Engine runs | `intelligence_engine_runs` (platforms_consumed, insights_generated) | EXISTING |
| Insights stored | `cross_platform_insights` | EXISTING |
| Snapshots ingested (stored data, rows) | `analytics_snapshots`; per-platform via `DataSource` | EXISTING |
| Connected platforms | `data_sources` where status = connected | EXISTING |
| Scheduled report runs, exports | `report_runs`, `analytics_reports` | EXISTING |
| Saved dashboards and widgets | `analytics_dashboards`, `dashboard_widgets` | EXISTING |
| Library watches, saved, alerts, digests | `library_watches`, `library_saved`, `library_alerts`, `library_preferences` | EXISTING |
| Digest emails sent | `app/Console/Commands/SendLibraryDigests.php` sends with `Mail::to()`; no send log table | NEEDS DEVELOPMENT to count |
| Team members | `team_user` (Jetstream) | EXISTING |
| Audit trail | `audit_logs` | EXISTING |
| Public data store size | `observations`, `observation_revisions`, `items`, `signals`; pruned by `intelligence:prune` | EXISTING (shared cost, not per customer) |
Background load (EXISTING, `routes/console.php`): metrics hourly, intelligence engines every 6 hours for all teams, briefings daily/weekly/monthly, Business DNA nightly, scheduled reports every minute, source collection every 10 minutes, library scan every 30 minutes, digests daily/weekly. The 6-hourly engine run is the main uncontrolled AI cost driver, because it runs for every team with a connected platform.

## 5. Third-party services called
- AI/LLM (EXISTING, `app/Services/AiModelRouter.php`): Anthropic (primary by default), OpenAI, Google Gemini, DeepSeek. Price table hard-coded in USD per million tokens (`MODELS`). Falls back through tiers by capability.
- Open data sources (EXISTING, `config/intelligence_sources.php`): World Bank, OECD SDMX, ECB, BIS, US EIA (needs `intelligence.keys.eia`), Federal Reserve RSS, ECB press, EU Commission press, UK Treasury and Business Dept Atom, Africa Newsroom RSS, Stats SA (disabled). Each source carries a licence note; the file says counsel decides before launch and that some sources may not be resold. This constrains what can be sold.
- InfoDot identity and entitlement API (EXISTING, `app/Services/InfoDot/IdentityClient.php`).
- Mail: Laravel mailer for digests and team mail (SMTP host per memory; no SMS).
- Cost drivers: LLM tokens (main), scheduler-driven engines, mail volume, snapshot storage. Compute for public-data collection is a fixed platform cost.

## 6. Candidate purchase units, free tier, exhaustion behaviour
Principle already stated in the code (EXISTING): published library figures, search, downloads and sources are free for ever. Keep them free (PROPOSED: this also protects licence compliance).
- AI analysis credits (PROPOSED, best unit). One credit = one AI generation (briefing, insight batch, DNA recompute, "ask" answer), or tokens-based with a ZAR conversion at purchase time. Reason: it is the only real marginal cost and `ai_model_usage` already records it per team. Free tier: a small monthly allowance (the current USD 5 per team cap is the natural seed, but needs converting to credits and ZAR).
- Connected platform data feeds / retained history (PROPOSED, capacity). Free: connect a small number of platforms and keep a short history; paid: more history months and more snapshot rows. Reason: snapshots accumulate in `analytics_snapshots`.
- Scheduled reports and report exports (PROPOSED, capacity). Free: a few saved reports, manual CSV; paid: more schedules and delivery by email.
- Library capacity (PROPOSED, capacity). The "plus" tier already designed (more watches, saved, alerts) can become a small capacity pack. Low revenue; keep generous.
- Alert and digest delivery by email/SMS (PROPOSED, comms credits) once SMS exists. NEEDS DEVELOPMENT.
- Keep free: the whole public library, metric tiles and anomaly alerts on connected data (these are cheap, rule-based), data export and erasure (already exempt from entitlement).
- When an allocation runs out (PROPOSED): AI features show "allocation used, resets on <date>, top up" and fall back to the rule-based numbers already shown (metrics, anomalies). Do not block the workspace, ingest or the public library. Existing precedent: `AiModelRouter::completeStrict` already refuses with a log line and callers handle absence (`hasProvider()` checks), so the UI path exists but has no user-facing message. NEEDS DEVELOPMENT for the message.
- Caution: a purchased credit must never be able to unlock public data whose licence forbids resale (`config/intelligence_plans.php` header and `config/intelligence_sources.php` state this).

## 7. Cross-platform dependencies and shareable resources
- Depends on other platforms: it ingests signed snapshots from Dot platforms (per-platform secrets, catalogue in `config/metrics.php`), and reads InfoDot for identity and entitlement. Metric catalogue covers 13 platforms per the R-50 audit notes (`/Users/sakhilebhayi/Dot/r50/AUDIT_ANALYTICS_PAYMENTS_NOTES.md`). Config still names discontinued platforms (`config/ecosystem.php`, `IntelligenceEngineService::PLATFORMS`) per that audit; confirm after clean-up.
- Shareable across the ecosystem (PROPOSED): AI credits (one shared wallet, since Agents and Forms also spend LLM tokens), seats (team membership comes from InfoDot teams), comms credits (email/SMS).
- Platform-specific (PROPOSED): snapshot history retention and scheduled-report capacity.

## 8. Gaps to meter, enforce and audit
1. NEEDS DEVELOPMENT: shared wallet client. Entitlement today is boolean (`EntitlementCheck` returns entitled/status only). A balance read and a debit call with an idempotency key (per AI call) must come from the future wallet service.
2. NEEDS DEVELOPMENT: pre-call reservation in `AiModelRouter::completeStrict`, replacing or sitting beside the USD cap, plus an "out of allocation" result the callers can show. Today callers cannot tell "refused for budget" from "no provider".
3. NEEDS DEVELOPMENT: record AI calls that have no team (`trackUsage` skips them) and record failed or fallback calls (`fallback_used` column exists but is not set in the code I read).
4. NEEDS DEVELOPMENT: the 6-hourly engine job runs for every team regardless of plan; it needs a per-company schedule setting and a wallet check before dispatch (`RunIntelligenceEnginesCommand`, `RunIntelligenceEnginesAction`).
5. NEEDS DEVELOPMENT: `AiModelUsage` cost is USD from a hard-coded table; there is no ZAR rate or dated price table (Agents has `ModelPrice`; reuse that idea).
6. NEEDS DEVELOPMENT: `GET /api/v1/metrics/ai-usage` sums `AiModelUsage::count()` for all teams (`total_calls`) rather than the current team when no authenticated session scope applies (API token requests rely on `HasTeamScope`, which only scopes when a session user with a current team exists). Verify before exposing usage to customers.
7. NEEDS DEVELOPMENT: enforcement for connected-platform count, snapshot retention and scheduled-report count (none exist).
8. NEEDS DEVELOPMENT: email send log for digests and reports, to meter comms.
9. NEEDS DEVELOPMENT: audit of balance movements (existing `audit_logs` is append-only and could hold them).
10. Entitlement unknown means open: by design the check fails open (`EntitlementCheck`). A purchase-based allocation needs the opposite failure rule for spending (fail closed on debit, fail open on read-only use), which is a design decision for the owner.

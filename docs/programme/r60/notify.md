# Dot.Notify: monetisation audit (read-only, R-60)

Evidence base. The local working copy `/Users/sakhilebhayi/Dot/Dot.Notify` is on `feat/reverb-realtime-core`, 10 commits behind origin. Everything below was read from `origin/main` a62bd8f (2026-11-15) exported with `git archive` into the scratchpad. Paths are repo-relative. No real `.env` read (only `.env.example`). Tags: EXISTING / PROPOSED / NEEDS DEVELOPMENT.

## 1. What it does (core value)
- EXISTING: the ecosystem's notification layer. A company configures channels, message templates and rules; other Dot apps tell Notify "this happened, tell this person" through a signed server-to-server API (`POST /api/ingest/events`, `app/Http/Controllers/Api/IngestEventController.php`).
- EXISTING: delivery is queued with atomic claim, retries with backoff 30/120/480/1920 s, max 5 attempts, stuck-send sweeper, opt-out enforcement, security notices always sent (`app/Services/NotifyDispatcher.php`, `app/Jobs/DeliverNotifyLog.php`, `config/notify.php`).
- EXISTING: a catalogue of 17 events across Tasks, HR, Auction, Ehail with default email text and an allowed-data-key whitelist; a company switches events on from the Connected apps page (`config/notify_events.php`, `app/Services/Notify/NotifyEventCatalogue.php`, `EventEnabler.php`, `app/Livewire/Notify/ConnectedApps.php`).
- EXISTING: delivery log per recipient with status queued/sending/retrying/sent/failed/suppressed (`notify_logs`); in-app bell (`NotificationBell`), scheduled sends by cron (`DispatchDueSchedules`, `notify_schedules`), inbound third-party webhooks (`POST /webhooks/{token}`).
- Honest caveat (EXISTING): only email, in-app, webhook and Slack actually deliver. `NotifyDispatcher::deliver()` throws PermanentDeliveryFailure for `sms` ("no configured provider") and `push` ("no configured provider"). The README advertises SMS/push; the code does not deliver them.

## 2. Most valuable features (measurable business value)
- EXISTING: reliable, idempotent event ingestion with a rate ceiling and no double sends (`unique (team, platform, idempotency_key)` on `notify_inbound_events`; atomic claim in `NotifyDispatcher::claim`). Value: every Dot platform gets alerts without building email plumbing.
- EXISTING: governed event catalogue + per-person opt-outs + redaction of sensitive keys (`Services/Notify/Redactor.php`, `NotifyPreference`).
- EXISTING: delivery log/audit and failure visibility (dashboard in `routes/web.php`: sent today, failed today, retrying, suppressed 30 days).
- EXISTING: SSRF-guarded outbound webhook/Slack delivery (`UrlGuard.php`, `SafeHttp.php`).
- EXISTING: AI template generator (`app/Services/AiNotifyService.php`, used by `app/Livewire/Notify/TemplateEditor.php`). Verified defect: it reads `config('services.anthropic.key')` but `config/services.php` defines `anthropic.api_key`, so on the code as exported the key is never found and it always returns the deterministic stub. NEEDS DEVELOPMENT/bug fix before any AI-credit pricing (otherwise AI usage would never happen).

## 3. Existing limits, quotas, plans, entitlement checks, billing code
Entitlement consult (EXISTING):
- `app/Http/Middleware/EnsureCompanyEntitled.php` (web signed-in group in `routes/web.php`) and `EnsureCompanyEntitledForBroadcast.php` (`/broadcasting/auth`) ask `app/Services/InfoDot/EntitlementClient.php` whether the company holds Notify. Boolean `entitled`/`status`, cached, fail-open on unknown. Env `NOTIFY_ENTITLEMENT_CHECK=true` (`.env.example`). Same design as Finance.
- GAP (verified by grep: `Entitlement` appears only in the web middleware, client, refusal page and auth controller): the machine path `POST /api/ingest/events` and `POST /webhooks/{token}` do NOT consult entitlement. A company that no longer holds Notify can still have other platforms deliver through it. NEEDS DEVELOPMENT.
Hard limits (EXISTING, technical):
- Ingest rate: 120 accepted events per platform+company per minute (`config/notify.php` `NOTIFY_INGEST_RATE_LIMIT`; returns 429), body max 65,536 bytes, signature tolerance 300 s.
- Per-rule `rate_ceiling_per_hour` (opt-in, `NotifyRule::isOverRateCeiling`, "40x normal volume" incident guard); inbound webhook throttle 30/min (`routes/web.php`).
- Delivery: max 5 attempts, sweep batch 100, stale-sending 15 min.
- Platforms allowed to call are those with `NOTIFY_INGEST_SECRET_<KEY>` set (`.env.example` lists FILES, HR, PROJECTS, TASKS; other callers exist in code, see s7).
Plans/quotas/billing: NONE. No monthly cap, no per-company message allowance, no credit, no payment code. Delivery is unmetered.

## 4. Resources consumed or stored, and where to count them today
| Resource | Where counted (EXISTING) |
|---|---|
| Messages attempted/sent per company | `notify_logs` (team_id, status, sent_at, created_at, channel_id, attempts, notification_type, event, rule_id). Index `(team_id,status,sent_at)`. Count per channel type via join on `notify_channels.type` |
| Emails sent | `notify_logs` where channel type = email and status = sent (`deliverEmail` uses `Mail::raw`) |
| Retry load / failures | `notify_logs.attempts`, `status`, `failure_reason`, `last_error` |
| Suppressed (opt-out) | `notify_logs.status='suppressed'` (free to count) |
| Ingested events per platform/company | `notify_inbound_events` (team_id, platform, idempotency_key, status received/routed/no_rule/ignored/failed) |
| Rules, templates, channels, schedules | `notify_rules`, `notify_templates`, `notify_channels`, `notify_schedules` (config volume, small) |
| Webhook endpoints | `notify_webhooks` (+ inbound events) |
| AI template generations | not recorded (AiNotifyService writes nothing) |
| In-app notifications | Laravel `notifications` table (`2026_08_01_100001_create_notifications_table.php`) |
| Stored message bodies | `notify_logs.body` (encrypted full body cleared to null on sent/suppressed/failed: `succeed`, `suppress`, `fail`), `body_preview` (redacted) |
| Seats | `team_user` |
| Background jobs | `DeliverNotifyLog` on the database queue, scheduler every minute |
Per-company counting is easy and already indexed. Notify already publishes a daily per-company aggregate snapshot to Dot.Analytics (`PublishMetrics`, `app/Services/Analytics/*`, `config/notify.php` `analytics`, inert without URL+secret).
Caveat: `notify_logs` has no `cost`/`units`/`segments` column and no channel-provider id.

## 5. Third-party services and cost drivers
- Email transport: the app mailer (`MAIL_MAILER` in `.env.example`, default `log`; `Mail::raw` in `NotifyDispatcher.php`). Production SMTP/provider is the real per-message cost and the sender reputation risk is shared across ALL companies (single `MAIL_FROM_ADDRESS`).
- Anthropic API for template generation (`services.anthropic`; currently dead due to the config-key defect above).
- Slack incoming webhooks and customer webhook URLs (customer-paid, our CPU/egress only).
- Reverb/WebSocket for the in-app bell (`config/reverb.php`, realtime health `/up/realtime`).
- SMS and push: NO provider integrated (so no cost yet, but also nothing to sell).
- Cost drivers (PROPOSED reading): email volume, retries (each retry is another attempt but only one send; failed sends still cost queue time), SMS if added (largest per-unit cost and the SA gateway/sender-ID compliance burden), AI tokens, log retention (`notify_logs` grows without a purge: no prune command found), websocket connections.

## 6. Candidate purchase units and free tier
- Primary unit (PROPOSED): COMMS CREDITS per message sent, priced by channel (email cheapest; SMS dearest once a provider exists; push when built). Counting rule: one credit per `sent` log row; suppressed and failed-permanent rows do not burn credits; retries do not burn extra credits (a send is one row, one outcome).
- Secondary (PROPOSED): AI generation credits (template drafting) once fixed.
- Capacity/feature (PROPOSED, light): number of active rules/channels/schedules is not a cost; keep free. Optional paid: longer log retention, dedicated sender domain/identity (reputation isolation), higher ingest rate ceiling, SLA.
- Free tier (PROPOSED): a monthly block of email credits that covers a small business's operational alerts from Tasks/HR/Projects/Auction (size from cost inputs, not set here), in-app notifications always free (no marginal cost beyond DB), webhook/Slack delivery free within rate limits, all opt-out/preferences/security notices free.
- MUST stay free (PROPOSED): `security` notification type (sign-in alerts, password reset; `NotifyDispatcher::suppressionReason` already treats them as mandatory) and in-app delivery. Never charge to honour an opt-out.
- When the allocation runs out (PROPOSED): do not drop events. Persist the log as `queued_awaiting_credit` (new status) and keep in-app/Slack/webhook flowing; email falls back to in-app so the person still sees it; notify the company's owner once (in-app + one email) before and at zero; resume automatically after top-up; expire held messages after a short window (for example 72 h) with `failed: credits_exhausted` so ingest callers get a clear answer. Return a distinct status through the ingest response (`status` already has routed/no_rule/ignored/failed) so Tasks/HR etc. degrade without errors. Callers must not retry-storm: keep the 429 semantics out of the credits case.

## 7. Cross-platform dependencies and shared resources
- Depends on InfoDot for identity, company uuid (`teams.infodot_team_uuid`), entitlement and SSO (`app/Services/InfoDot/*`).
- Callers of the ingest API on origin/main (found by `git grep "ingest/events"`): Dot.Auction, Dot.Billing, Dot.Design, Dot.Dopemine, Dot.Ehail, Dot.Farms, Dot.HR, Dot.Projects, Dot.Sheet, Dot.Tasks, Dot.Tutor (each with its own `NotifyClient`). Billing and Farms are discontinued platforms (owner 2026-11-13): their callers are legacy. Dot.Finance has a Notify client (`app/Services/Integrations/NotifyClient.php`) that posts to the INBOUND webhook route (`/webhooks/{token}`), a different path. Dot.Notify itself is the only sender for these, so one comms wallet is natural.
- PROPOSED shared across the ecosystem: comms credits (email/SMS/push) as one wallet consumed through Notify; attribution per calling `platform` via `notify_inbound_events.platform`/`notify_logs` + rule. AI credits shared with other AI users. Seats from InfoDot.
- Platform-specific: event catalogue, rules, templates, channels.
- Double-charge hazard: Finance (`SalesDocumentMail`, `SalesReminderMail`, `FinanceNotificationMail`) and Files (`SuspiciousActivityAlert`) send their own email with `Mail::to()` and do NOT route through Notify. A shared comms wallet needs a decision: route all outbound email through Notify, or let each platform debit the wallet.

## 8. Gaps: meter, enforce, audit
- NEEDS DEVELOPMENT meter: a units ledger (company, platform, channel, units, log_id, at) written when a log becomes `sent`; or a nightly roll-up from `notify_logs` (acceptable for reporting, not enough for enforcement). No cost/units columns exist.
- NEEDS DEVELOPMENT enforce on the backend: a credit check in two places: `EventIngestor::route` (before creating a log) and manual/schedule creators (`DispatchDueSchedules`, `WebhookInboundController`), plus a final reserve/commit at `NotifyDispatcher::dispatch` so a race cannot overspend. Add entitlement check on `/api/ingest/*` and `/webhooks/*`.
- NEEDS DEVELOPMENT: fix `AiNotifyService` config key; record each AI call.
- NEEDS DEVELOPMENT: a real SMS/push provider before selling them; per-company sender identity; bounce/complaint feedback (the README says Notify "does not receive opens, clicks, bounces"; `delivered_at`/`opened_at` columns exist but are never set by a provider), so "sent" means "the channel accepted it" (the honest billing basis).
- NEEDS DEVELOPMENT audit: append-only credit ledger with reconciliation to `notify_logs` sent count, per-platform usage statements, retention/prune job for `notify_logs` and `notify_inbound_events`, abuse controls (a platform secret can currently send to any email address for a company: recipients are caller-supplied, so a compromised platform secret = spam via the shared sender).
- Housekeeping: working copy is stale; confirm with a fresh checkout before building.

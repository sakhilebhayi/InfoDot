# Dot.Agents: monetisation fact base (R-60, read-only audit)

Audit basis. The local checkout `/Users/sakhilebhayi/Dot/Dot.Agents` is `main` at 8be6097 (2026-09-19), 84 commits behind `origin/main` (3c2f10b, 2026-11-15, as of last fetch; I did not fetch). The R-37 rebuild (run engine, tools, knowledge, budgets) and R-50 entitlement exist only on `origin/main`. I audited `origin/main`; paths are repo-relative on it. Local-only differences are called out. Status tags: EXISTING, PROPOSED, NEEDS DEVELOPMENT.

## 1. What it does
- Lets a business hire AI agents as a workforce: a marketplace/catalogue, deployments per organisation, versioned agent setups (`AgentConfigVersion`), personas, departments, teams.
- Runs work through a run engine: a person chat, a schedule, a webhook, a business event or a workflow starts an `AgentRun`; steps call a model and granted tools; every step is journalled (`app/Actions/Agents/StartRunAction.php`, `app/Services/Runs/*`). EXISTING.
- Governance: approvals, autonomy levels, delusion-risk scoring, sandbox permissions, sealed and verifiable audit log, retention proposals. EXISTING.
- Knowledge bases (chunked, searched by terms) and governed memory; built-in tools (calculator, read web page, team directory, notify member, knowledge search, remember fact, delegate, save to Dot.Files) and custom API tools (`config/agents.php`, `app/Services/Tools/*`). EXISTING.
- Social channel agents (publish, inbox, sentiment, leads) with Facebook, Instagram, LinkedIn, X publishers. EXISTING code; the older ledger flagged social publishing and inbound ingestion as "unbuilt behind a page shell" (`docs/feature-completion-ledger.md`, September); re-verify before pricing it.

## 2. Most valuable features
- Scheduled and event-triggered agent runs (`agents:fire-schedules` every minute, `app/Services/Triggers/*`, `POST /api/hooks/{uuid}`). EXISTING. This is the "work done while you sleep" value.
- Approval desk with human-in-the-loop for risky actions. EXISTING.
- Knowledge-grounded answers (company documents) and memory with retention days. EXISTING.
- Run diagnostics and budget visibility (`app/Services/Insights/RunMetrics.php`, `BudgetWatch.php`, `RunDiagnosis.php`). EXISTING.
- Workflows with agents as steps (`AgentWorkflow`, `WorkflowExecution`, `WorkflowStep`, `POST /api/v1/workflows/{workflow}/runs`). EXISTING.
- Public API with tokens and abilities (`PersonalAccessToken` with `abilities`, `organization_id`; `routes/api.php`). EXISTING.

## 3. Existing limits, quotas, plans, entitlement, billing
- InfoDot entitlement (EXISTING): `app/Http/Middleware/EnsureCompanyEntitled.php` (alias `entitled`, `bootstrap/app.php:53`), Livewire persistent middleware (`app/Providers/AppServiceProvider.php:44`), `app/Services/InfoDot/EntitlementClient.php` (10-minute cache, 60 s on failure, unknown never blocks), `app/Support/EntitlementRefusal.php`, org sync `app/Actions/Ecosystem/SyncOrganizationsFromInfoDot.php`. Data export and erasure exempt (commit "data export and erasure are never blocked by an access state"). Boolean company-holds-platform gate only.
- Plans table and Stripe billing (EXISTING): `SubscriptionPlan` (name, slug, price, yearly_price, max_agents, max_users, max_departments, max_workflows, monthly_token_quota, features, limits, stripe ids, trial_days), `OrganizationSubscription`, `Invoice`, `StripeService`, `BillingController` (checkout, portal, webhook), `HandleStripeWebhookAction`, billing pages. Seeded plans in `database/seeders/AgentPlatformSeeder.php`: Starter 49 / Professional 149 / Enterprise 499 (currency-less decimals, Stripe, in practice USD; organisations default `currency => 'USD'`). Stripe is not the owner's payment route (ZAR, Paystack via Dot.Finance per R-50 ARCHITECTURE); Dot.Billing is discontinued. The Stripe path is therefore NEEDS DEVELOPMENT to replace, or switch off, before any monetisation.
- Enforced plan limits (EXISTING, verified):
  - `max_agents`: `app/Policies/AgentDeploymentPolicy.php::create` counts active+paused deployments; -1 means unlimited.
  - `monthly_token_quota`: `app/Services/AI/AgentQuotaGuard.php`, called from `StartRunAction::assertAllowed` (`StartRunAction.php`), so every run start (chat, schedule, webhook, workflow) is gated. Reads `usage_records` where metric_type = tokens for the month; 5-minute cached sum.
  - Per-business spend cap: `app/Services/Insights/BudgetWatch.php` with table `usage_budgets` (monthly_limit, warn_at_percent default 80, stop_at_limit). It sums `agent_runs.cost` for the month and refuses new runs when spent >= limit; emails payers once per month (`BudgetNotification`).
  - Per-run limits (default in `app/Services/Agents/AgentConfig.php`): 12 steps, 20 tool calls, 60000 tokens, cost 1.00, 900 seconds, 2 retries, concurrency 3; enforced between steps by `app/Services/Runs/RunGuard.php`.
  - Rate limits: `ai-execution` 10/min per user (`AppServiceProvider.php:77`), API 120/min and 60/min.
- NOT enforced (EXISTING gap, verified by grep): `max_users`, `max_departments`, `max_workflows` exist in the schema and plan seed but nothing reads them (only `max_agents` and `monthly_token_quota` appear in `app/`).
- Trial: new organisations get plan `starter`, status `trial`, 14 days (`app/Actions/Ecosystem/SyncOrganizationsFromInfoDot.php:99-103`). No code was found that ends the trial or downgrades after it; verify.

## 4. Resources consumed or stored, and where to count them
| Resource | Where it can be read today | Status |
|---|---|---|
| Model tokens in/out/cached, cost, by org, deployment, run, step, model | table `agent_usage_events` (`app/Models/AgentUsageEvent.php`, written by `UsageLedger::record`) | EXISTING |
| Token total per org per day | table `usage_records` (metric_type tokens, quantity, total_cost, model_used, recorded_date) | EXISTING |
| Cost per run, wasted cost | `agent_runs.cost`, `agent_runs.wasted_cost`, `tokens_used`, `steps_used`, `tool_calls_used` | EXISTING |
| Runs started, by trigger type, test vs live | `agent_runs` (trigger_type, is_test, status, started_at, finished_at) | EXISTING |
| Tool calls and outputs | `agent_run_steps`, `agent_effects` (outbox for external effects) | EXISTING |
| Active deployments (agents) | `agent_deployments` (status active/paused) | EXISTING |
| Knowledge storage | `knowledge_chunks`, `knowledge_articles`, `knowledge_bases` (text; indexed by terms, no embeddings in the rebuilt path) | EXISTING |
| Memory rows | `agent_memories` (retention via `agents:forget-expired`) | EXISTING |
| Approvals handled | `agent_approvals` | EXISTING |
| Seats | organisation members (`Membership`, org users) | EXISTING |
| Webhook/API calls | `webhook_deliveries`; API token use not counted | partial |
| Social posts/messages | `social_posts`, `social_messages` | EXISTING |
| Files saved to Dot.Files | `agent_effects` rows via `DotFilesClient` | EXISTING (indirect) |
| Emails sent (budget warnings, approvals, notifications) | not logged as a metered count | NEEDS DEVELOPMENT |
Important: cost is only known when a dated `model_prices` row exists (`UsageLedger`: "with no price on file it stays unknown"; `agent_usage_events.cost` is nullable). With no price, `cost` stays null, so per-run cost limits and the `BudgetWatch` cap do not bite, while token limits still do. Prices are entered by a person (`agents:set-price`, `app/Console/Commands/SetModelPrice.php`), in an unspecified currency (no currency column on `model_prices` or `usage_budgets`).
Background load (EXISTING, `routes/console.php`): sweep runs every minute, fire schedules every minute, seal audit every minute, immune-system check every 15 minutes, scorecards daily, retention, DWCA audit weekly, queue heartbeat.

## 5. Third-party services called
- AI/LLM (EXISTING, `app/Services/Models/PrismGateway.php`, `config/agents.php`): Anthropic (default `claude-sonnet-5-5`, plus opus and haiku), OpenAI (gpt-4o, gpt-4o-mini), Google Gemini. Fallback stays inside the same provider by design. A model only shows when its provider key exists. `AI_PROVIDER` defaults to a mock in config (`config/prism.php`), so an unconfigured host spends nothing.
- No other LLM call path was found on `origin/main` (no `OpenAI::` facade use under `app/`); the run engine is the single path, so the ledger sees all model spend. Confirm after the next fetch.
- Social APIs: Facebook/Instagram Graph, LinkedIn, X (OAuth per account; `config/services.php`). Cost: rate limits and app review, not fees.
- Stripe (billing). EXISTING, to be replaced by the ecosystem payment route.
- Dot.Files (signed POST, `DotFilesClient`), Dot.Memory (`ReportRunToDotMemory`, `DotMemoryClient`), InfoDot (identity, entitlement). EXISTING.
- `ReadWebPage` tool and custom API tools fetch arbitrary URLs (HTTP, 10 s timeout); outbound bandwidth only.
- Mail via the app mailer (budget warnings, approvals). No SMS.
- Cost drivers: LLM tokens (dominant), agent step count and retries, scheduled runs firing unattended, tool calls, long contexts from knowledge.

## 6. Candidate purchase units, free tier, exhaustion behaviour
- AI run credits (PROPOSED, credit). The platform already measures tokens and cost per run and per org per month, and caps per run. Best unit: a monthly credit pool consumed per model turn, weighted by model tier (haiku-class cheap, opus-class expensive), converted to ZAR at top-up time. Reason: tokens vary 50x between models, so a flat "per run" price would be unfair or loss-making.
- Active agents and concurrency (PROPOSED, capacity). `max_agents` already exists; sell extra active deployments and extra concurrent runs as capacity packs rather than a plan name.
- Scheduled and event triggers (PROPOSED, capacity). Unattended runs are where cost is least controlled; a bounded number of live schedules per business with extra packs.
- Knowledge storage (PROPOSED, resource): documents/chunks beyond a free amount; likely a shared storage credit with Dot.Files.
- Free tier (PROPOSED): a few agents, chat on a cheap model, a modest monthly credit pool, test runs, approvals, audit log, data export and erasure, governance pages. Governance and audit must stay free: they are the platform's trust story and cost almost nothing.
- Keep features ungated (PROPOSED): approvals, audit, retention, scorecards, security centre.
- When credits run out (PROPOSED, mostly EXISTING behaviour): `StartRunAction` already refuses a new run with a plain `RunRefused` message and `RunGuard` stops an in-flight run between steps, never mid-step. Keep that: finish or cleanly stop the current run, refuse new ones, keep chat history, knowledge, approvals and audit readable, and show "top up or wait until <reset date>". Paused deployments, not deleted ones. Schedules should skip, log "skipped: no credits" and not queue a backlog.

## 7. Cross-platform dependencies and shareable resources
- Depends on: InfoDot (identity, entitlement, org sync), Dot.Files (SaveToFiles), Dot.Memory (run reports), Dot.Analytics (consumes agent activity, per R-50 notes).
- Shareable (PROPOSED): AI credits (one wallet shared with Analytics and Forms), storage credits (knowledge documents, files), seats (InfoDot company membership), comms credits (email/SMS for approvals and notifications).
- Platform-specific (PROPOSED): active agent slots, concurrency, schedule count.

## 8. Gaps to meter, enforce and audit
1. NEEDS DEVELOPMENT: replace Stripe/USD plan billing with the ecosystem wallet (ZAR). `SubscriptionPlan` monetary fields, `OrganizationSubscription`, `StripeService`, the webhook action and the billing pages assume Stripe.
2. NEEDS DEVELOPMENT: enforce or remove the dead plan limits (`max_users`, `max_departments`, `max_workflows`) so the product is not advertising limits it does not apply.
3. NEEDS DEVELOPMENT: make cost always known. Seed `model_prices` for every model in `config/agents.php` with a dated price and source, and add a ZAR conversion; otherwise cost-based caps and any credit conversion silently do nothing. Add a currency to `model_prices` and `usage_budgets`.
4. NEEDS DEVELOPMENT: reservation before a run starts. `AgentQuotaGuard` reads a 5-minute cached sum and only checks "used >= limit" (a run can overshoot by up to its own `tokens` limit, default 60000, and concurrent runs can all pass the check). A debit-per-step against a wallet with an idempotency key (the `UsageLedger` event id is a natural key) is needed.
5. NEEDS DEVELOPMENT: entitlement is boolean and fails open (`EntitlementClient`). Spending needs fail-closed on debit.
6. NEEDS DEVELOPMENT: keep the single model path. Any future AI feature (social sentiment, replies) must go through `ModelGateway` and `UsageLedger`, or it escapes metering.
7. NEEDS DEVELOPMENT: email/notification send counts; trial expiry handling; a customer-facing usage page that reads `agent_usage_events` (a `UsageOverview` Livewire component exists, check what it shows).
8. NEEDS DEVELOPMENT: audit of credit movements. The sealed audit log (`agents:seal-audit`, `agents:verify-audit`) already supports tamper-evidence and can carry wallet events.

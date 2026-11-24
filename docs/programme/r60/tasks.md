# Dot.Tasks: monetisation audit (read-only, R-60)

Evidence base. The local working copy `/Users/sakhilebhayi/Dot/Dot.Tasks` is on `feat/reverb-realtime-core`, 11 commits behind origin. Everything below was read from `origin/main` fa83439 (2026-11-15) exported with `git archive` into the scratchpad (plus `bootstrap/app.php` read with `git show`). Paths are repo-relative. No real `.env` read. Tags: EXISTING / PROPOSED / NEEDS DEVELOPMENT.

## 1. What it does (core value)
- EXISTING: team task management on kanban boards and lists: tasks, subtasks, priorities, due dates, recurrence, labels, custom fields, statuses, dependencies, checklists, comments with @mentions, attachments (`app/Models/*`, `app/Livewire/Tasks/*`, tables from `database/migrations/2026_06_27_000001_create_tasks_tables.php` to `2026_11_08_000001_create_audit_events_table.php`).
- EXISTING: workload and planning: My tasks, day plan, focus ranking, workload check against `TASKS_WEEKLY_HOURS` (default 40) (`app/Services/DayPlanner.php`, `FocusRanking.php`, `WorkloadCheck.php`, `config/tasks.php`).
- EXISTING: automations: if-this-then-that task rules with delayed runs, executed every five minutes (`app/Services/Automations.php`, `AutomationCatalog.php`, `task_rules`, `task_rule_runs`, `tasks:run-rules`).
- EXISTING: approvals, escalation queue for tasks over 7 days overdue, reports and exports, audit trail kept 24 months (`TaskApprovals`, `DetectEscalationCandidates`, `TaskReports`, `ReportExportController`, `config/tasks.php` `audit_months`).
- EXISTING: AI task breakdown into 3 to 7 subtasks with time estimates; writes nothing until a person chooses (`app/Services/AiTaskBreakdownService.php`, `AiClient.php`).

## 2. Most valuable features (measurable business value)
- EXISTING: kanban + assignment + due dates + recurring tasks (daily operations; measurable as tasks created/completed/overdue via `DashboardFigures`, `DashboardStats`, `Reports`).
- EXISTING: automations and templates (time saved; countable as `task_rule_runs`).
- EXISTING: workload/overdue/escalation insight (managerial value; `WorkloadCheck`, `TaskEscalationProposal`).
- EXISTING: AI breakdown (convenience; cost-bearing).
- EXISTING: audit trail and restore of removed tasks (90-day retention, `REMOVED_RETENTION_DAYS`, `RemovedTaskService`, `RecordRestoration`).
- EXISTING: ecosystem links: files kept in Dot.Files, alerts through Dot.Notify, metrics to Dot.Analytics.

## 3. Existing limits, quotas, plans, entitlement checks, billing code
Entitlement consult (EXISTING):
- `app/Http/Middleware/EnsureCompanyEntitled.php` is appended to the whole `web` group in `bootstrap/app.php` (`$middleware->web(append: [UseTeamClock::class, EnsureCompanyEntitled::class])`). It uses `app/Services/InfoDot/CompanyEntitlement.php::of($teamUuid)` (URL from `config/ecosystem.php` `entitlement.url` or derived from the redeem URL; switch `TASKS_ENTITLEMENT_CHECK`, timeout `TASKS_ENTITLEMENT_TIMEOUT` 3 s). A clear "no" gives the plain refusal page + 403; no answer carries on (fail-open). `manageUrl()` points the person to InfoDot `/platforms/{key}`. The machine API and public pages are explicitly not covered (middleware docblock). Same boolean access model as Finance, Files and Notify; no numeric allowance is carried.
Existing limits (technical/safety, not commercial):
- AI: OFF until BOTH `ANTHROPIC_API_KEY` and `ANTHROPIC_MONTHLY_TOKEN_CAP` > 0 (`config/services.php`; default cap 0). `AiClient::overCap()` stops the month once logged tokens reach the cap. CRITICAL: the cap is "across the whole app" (config comment) and the sum has no company filter (`AiBreakdownLog::where('created_at' >= startOfMonth)->sum('tokens_used')`), so one company can exhaust it for everyone and nothing is per company. Max 7 subtasks, `max_tokens` 4000, `effort: low`, timeout 60 s.
- Attachments: 25 MB (`TaskAttachments::MAX_KB`), type allow-list (`TaskAttachments::TYPES`), stored on the local disk `task-files/{team}/{task}/` (`storeAs(..., 'local')`).
- Platform Files filing: 25 MB, fixed extension list (`app/Services/Files/FilesClient.php`).
- Retention: removed tasks 90 days then purged (`tasks:purge-removed`); audit 24 months pruned by `model:prune` (`routes/console.php`).
- Subtasks max 7 per AI call (`AiTaskBreakdownService::MAX_SUBTASKS`); automations batch per run 200 ready runs / 500 / 300 rows (`Automations.php`), performance guards only.
- Roles: owner/admin/editor/viewer (README "Roles"); seats are InfoDot's.
Plans/quotas/billing: NONE for boards, tasks, members, automations, storage or AI per company. No payment code in `app/`.

## 4. Resources consumed or stored, and where to count them today
| Resource | Where counted (EXISTING) |
|---|---|
| AI calls and tokens | `ai_breakdown_logs` (task_id, user_id, prompt, response, `tokens_used` input+output, created_at). Company = `task -> task_list.team_id` (no `team_id` column). Failed/unavailable calls are recorded with a bracketed marker and null tokens (`AiClient::record`), refusals record tokens used. Strong base for an AI credit ledger |
| Attachment storage | `task_attachments.size` (bytes) via `task -> task_list.team_id`; files in `storage/app/task-files/{team}/...` (path itself carries team id). Optional copy in Dot.Files (`task_attachments.file_uuid`) which counts against Files, not Tasks |
| Tasks / subtasks | `tasks` (+ soft deletes, archive) per `task_lists.team_id` |
| Boards | `task_lists` |
| Members/seats | `team_user` (InfoDot-licensed) |
| Automation executions | `task_rule_runs` (status waiting/...), `task_rules` |
| Comments, activity, history | `task_comments`, `task_activities`, `audit_events` (24-month prune) |
| Notification events sent to Notify | queue job `SendNotifyEvent` (no table; counted in Notify as `notify_inbound_events` by platform=tasks and team). Local in-app bell uses Laravel `notifications` |
| Report exports | `ReportExportController` (no usage row) |
| Realtime | Reverb broadcast (`TaskBoardUpdated`, `TaskEscalated`): no counter |
| Background jobs | `jobs` queue, scheduler (`routes/console.php`): rules every 5 min, due-soon daily |
All tenant data hangs off `task_lists.team_id` with a team scope (`app/Models/Scopes`).

## 5. Third-party services and cost drivers
- Anthropic API (per token). Default model in config is `claude-opus-5-5` (`config/services.php`): the most expensive tier of the family is the default, so each breakdown is the costliest per-token path in the four apps audited. `effort: low` and 4000 max tokens bound it. Cost driver: calls per user, prompt size (task title + description).
- Dot.Notify (internal; email cost lives there): events task.assigned, due_soon, escalated, mentioned (`app/Services/Notify/NotifyClient.php`, `SendNotifyEvent`).
- Dot.Files (internal storage) when a person chooses "keep in Dot.Files".
- Dot.Analytics daily snapshot (`app/Services/Analytics`), Reverb WebSockets, SMTP mailer (invitations).
- Cost drivers (PROPOSED reading): AI tokens (dominant variable cost); attachment disk (single local disk, shared host capacity) and backups; websocket connections; scheduler/queue load from automations; notification email via Notify.

## 6. Candidate purchase units and free tier
- Primary resource unit (PROPOSED): AI CREDITS (tokens or "breakdowns"), per company, drawn from a shared ecosystem AI wallet. Needed first because the existing global cap is the only governance and it is not fair across companies.
- Capacity units (PROPOSED): attachment storage GB (or route attachments to Dot.Files and let Files own the storage unit, avoiding two meters); automation runs per month (moderate cost, scheduler load), if usage shows abuse.
- Feature candidates (PROPOSED, use sparingly): advanced automations, custom fields/statuses/views beyond a free number, approvals, report exports, longer audit/removed retention. Core board/task/subtask/comment/assignment/recurrence/notification stay free.
- Free tier (PROPOSED): unlimited tasks and boards for a small business (they cost almost nothing to store), team collaboration, mentions, dependencies, due-soon and assignment notifications within the free comms block, a small monthly AI allowance (for example a handful of breakdowns, sized later from cost inputs) so the AI value can be felt, 90-day restore.
- Do not charge for seats here (seats are sold at InfoDot via licences; charging again would double bill).
- When AI allocation runs out (PROPOSED): the existing path already degrades correctly. `AiClient::ask()` returns `['ok'=>false,'reason'=>'over_cap'|'not_configured']`, `AiTaskBreakdownService::breakdown()` returns `source: 'unavailable'` with no canned subtasks, and the UI shows it without breaking the task. Add a new reason `no_credits` with a top-up link; manual subtask creation stays free.
- When storage runs out (PROPOSED): block only new uploads; keep download and delete; show remaining allowance. Prefer pointing to Dot.Files so there is one storage meter.

## 7. Cross-platform dependencies and shared resources
- InfoDot: identity, company uuid (`teams.infodot_team_uuid`), entitlement, membership (`app/Services/InfoDot/*`, `app/Actions/Ecosystem/*`).
- Dot.Files (EXISTING client `app/Services/Files/FilesClient.php`, `AttachmentFilings.php`): filing is manual and optional; the file stays on the task as well, so the same bytes can sit in two places (double storage if both are metered).
- Dot.Notify (EXISTING `NotifyClient`, `SendNotifyEvent`): Tasks is one of the largest event sources (4 catalogued events in `config/notify_events.php` of Notify).
- Dot.Analytics (EXISTING publisher).
- Shared across ecosystem (PROPOSED): AI credits (Tasks, Finance assistant/receipts, Notify template drafting all call Anthropic with separate keys and separate caps today), storage (Files), comms credits (Notify), seats (InfoDot).
- Platform-specific (PROPOSED): automation runs, boards, workload features.

## 8. Gaps: meter, enforce, audit
- NEEDS DEVELOPMENT meter: a per-company AI usage view. `ai_breakdown_logs` has the tokens but no `team_id`; add one or join through `tasks -> task_lists`. Capture model name and input/output tokens separately (today a single `tokens_used` sum); costs differ by model and by input vs output.
- NEEDS DEVELOPMENT enforce: replace the global `monthly_token_cap` check with per-company balance (reserve before the call, settle after) in `AiClient::ask`; keep the global cap as a safety fuse. Add attachment quota check in `TaskAttachments::store` and `AttachmentFilings::file`. Add entitlement/numeric-allowance consult (boolean today).
- NEEDS DEVELOPMENT: rate limit per user on the breakdown action; the Livewire call site is `app/Livewire/Tasks/TaskDetail.php` (only entry point found), so enforcement can sit in one place.
- NEEDS DEVELOPMENT audit: append-only usage/credit ledger shared with the wallet service; monthly statement; reconcile token sums with the Anthropic invoice; store the prompt only if privacy review allows (the log keeps full prompt and response text, which contains customer task content: a POPIA question before it is used for billing evidence).
- Risks to settle: default model is the premium tier (consider a cheaper default before charging), AI prompts include task descriptions sent to a third party, single local disk for attachments, and the stale local checkout.

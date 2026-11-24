# Dot.Projects monetisation audit (read-only)

IMPORTANT source note: the local checkout /Users/sakhilebhayi/Dot/Dot.Projects is on branch feat/reverb-realtime-core, 95 commits behind origin/main (last origin/main commit 2026-11-15). The same staleness applies to all five repos (HR 94 behind, Sheet 91, docs 40, Press 65 behind its checked-out branch). All findings below were read from `git archive origin/main` extracted to scratchpad (/private/tmp/claude-501/-Users-sakhilebhayi-Dot/e077f64e-f1ab-4cb2-b638-4c260e093867/scratchpad/r60/src/Dot.Projects), paths are repo-relative. Nothing in the repos was changed.
Labels: EXISTING (verified in origin/main code), PROPOSED, NEEDS DEVELOPMENT.

## 1. What it does
- EXISTING: Project and programme delivery for a business team: projects, milestones, tasks (board, list, schedule, calendar views: `app/Livewire/Projects/*`), dependencies, subtasks, labels, saved views.
- EXISTING: Governance registers: risks, issues, change control, decisions, stage gates, meetings, actions, status reports (`app/Livewire/Governance/*`, `app/Services/Governance.php`, `ChangeControl.php`, `Gates.php`, `StatusReports.php`).
- EXISTING: Money and time: timesheets with approval, person capacity/workload, budgets, cost items, labour cost, earned value (`app/Services/TimeTracking.php`, `Workload.php`, `CostLedger.php`, `LabourCost.php`, `EarnedValue.php`; migrations 2026_11_23..26).
- EXISTING: Portfolios, custom dashboards, report builder with CSV/XLSX/PDF export, automations (rules with approvals), templates (`app/Livewire/Portfolio`, `Dashboards`, `Reporting`, `Automations`, `Templates`).
- EXISTING: AI plan generation from a brief (`app/Services/AiProjectPlannerService.php`) and documents filed into Dot.Files (`app/Services/ProjectDocuments.php`).

## 2. Most valuable features (measurable value)
- Governance and audit trail (activity history `app/Models/Activity.php`, closure approval, change control): reduces rework and audit time.
- Time/cost/earned value (EXISTING): shows project profitability, the strongest "pay for outcome" feature.
- Automations (`app/Services/Automations.php`, `AutomationCatalog.php`, hourly `automations:sweep`): measurable hours saved, escalations.
- Report builder and exports (`app/Livewire/Reporting/Builder.php`, `app/Http/Controllers/ReportController.php`; throttle:exports 20/min).
- AI plan draft (saves planning time; only real marginal-cost feature).
- Project documents with version history in Dot.Files (`ProjectDocuments::add/replace`).
- Cross-platform outbox to Notify and Analytics (`app/Services/Integrations/Outbox.php`).

## 3. Existing limits, quotas, plans, entitlements, billing
- EXISTING InfoDot entitlement: `app/Http/Middleware/EnsureCompanyEntitled.php`, appended to the `web` group in `bootstrap/app.php:24`. It asks InfoDot via `app/Services/InfoDot/CompanyEntitlement.php` (GET `{infodot}/companies/{uuid}/platforms`, bearer `config('ecosystem.infodot.secret')`, header X-Dot-Platform) and reads ONLY a boolean `entitled` plus a `status` string for platform key `projects`. Cached 10 min (60 s on failure). Fails OPEN on any error ("carrying on"). Covers all signed-in routes and Livewire endpoints; personal teams use the session value stored at sign-in (`SyncTeamsFromInfoDot::PERSONAL_ENTITLED`). Refusal page: `resources/views/entitlement.refused`, words in `app/Support/EntitlementRefusal.php`. Disable switch: `ecosystem.infodot.entitlement_check`.
- So the current model is exactly "access on/off". There is NO quantity, plan name, seat cap, allocation or credit balance in the InfoDot answer or anywhere in the code. NEEDS DEVELOPMENT.
- EXISTING rate limits (abuse control, not commercial): `throttle:app` 300/min, `livewire` 600/min, `exports` 20/min, `downloads` 60/min (`app/Providers/AppServiceProvider.php`). AI plan: 10 per user per hour via RateLimiter key `ai-plan:{userId}` in `CreateProject::generatePlan` (cache-based, resets, not per team, not persisted).
- EXISTING size cap: document upload 25 MB per file (`app/Livewire/Projects/ProjectDocuments.php` `max:25600`).
- EXISTING retention settings chosen by owner/admin: activity history, read notices, automation runs (`app/Services/DataRetention.php`, `data:prune` nightly). Records are never pruned. Outbox rows pruned after 30 days.
- No billing/payment code in this repo (grep of origin/main). Discontinued platforms (billing, plug, farms) still listed in `config/ecosystem.php` registry.

## 4. Resources consumed or stored, and where to read a count today
| Resource | Where measurable now |
|---|---|
| AI plan calls, output tokens | `ai_plan_logs` (migration 2026_06_27_151620; model `AiPlanLog`): rows per successful real call with `user_id`, `project_id`, `tokens_used` = output tokens only. Input tokens, failures and example (mock) plans are NOT logged. |
| Projects, tasks, milestones | `projects.team_id`, `project_tasks`, `milestones` |
| Seats | `team_user` / `team_invitations` (Jetstream); `app/Livewire/Teams/TeamMemberManager.php`; teams linked to InfoDot via `teams.infodot_team_uuid` (migration 2026_10_20) |
| Time entries / timesheets | `time_entries`, `timesheets` (2026_11_23) |
| Budget and cost lines | `budget_lines`, `cost_items`, `budget_revisions` (2026_11_25) |
| Governance records | risks/issues/changes/decisions/gates/meetings/actions/reports tables |
| Documents (storage bytes) | `project_documents.size` and `mime` per file (bytes live in Dot.Files, not here): sum per team = measurable now |
| Automation runs | `automation_runs` (`app/Models/AutomationRun.php`) with status; the best "background job" count |
| Cross-platform calls | `integration_deliveries` (status, attempts, event) |
| Activity events | `activities` (2026_11_14), one row per change |
| Exports | not logged (only throttled) |
| Notifications | `notifications` table; all four notification classes send via `database` only (no email/SMS from this app); outbound to Dot.Notify via outbox |
| Reports saved/dashboards | `saved_reports`, `dashboards`, `dashboard_widgets` |

## 5. Third-party services and cost drivers
- Anthropic Messages API (model default claude-sonnet-4-6, `config/services.php`): real marginal cost per plan (<=1,500 output tokens).
- Dot.Files (internal) for document bytes: storage and egress cost sits there (`app/Services/Integrations/FilesClient.php`, `FILES_API_URL`).
- Dot.Notify (internal ingest; email/SMS cost sits there) (`NotifyClient`, `ForwardNoticesToNotify`).
- Dot.Analytics ingest (`AnalyticsClient`, daily `integrations:analytics-snapshots`).
- InfoDot (identity, entitlement).
- Self-hosted: PostgreSQL daily backup (`BackupPostgresDatabase`), Reverb websockets, PDF/XLSX export generation (CPU).
- Mail provider keys exist in config (Postmark/Resend/SES) but no notification uses mail.

## 6. Candidate purchase units (PROPOSED, no prices)
- Credit: AI plan drafts (1 credit each; cleanest metered unit, one choke point).
- Capacity: active projects per business, members (seats), document storage (GB, via Dot.Files shared wallet), automation runs per month, saved reports/dashboards, activity-history retention years.
- Feature: report export formats and portfolio/earned-value dashboards could be a capacity-bundle, but cost us little; prefer not to meter.
- Keep FREE (core value, protects trust): projects/tasks/milestones/board, comments, time logging and approval, governance registers (risks, issues, decisions), notifications, CSV time export, basic templates, the labelled example plan (no AI), a small monthly AI allowance, a few active projects and seats, 1 GB-class document allowance, 90-180 day default retention for run history.
- On exhaustion (PROPOSED): AI plan returns the existing labelled example plan (code path exists: `AiProjectPlannerService::examplePlan`, `CreateProject` already creates the project before planning); new project / new invite / new upload refused with a clear message and a top-up link; existing projects stay fully readable and editable; automation rules pause (not delete) and a banner says so; never block time logging, comments, approvals or exports of the business's own data.

## 7. Cross-platform dependencies and shareable resources
- EXISTING dependencies: InfoDot (identity, entitlement), Dot.Files (documents), Dot.Notify (notices), Dot.Analytics (snapshots). Verified in `app/Services/Integrations/*` and `app/Services/InfoDot/*`.
- Shareable across the ecosystem (PROPOSED): storage (Dot.Files owns bytes; best as one shared storage allocation), AI credits (one wallet), seats (an InfoDot organisation licence), comms credits (Notify owns delivery).
- Platform-specific: active projects, automation runs, report capacity.

## 8. Gaps (NEEDS DEVELOPMENT)
- Entitlement contract: InfoDot answer must return allocations (quantities, balances), not only `entitled`; `CompanyEntitlement` currently discards everything else. Today fails open: decide fail-open vs fail-safe per resource (credits must fail closed or use local balance).
- Metering: a local append-only `usage_events` ledger (team, user, resource, quantity, idempotency key, model, input and output tokens, outcome). `ai_plan_logs` is insufficient (output tokens only, successes only, mock not marked).
- Enforcement on the backend: AI check and reserve in `AiProjectPlannerService::generatePlan` (not just the UI/RateLimiter, which is a cache key, not durable and per user); project-creation gate via `ProjectPolicy::create` (already used by route middleware `can:create`) and by `CreateProject`; seat gate in `TeamMemberManager` and Jetstream invite actions; upload gate in `ProjectDocuments::add`/`replace`.
- Storage: Projects stores only `project_documents.size`; per-business byte totals must be reconciled with Dot.Files, which is authoritative.
- Audit: reuse `activities` for human-visible trail; add allocation/consumption/refund ledger with reconciliation job against InfoDot; admin report of usage; tests.
- Run the AI call off the request (it is a synchronous 30 s HTTP call inside a Livewire action) before selling it.

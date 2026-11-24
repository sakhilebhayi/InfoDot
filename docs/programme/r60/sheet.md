# Dot.Sheet monetisation audit (read-only)

Source note: local checkout /Users/sakhilebhayi/Dot/Dot.Sheet is `main` 91 commits behind origin/main (2026-11-15). Findings come from origin/main via `git archive`, extracted to /private/tmp/claude-501/-Users-sakhilebhayi-Dot/e077f64e-f1ab-4cb2-b638-4c260e093867/scratchpad/r60/src/Dot.Sheet. Paths are repo-relative. Labels: EXISTING / PROPOSED / NEEDS DEVELOPMENT.

## 1. What it does
- EXISTING: Online workbooks with multiple tabs, formulas (own engine `app/Services/Formula/*`), formatting, charts, pivots, named ranges, comments, protections, merges, filters (models `Spreadsheet`, `SheetTab`, `Cell`, `ChartConfig`, `PivotTable`, `NamedRange`, `SheetProtection`).
- EXISTING: Import/export: CSV, TSV, XLSX, XLS, ODS in; XLSX, ODS, PDF, CSV, TSV out (`SpreadsheetController`, `app/Services/WorkbookImporter.php`, `WorkbookWriter.php`).
- EXISTING: Collaboration and governance: sharing with permissions view/comment/edit/admin (`app/Services/SheetSharing.php`), invitations by link (no email sent), approvals/sealing (`WorkbookApprovals`, `RefuseSealedWorkbook`), version history and restore (`SheetVersions`), audit events (`audit_events`).
- EXISTING: Workflow, macros and scripts, both feature-flagged off by default (`config/sheet.php` `scripts_enabled`, `structure_ops_enabled`).
- EXISTING: AI assistant: ask about a sheet with traceable facts/engine-computed sums, formula from words, analysis, NL query (`app/Services/SheetAssistant.php`, `AiService.php`, `app/Livewire/Ai*.php`). Filing of a workbook into Dot.Files as XLSX with versions (`app/Services/Integrations/WorkbookFilings.php`).

## 2. Most valuable features
- Import/export fidelity with Excel files (the switching reason for small businesses).
- Formula engine plus formula doctor/explainer/from-words (`app/Services/Formula/FormulaDoctor.php`, `FormulaExplainer.php`, `FormulaFromWords.php`): deterministic, low marginal cost.
- Real-time shared editing and version history (`app/Services/SheetTogether.php`, `SheetVersions.php`, `spreadsheet_versions`).
- AI assistant that is checked against the engine (`SheetAssistant`) - the one AI-cost feature.
- Pivot tables, charts, dashboards (`PivotTableService`, `DashboardBuilder`, `SheetCharts`).
- Approvals/sealing, audit trail and protections: governance value.
- Filing to Dot.Files with versions (`WorkbookFilings`), cross-platform value.
- Templates (`TemplateCatalogue`, `IdeaStarts`).

## 3. Existing limits, quotas, plans, entitlements, billing
- EXISTING InfoDot entitlement: `app/Http/Middleware/EnsureCompanyEntitled.php` + `app/Services/InfoDot/CompanyEntitlement.php`. Same pattern as Projects: boolean "company holds Dot.Sheet" read from InfoDot (R-50), cached; only a clear NO stops a request; personal and unlinked teams and any failure carry on (fail open). Access on/off only. No quantities exchanged.
- EXISTING AI allowance (the only quota-like code found across all five repos): `config/ai.php` `monthly_tokens_per_business` (env `AI_MONTHLY_TOKENS_PER_BUSINESS`, default 500,000; 0 = off). Enforced in `SheetAssistant::ask` through `tokensLeft()` (sums `ai_prompts.context.tokens` for all workbooks of the team, or of the owner when no team, from the start of the month; shows "used its AI allowance for the month"). Limits of the implementation: (a) the other three AI entry points (`AiFormulaModal`, `AiAnalysisPanel`, `AiNaturalLanguageQuery`, mounted in `resources/views/livewire/show-spreadsheet.blade.php` lines 245, 958, 968) call `AiService::generateResponse` directly, do NOT check `tokensLeft` and `AiFormulaModal::storeAiPrompt` records no tokens; (b) token total is a JSON field summed in PHP (`get(['context'])->sum`), not an indexed column; (c) the check and the call are not atomic (race over the cap); (d) failed calls are not logged; (e) the cap is one env value for everyone, not per plan.
- EXISTING AI rate limits: per-user cache counter `ai_rate_limit_{userId}` hourly (config `ai.rate_limit`, default 1000/h), response cache 1 h (cached answers return without cost) (`AiService::checkRateLimit`, `generateResponse`); route throttle `throttle:20,1` on `/ask`.
- EXISTING size limits (`config/sheet.php`): export excel max 50,000 cells, duplicate max 100,000 cells, import whole max 60,000 cells, import inline 6,000 cells with queued chunks of 5,000 (`ImportRowsChunkJob`), import file 10 MB (`SpreadsheetController` `max:10240`), audit keep 730 days.
- EXISTING route throttles: imports 30/min, exports 20/min, duplicate 20/min, filing 10/min (`routes/web.php`).
- No plans, seats, subscriptions or billing code. `EnsurePlatformOperator` middleware exists for operator pages (not billing).

## 4. Resources consumed or stored and measurable counts
| Resource | Where measurable today |
|---|---|
| AI tokens and calls | `ai_prompts` (user_id, spreadsheet_id, prompt, response, context json, created_at). `context.tokens` only for assistant calls (input + output). Formula modal rows have no tokens; the analysis/NLQ components write no row. |
| Workbooks | `spreadsheets` (team_id, owner_id) |
| Cells (storage proxy) | `cells` (one row per non-empty cell: `spreadsheet_id`, `sheet_tab_id`, raw/computed/formula/formatting). `COUNT(*)` per team is the best storage metric. |
| Tabs, charts, pivots | `sheet_tabs`, `chart_configs`, `pivot_tables` |
| Version snapshots (heavy storage) | `spreadsheet_versions.cells_snapshot` JSON; pruned by `sheet:prune-versions` (daily 03:20: keeps newest automatic copies; user-made copies kept) |
| Cell history | `cell_history` (per edit) |
| Collaborators / seats | `team_user`, `spreadsheet_user` (per-workbook shares), `spreadsheet_invitations` |
| Audit | `audit_events` (pruned `sheet:prune-audit` weekly, 730 d) |
| Background jobs | `jobs` queue: `ImportRowsChunkJob`, `BulkPasteChunkJob`, `WorkOutSheet` (recalculation), `QueueHeartbeat`; no per-team job log |
| Exports | not recorded (throttle only); `sheet:prune-exports` removes temp files |
| Filing to Dot.Files | `workbook_filings` (spreadsheet -> Files document) |
| Notifications / Notify calls | `integration_deliveries` (outbox: notify, analytics), `notifications` |
| Workflow/macros runs | `workflow_execution_logs` (feature-flagged off by default) |

## 5. Third-party services and cost drivers
- AI provider chosen by `AI_PROVIDER`: ollama (default, self-hosted), openai, anthropic (`config/ai.php`; default anthropic model `claude-sonnet-5-5`). `isLive()` is false for mock/demo or missing key. Marginal cost = tokens (max_tokens 2000; prompts carry sheet context, so input can be large).
- Dot.Files (workbook filing), Dot.Notify (notices), Dot.Analytics (snapshots), InfoDot (identity, entitlement) via `app/Services/Integrations/*`.
- Own infrastructure: database (cells and version JSON dominate), queue workers (imports, recalculation), Redis/Horizon per `docs/redis-horizon-checklist.md`, websockets/pulse (`pulse_seconds` 20), PDF generation.
- No email/SMS: sharing invitations are links, "nothing is sent by email" (`SheetSharing`).

## 6. Candidate purchase units (PROPOSED, no prices)
- Credit: AI tokens or AI "assistant actions" per business per month. Already almost implemented (see s3); convert tokens to credits with a conversion rate set by owner after cost inputs.
- Capacity: stored cells (and version history depth), number of workbooks, collaborators per workbook/team, import/export size ceilings (the existing cell limits are natural tiers), retention of version history, file-to-Dot.Files storage.
- Feature: scripts/macros/workflows (flag off today) and approval sealing could be capacity-bundled; formula engine, pivots, charts should stay free as they cost nothing per use.
- Keep FREE: creating/editing workbooks within a generous cell allowance, formulas, formatting, charts, pivots, import/export of CSV/XLSX under current caps, sharing, comments, version history for a short window, the formula doctor/explainer/engine-based "Check this table" which works without AI (`SheetAssistant` off text), a small monthly AI allowance.
- On exhaustion (PROPOSED): AI exhaustion already degrades correctly in the assistant (returns 'used its AI allowance', everything else keeps working); apply the same to the other three AI entry points. Storage exhaustion: stop creating new workbooks/imports above allocation and make existing workbooks read/edit-in-place; offer download/export (never block export of own data). Older version snapshots can be thinned rather than deleted first.

## 7. Cross-platform dependencies and shareable resources
- EXISTING: Dot.Files for filing workbooks (`FilesClient`, `config/services.php` files), Notify, Analytics, InfoDot.
- Shareable (PROPOSED): AI credits (one wallet across Projects, Sheet, docs, Press), storage (filed XLSX copies), seats (InfoDot licence), comms credits (Notify).
- Platform-specific: stored cells, workbook count, recalculation/import compute.
- Overlap with Dot.Finance and Dot.Analytics possible (spreadsheets as data source) - no code coupling found.

## 8. Gaps (NEEDS DEVELOPMENT)
- Single AI choke point: move the cap check, reservation and logging into `AiService::generateResponse` (used by all four entry points) with atomic reservation (e.g. DB row lock or counter table) and record input/output tokens, model, outcome, even for failures; keep `ai_prompts` for content, add a usage table for counts.
- Per-team allowance from InfoDot instead of one global env value; entitlement client must read quantities.
- Storage metering: scheduled job counting `cells` and version snapshot bytes per team (nothing computes this); add enforcement at `SpreadsheetController::store/import/fromFile`, `WorkbookImporter`, `BulkPasteChunkJob`, `SheetVersions` creation.
- Seats: enforcement at invitation and team-add paths.
- Audit: extend `audit_events` or add a ledger for allocation, consumption, refund and admin adjustment; reconciliation against InfoDot.
- Prove no free-tier bypass: caches (`AiService` response cache returns answers without ledger entries: decide whether cache hits cost credits).

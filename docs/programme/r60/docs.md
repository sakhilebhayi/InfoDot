# Dot.docs monetisation audit (read-only)

Source note: local checkout /Users/sakhilebhayi/Dot/Dot.docs is `main` 40 commits behind origin/main (2026-11-15). Findings come from origin/main via `git archive` extracted to /private/tmp/claude-501/-Users-sakhilebhayi-Dot/e077f64e-f1ab-4cb2-b638-4c260e093867/scratchpad/r60/src/Dot.docs. Paths are repo-relative. Labels: EXISTING / PROPOSED / NEEDS DEVELOPMENT.

## 1. What it does
- EXISTING: Collaborative rich-text documents on a TipTap editor with canonical `content_json` (`app/Documents/Schema`, `resources/js/editor`): comments with mentions, version history with diffs, presence, templates, brand kits/styles, tags, folders (`documents`, `document_versions`, `comments`, `document_templates`, `brand_kits`, `document_styles`, `tags`, `folders`).
- EXISTING: Sharing: team collaborators, public share links with password and expiry (`documents.is_public`, `share_password`, `share_expires_at`; routes `/shared/{uuid}`), published pages `/d/{slug}` (`PublishedDocumentController`).
- EXISTING: Export/import: PDF, Word, HTML, Markdown (`app/Documents/Export`, `Import`, `DocumentExportController`, `/documents/{uuid}/export/{format}`), "save to Files" export.
- EXISTING: AI assistant: slash commands, grammar, summarise, continue, tone, translate, outline, custom commands, chat rail (`app/Services/AiService.php`, `app/Livewire/Documents/AiAssistant.php`, `AiChat.php`, `app/Ai/*`).
- EXISTING: A shared files tree (folders, uploads, `files`, `objects`, `tree_folders`) used by both Docs and Dot.Files (`app/Files/FilesService.php`), webhooks on save/export (`document_webhooks`, `app/Services/WebhookService.php`), audit log (`audit_logs`, `app/Audit/AuditLogger.php`).

## 2. Most valuable features
- Real-time co-editing with stale-save refusal and version history (documents as business records).
- Inline comments with email notification (`CommentEmailNotification`).
- Branded PDF/Word export (brand kits) and templates: lead use for small businesses (quotes, policies, contracts).
- AI writing assist: highest marginal-cost feature, measurable usage.
- Public share/publish links (reach, but also abuse surface).
- Files tree with document registration into InfoDot (`app/Services/InfoDot/ResourceRegistry.php`, `documents:register-resources`): pointer only, never content.
- Audit log and webhooks for integration.

## 3. Existing limits, quotas, plans, entitlements, billing
- EXISTING InfoDot entitlement: `app/Http/Middleware/EnsureCompanyEntitled.php` with `app/Services/InfoDot/EntitlementClient.php` (platform key `docs`, `config/ecosystem.php` 'infodot' block). Same R-50 pattern: boolean `entitled` and a `status` string per company, cached ten minutes, null on failure (carry on). `EntitlementRefusal` page. Access on/off only; no quantities.
- EXISTING AI spend guard (the most mature metering in the five repos): `app/Ai/Guard.php` runs before every real model call in `App\Ai\Client` (lines 46-66, 104). Rules from `config/ai.php`: `AI_ENABLED` kill switch; `AI_DAILY_TOKEN_BUDGET` global (default 2,000,000 per UTC day); `AI_DAILY_TOKEN_BUDGET_PER_USER` (default 300,000 per UTC day). Mock/none provider rows are excluded. Refusal throws `AiUnavailable` with a reason (switched off, global budget, user budget).
- EXISTING AI metering: `app/Ai/Usage.php` writes one row per call to `ai_model_usage` (migration 2026_09_07_000008): `team_id`, `user_id`, `document_id`, `provider`, `model`, `operation`, `input_tokens`, `output_tokens`, `cache_read_tokens`, `cost_usd` (from placeholder rates `config/ai.php pricing_per_million`, "illustrative"), `latency_ms`, `fallback_used`, `created_at`. This is close to a ready-made consumption ledger. Gaps: it is not idempotent, not a reservation (check-then-call race; cap is checked before and totalled after), `team_id` is the current team of the acting user (nullable), no monthly or per-team cap exists (only daily global and per user), no balance concept.
- EXISTING separate hourly limit: `AiService::checkRateLimit` 20 requests per user per hour via RateLimiter (`config/ai.php rate_limit_per_hour`), called from `AiAssistant` and `AiChat`.
- EXISTING upload limits: general files 20 MB (`FileUploadController::MAX_KILOBYTES 20480`), document images 4 MB (`DocumentImageController`). Published-document unlock throttled (`published-unlock`).
- EXISTING retention/pruning: sync metrics prune daily; failed jobs 7 days. No document or version retention policy found (versions kept: `document_versions.content_snapshot` per version).
- No plans, seats, subscription or billing code.

## 4. Resources consumed or stored, and where to read a count
| Resource | Where measurable today |
|---|---|
| AI calls, tokens, model, USD cost, latency | `ai_model_usage` (per call; best in the ecosystem); also `ai_suggestions` rows |
| Documents (count, word count) | `documents` (`team_id`, `word_count`, `slug`) |
| Document versions (storage) | `document_versions.content_snapshot` longText per version |
| Stored files (bytes) | `files.size`, `files.team_id`, path on disk `files` (local `storage/app/files` or `FILES_DISK`; `config/filesystems.php`). Same tree/seam as Dot.Files (`FilesService`) so quota here equals storage quota there if they share the disk. |
| Seats/collaborators | `team_user`, `team_invitations`, `document_collaborators` |
| Email sent | Team invitations `Mail::to` (`app/Actions/Jetstream/InviteTeamMember.php`) and comment emails (`CommentEmailNotification` channel mail, queue `mail`); no counter table. `notifications` table holds the in-app ones. |
| Public views/exports | not recorded (only rate limit); exports and webhook firing are not counted |
| Webhook calls | `document_webhooks` config exists; delivery count not stored |
| Presence/sync traffic | `user_presences`, `document_presences`, `sync_metrics` (editor polls every few seconds, `app/Services/SyncMetrics.php`); a capacity/cost driver |
| Audit | `audit_logs` |
| Background jobs | scheduled `queue:work` every minute (`routes/console.php:28`), `QueueHeartbeat`; no job ledger |

## 5. Third-party services and cost drivers
- AI: Anthropic primary (`ANTHROPIC_API_KEY`; models draft/compose/quick configurable: sonnet-5 / opus-5 / haiku-4-5 in `config/ai.php`), OpenAI gpt-4o as failover (`failover` list); provider default `mock`. Opus compose is the expensive path.
- Mail provider for invitations and comment emails (`config/mail.php`).
- InfoDot (identity, entitlement, resource registration), Dot.Files-shared tree (same seam).
- Own infra: SQLite backups (`BackupSqliteDatabase`), Reverb config present, polling sync every 1-2 s (`routes/web.php` comment line 168) is a CPU and request-volume driver as co-editing grows; PDF rendering.

## 6. Candidate purchase units (PROPOSED, no prices)
- Credit: AI actions measured in tokens or normalised credits by model role (quick vs draft vs compose weights, since cost differs ~15x); this table already supports it.
- Capacity: stored documents, version history depth/retention, stored file GB (shared storage), collaborators/seats per team, published/public pages count, webhooks count.
- Feature: brand kits, advanced export, publishing, webhooks could be capacity-gated; most cost nothing per use.
- Keep FREE: creating/editing documents, comments, version history for a recent window, export to PDF/Word/Markdown of own content, templates, reading shared documents, a small monthly AI allowance on the cheap `quick` role, invitations to a few collaborators. Never charge to read or export own documents.
- On exhaustion (PROPOSED): AI refuses with `AiUnavailable` (already designed: editor carries on, message shown) and offers top-up; storage exhaustion blocks new uploads/image insertion only, existing docs stay editable; version capture could thin older snapshots instead of failing a save; public/published pages already published stay online (do not take down a customer's live page) but new publishes are blocked until the allocation is restored.

## 7. Cross-platform dependencies and shareable resources
- EXISTING: InfoDot SSO, entitlement and resource registry; Dot.Files shares the tree/seam and disk (`FilesService`); Notify not used here (Docs sends its own mail).
- Shareable (PROPOSED): storage (the `files` tree is already the common substrate, so a storage allocation naturally spans Docs, Files, Projects documents and Sheet filings), AI credits (one wallet; Docs and Sheet and Press and Projects all call Anthropic), comms credits (comment emails and invitations would move to Notify to share a wallet), seats (InfoDot).
- Platform-specific: document and version capacity, published pages, co-editing capacity.

## 8. Gaps (NEEDS DEVELOPMENT)
- Convert `Guard` from daily-per-user into a per-team monthly allowance with balance read from InfoDot or a local wallet; add atomic reservation before the call and settlement after (today check-then-write can overshoot).
- Make `ai_model_usage` the ledger of record: add `team_id` guarantees (never null for billable use), idempotency key, request id, outcome (success, refused, failed) and a cost-credit column, and link to the allocation consumed. Replace placeholder `pricing_per_million` with real contract rates (owner cost input).
- Unify the two limiters (`AiService::checkRateLimit` hourly and `Guard` daily) and cover every AI call path (verify `AiChat`, slash commands, custom commands all go through `Client`).
- Storage metering per team (`SUM(files.size)` + version snapshot bytes; nothing computes it) and enforcement in `FileUploadController`, `DocumentImageController`, `FilesService::put`, version creation, import.
- Email metering: wrap `Mail::to` and mail notifications in a counted channel, or route to Notify.
- Seat enforcement in `InviteTeamMember`.
- Entitlement client must carry quantities; define fail-open vs fail-closed per resource.
- Admin audit trail for adjustments; reconciliation with InfoDot; tests. `task-list.md` Phase 10 tests are still unchecked per `wiki.md` (test coverage needed before charging).

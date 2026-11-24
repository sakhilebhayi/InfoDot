# Dot.Forms: monetisation fact base (R-60, read-only audit)

Audit basis. The local checkout `/Users/sakhilebhayi/Dot/Dot.Forms` is on feature branch `chore/signed-in-vite` (6638c39, 2026-10-18). The current default is `origin/main` (5c64a85, 2026-11-15, as of last fetch; I did not fetch), 74 commits ahead of local `main`. The R-30 rebuild (templates, submission desk, embeds, entitlement) exists only there. I audited `origin/main`; paths are repo-relative. Status tags: EXISTING, PROPOSED, NEEDS DEVELOPMENT.

## 1. What it does
- Team-scoped form builder: field types (text, email, number, textarea, select, radio, checkbox, date, file), per-field rules, conditional visibility, form versions with revert (`app/Livewire/Forms/Builder.php`, `app/Services/FormFieldVisibilityEvaluator.php`, `form_versions`). EXISTING.
- Public forms at `/forms/{slug}` that anyone can submit, with a honeypot, minimum-submit-time check, per-IP rate limit and file uploads up to 10 MB (`app/Livewire/Forms/PublicView.php`). Embeddable (`app/Http/Middleware/AllowFormEmbedding.php`). EXISTING.
- Submission desk: status (new and so on), assignee, internal note, decision, decided_by (migrations `2026_10_31_170000_add_desk_columns_to_form_submissions_table.php`, `2026_10_31_171749_add_r30_leftover_columns.php`), CSV/Excel export (`app/Exports/FormSubmissionsExport.php`), start and view counts for funnel (`forms.views_count`, `starts_count`). EXISTING.
- Master templates (built-in South African set in `app/Support/FormTemplates.php`, team templates in `form_templates`). EXISTING.
- Delivery of submissions to webhooks, Slack, Zapier, Make, CRM (`app/Services/FormSubmissionIntegrationDispatcher.php`, `form_integrations` with a propose/approve flow). EXISTING.

## 2. Most valuable features
- Public form collection with notification to the owner (`NewFormSubmissionNotification`, queued mail). EXISTING.
- Submission desk plus export: turns a form into a lightweight work queue. EXISTING.
- Integrations to Slack, Zapier, Make and CRMs: workflow value, cheap to run. EXISTING.
- Templates: fastest path to value for a small business. EXISTING.
- AI form generation from a prompt (`app/Services/Ai/AiFormGenerator.php`): the only real LLM feature, with a rule-based fallback when no key. EXISTING.
- The "AI" analytics, field suggestions and CRM field mapper are rule-based (no model call): `AiSubmissionAnalyzer`, `AiFieldSuggestionEngine`, `AiCrmFieldMapper` have no HTTP or provider code. EXISTING, verified by reading them. Do not price them as AI.

## 3. Existing limits, quotas, plans, entitlement, billing
- InfoDot entitlement (EXISTING): `app/Http/Middleware/EnsureCompanyEntitled.php` on the signed-in route group (`routes/web.php:64`), Livewire persistent middleware (`app/Providers/AppServiceProvider.php:28`), `app/Services/InfoDot/EntitlementClient.php` (10-minute cache, unknown never blocks), `app/Support/EntitlementRefusal.php`, team sync `app/Actions/Ecosystem/SyncTeamsFromInfoDot.php`, tenant link `teams.infodot_team_uuid` (migration `2026_10_20_000001_add_infodot_team_uuid_to_teams_table.php`). Not applied to the public form: a respondent is never asked about the company behind it (docblock). Boolean gate only.
- Team size cap (EXISTING): `config/dotforms.php` `teams.max_members` default 10 (`DOTFORMS_TEAM_MAX_MEMBERS`), enforced in `app/Actions/Jetstream/AddTeamMember.php:76` and `InviteTeamMember.php:82`.
- Submission rate limit (EXISTING): 10 submissions per minute per form and IP (`PublicView.php:219-221`, key `form-submit:{form}:{ip}`, `RateLimiter::hit(..., 60)`; a separate `form-unlock` limit at line 137 guards password-protected forms), plus min-submit seconds (`DOTFORMS_MIN_SUBMIT_SECONDS`, default 2).
- Upload cap (EXISTING): 10240 KB per file (`PublicView.php`), disk from `DOTFORMS_UPLOAD_DISK` (default `public`).
- Permissions: team role permissions `forms:create`, `forms:edit`, submissions view (`app/Policies/TeamPolicy.php`).
- Plans, quotas on forms or submissions, credits, billing: none (EXISTING absence). There is no cap on the number of forms, fields, submissions, storage, or AI generations.
- AI builder has no rate limit or usage record (NEEDS DEVELOPMENT): `app/Livewire/Forms/AiBuilder.php` dispatches `GenerateAiFormBlueprintJob` synchronously (`dispatchSync`), so an OpenAI call runs inside the web request (20 s timeout) with only a `canCreateForm` authorisation, and no per-team count or limit.

## 4. Resources consumed or stored, and where to count them
| Resource | Where it can be read today | Status |
|---|---|---|
| Forms per team | `forms` (team_id, is_published, archived_at) | EXISTING |
| Fields per form | `form_fields` | EXISTING |
| Submissions (rows) per form/team/month | `form_submissions` (form_id, data JSON, submitted_at, ip_address, status, assigned_to, decision, client_token) | EXISTING |
| Submission data size | length of `form_submissions.data` | EXISTING (derivable) |
| Uploaded file storage | files on the upload disk; paths inside `form_submissions.data`; no size column | NEEDS DEVELOPMENT to sum bytes |
| Views and starts | `forms.views_count`, `starts_count` | EXISTING |
| Form versions kept | `form_versions` | EXISTING |
| Integrations configured | `form_integrations` (type, url, status) | EXISTING |
| Webhook deliveries made | no log; `FormSubmissionIntegrationDispatcher` fires `Http::timeout(8)->post` and discards the result | NEEDS DEVELOPMENT |
| Notification emails sent | queued mail via `NewFormSubmissionNotification`; no send log | NEEDS DEVELOPMENT |
| AI form generations | `ai_suggestions` rows are written by the AI builder after a build (`AiBuilder.php:142`) and by the rule-based screens (`AiFieldSuggestion.php:72,97`, `AiAnalytics.php:52`); no tokens, no provider, and no distinction between an OpenAI build and the rule-based fallback | partial; NEEDS DEVELOPMENT for a true count |
| Seats | `team_user` | EXISTING |
Scheduler (EXISTING, `routes/console.php`): `forms:close-expired`, scheduler heartbeat, queue heartbeat every 5 minutes, nightly SQLite backup. Database is SQLite (`.env.example` `DB_CONNECTION=sqlite`), queue is the database driver, mail defaults to `log` in the example file (check production, per the platform notes mail is SMTP via the InfoDot no-reply address).

## 5. Third-party services called
- OpenAI chat completions, model from `OPENAI_MODEL` default `gpt-4.1-mini`, only in `AiFormGenerator::generateWithOpenAi` (EXISTING). Cheap per call. No Anthropic path.
- Outbound webhooks to customer-chosen URLs, guarded by an SSRF check (`app/Support/SsrfGuard.php`) and no redirects. EXISTING.
- InfoDot identity/entitlement (HTTP). EXISTING.
- Laravel Reverb (websocket) config exists (`config/reverb.php`); Reverb is a platform cost if live.
- Mail (SMTP) for submission notifications and team invitations. No SMS.
- Cost drivers: submission volume (database rows, SQLite file growth), file uploads (disk, up to 10 MB each, public disk by default), outbound webhook traffic, email, and the occasional LLM call. LLM spend is tiny compared with Agents or Analytics.

## 6. Candidate purchase units, free tier, exhaustion behaviour
- Submission capacity (PROPOSED, capacity): monthly submissions received, per business. Reason: it is the value unit a small business understands ("responses"), it maps to a table we can count today, and it tracks real storage and mail cost. Public respondents must never see a billing wall.
- File storage (PROPOSED, resource, shareable): bytes of uploaded files, ideally the same credit as Dot.Files storage. NEEDS DEVELOPMENT to measure.
- Active published forms (PROPOSED, capacity): a generous free count; extra forms as capacity.
- Notification and delivery comms (PROPOSED, comms credits): emails and webhook/CRM deliveries beyond a free amount; shared with other platforms' mail and later SMS.
- AI generation credits (PROPOSED, credit): a small number of AI builds per month; extra from the shared AI wallet. Low cost, so keep a free allowance generous.
- Keep free (PROPOSED): building and editing forms, templates, the submission desk, CSV/Excel export, viewing submissions, data export and erasure, the rule-based suggestions and analytics. Forms is the ecosystem's top-of-funnel utility; a useful free tier is the product.
- When an allocation runs out (PROPOSED): never reject a respondent's submission silently. Preferred: accept and store the submission, mark it "over allocation", show it locked or limited to the owner with a top-up prompt, and keep export available. Alternative on hard storage limits: stop accepting new file uploads only. Never delete existing data. Unrelated features (builder, templates, desk, other platforms) keep working.

## 7. Cross-platform dependencies and shareable resources
- Depends on: InfoDot (identity, entitlement, company sync). Delivery endpoints are customer-defined, not ecosystem platforms. No Dot.Files, Dot.Finance, Dot.Notify or Dot.Engage integration found in `app/`.
- Shareable (PROPOSED): AI credits (shared wallet), storage credits (uploads, aligned with Dot.Files), comms credits (email now, SMS later, perhaps through Dot.Notify), seats (InfoDot company membership replaces the local cap of 10).
- Platform-specific (PROPOSED): submission count, published form count.

## 8. Gaps to meter, enforce and audit
1. NEEDS DEVELOPMENT: a usage table for Forms (team_id, metric, quantity, period) fed by events: submission stored, file bytes stored, email queued, webhook delivered, AI generation done. Today only row counts are derivable.
2. NEEDS DEVELOPMENT: enforcement point in `PublicView` before saving a submission and before storing a file (public path, so it must use the form's owning team's wallet, fail open for the respondent, and degrade as in section 6).
3. NEEDS DEVELOPMENT: AI builder metering and a rate limit (`AiBuilder.php`, `GenerateAiFormBlueprintJob`), and move the call off the web request.
4. NEEDS DEVELOPMENT: byte counting for uploads (size column or storage scan), and a decision on moving uploads to Dot.Files and off the `public` disk (a privacy risk independent of billing).
5. NEEDS DEVELOPMENT: log of webhook deliveries and notification emails (status, size), also useful for support.
6. NEEDS DEVELOPMENT: replace the `max_members` env cap with the InfoDot seat entitlement.
7. NEEDS DEVELOPMENT: wallet client with fail-closed debit for spending and fail-open for reads (entitlement today fails open by design). On SQLite, wallet debits need a single writer or an external ledger to avoid double spend.
8. NEEDS DEVELOPMENT: audit trail of allocation changes; Forms has no audit log table today.

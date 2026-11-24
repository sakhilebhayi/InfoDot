# Dot.Press monetisation audit (read-only)

Source note: local checkout /Users/sakhilebhayi/Dot/Dot.Press is on branch `chore/align-production`, 65 commits behind origin/main (2026-11-15). Findings come from origin/main via `git archive` extracted to /private/tmp/claude-501/-Users-sakhilebhayi-Dot/e077f64e-f1ab-4cb2-b638-4c260e093867/scratchpad/r60/src/Dot.Press. Paths are repo-relative. Labels: EXISTING / PROPOSED / NEEDS DEVELOPMENT.

## 1. What it does
- EXISTING: Presentation/deck editor: projects hold decks, slides, elements, layouts, themes, brand kits; document schema (`database/migrations/2026_11_08_100000_press_document_schema.php`, `app/Services/Press/DeckDocument.php`, `LayoutLibrary`, `ThemeLibrary`, `TemplateLibrary`, `BrandKitTokens`).
- EXISTING: Collaboration and governance: comments, mentions, reviews with rounds, presence, shared links and per-person access, version history with compression (`DeckComment`, `DeckReview`, `DeckShare`, `DeckPresence`, `DeckVersion`; `Sharing`, `Reviews`, `VersionService`).
- EXISTING: Export to PDF and PPTX via queued job (`app/Jobs/RunDeckExport.php`, `ExportService::FORMATS = ['pdf','pptx']`, `deck_exports`), PPTX import (`DeckImportController`).
- EXISTING: Live presenting sessions and offline present package (`PresentSessionController`, `PresentPackage`, `present_sessions`).
- EXISTING: AI: whole-deck generation, slide generation, text rewrite, outline from brief/source, whole-deck "intel" suggestions; plus deterministic local analysis (analyze/story/spine are free) (`app/Http/Controllers/Api/Ai*.php`, `DeckIntelController.php`, `app/Services/Ai/*`, `app/Services/Press/Intel/*`).
- EXISTING: Asset library with image/audio/video upload, processing and usage tracking (`app/Services/Press/Assets/*`).

## 2. Most valuable features
- Branded deck creation with themes/brand kits and reviewed/approved decks (reviews + mentions: business workflow value).
- PDF/PPTX export and PPTX import (switching and handoff value; CPU-heavy queued work).
- AI outline and deck generation (time saved; the cost feature) with local-rule fallback.
- Live presenting and shareable link access with revocation (`DeckShare`, rotate-link route).
- Deterministic deck intelligence (analyze, story, spine) with no cost.
- Asset library with video up to 200 MB (storage-heavy).

## 3. Existing limits, quotas, plans, entitlements, billing
- EXISTING InfoDot entitlement: `app/Http/Middleware/EnsureCompanyEntitled.php` + `app/Services/InfoDot/CompanyEntitlement.php` (platform key `press` in `config/ecosystem.php` 'infodot'). R-50 pattern: on web and api groups for browser-session users whose team is linked to an InfoDot company; boolean entitled; cached about ten minutes; no clear answer = carry on; public/shared links, live presenting and bearer-token calls are never checked. Access on/off only; no quantities.
- EXISTING AI quota (the credit-like logic): `config/ai.php` `limits.daily_quota` 100 units per USER per day (env `AI_DAILY_QUOTA`), `per_minute` 20 (`AppServiceProvider` throttle `ai`), `max_deck_slides` 12. `config/press-ai.php` `quota_cost`: outline = 3 units, suggest = 1 unit; full-deck generation is charged one unit per slide, reserved up front (`AiController::ensureQuota($request, $slideCount)`; `AiOutlineController`, `DeckIntelController`). Counting is `COUNT(*)` of `ai_usage_logs` rows for the user and calendar day (`AiController::usedToday`, `IntelGate::remaining`); a call of cost N writes N rows ("charge" rows) (`IntelGate::record`). When the quota is exhausted the intel and outline endpoints degrade to local rules and say so (`degraded_reason`); `AiController` returns HTTP 429 "daily AI quota". `GET /ai/usage` reports quota remaining (`AiController::usage`).
- Weaknesses: per user not per team/business; day-bounded not monthly; no balance, top-up or purchase; check then write (race); mock provider rows count toward quota; tokens stored but quota counts rows not tokens; per-user rows not carrying `team_id`.
- EXISTING size limits: `app/Services/Press/Assets/AssetLimits.php` image 25 MB, audio 50 MB, video 200 MB, SVG 5 MB, poster 8 MB, 60 MP; PPTX import 60 MB, max 300 slides (`config/press_export.php`); export max 600 slides, TTL 24 h; AI data inputs 5 MB / 5000 rows (`config/press-ai.php`); outline 5-40 slides.
- EXISTING rate limits: throttles on assets, uploads, exports, imports, shares, present sessions (`routes/api.php`, `AppServiceProvider.php`). No per-business storage quota.
- No plan, seat, subscription or billing code.

## 4. Resources consumed or stored, and where to read a count
| Resource | Where measurable today |
|---|---|
| AI units, calls, tokens | `ai_usage_logs` (user_id, deck_id, action, provider, model, status success/failed/blocked/charge, input_tokens, output_tokens, latency_ms, prompt/response truncated to 5000 chars; no team_id; migration 2026_03_27_190131). Per-slide charge rows make `COUNT(*)` = units. |
| Decks, slides, elements | `decks` (project_id), `slides`, `elements`; ownership through `projects.team_id` (migration 2026_08_10_143103, nullable) |
| Storage bytes (assets) | `assets.size` per project/uploader; disk `public` default (`assets.disk`); GC `press:assets-gc` daily 03:25 |
| Export files and compute | `deck_exports` (format, status, size, attempts, started_at/finished_at = job seconds, expires_at); `RunDeckExport` queue job; pruned hourly (`PruneDeckExports`) |
| Version storage | `deck_versions` (compressed nightly by `CompressDeckVersions` 03:40), `deck_changes` (pruned 03:20) |
| Seats/collaborators | `team_user`, `team_invitations`, `deck_user`, `deck_shares` (people and links) |
| Present sessions | `present_sessions` (live presenting; realtime traffic via Reverb) |
| Emails | `ReviewRequested`, `ReviewDecided`, `DeckMention` notifications use channels `mail` and `database` (`app/Notifications/*`), plus team invitations (`InviteTeamMember`): no counter |
| Background jobs | `jobs` table: `RunDeckExport`, `ApplyDependencyPatchJob`; exports table doubles as ledger |
| Comments/reviews | `deck_comments`, `deck_reviews`, `deck_activity` |

## 5. Third-party services and cost drivers
- Anthropic (when `AI_PROVIDER=anthropic`; model default `claude-3-5-sonnet-latest` per `config/ai.php`, 1,200 token default, 4,000 for JSON outline/deck) - default provider is `mock`. Prompts include source documents up to 60,000 chars, so input cost for outline can be high.
- Own infra: PDF rendering and PPTX building (CPU/memory heavy; `pdf_part_budget_mb` 150 MB, job timeout 280 s), asset storage and media processing (`ImageProcessor`, video 200 MB), Reverb websocket for presence/live presenting, SQLite database (`.env.example` DB_CONNECTION=sqlite) with nightly backup.
- InfoDot (identity, entitlement). No Dot.Files, Notify or Analytics clients found in app code (grep: only `config/ecosystem.php` mentions); mail is sent by Press itself.

## 6. Candidate purchase units (PROPOSED, no prices)
- Credit: AI units. The unit economy already exists (1 per suggestion, 3 per outline, 1 per slide of deck generation); proposal: keep these relative weights and make a "credit" = one unit, funded from a shared wallet. Weight by model/tokens once real costs are known.
- Resource/capacity: export jobs per month (PDF/PPTX are the CPU cost; maybe free for small decks via `fast_path_slides` 12), stored asset GB (video dominates), active decks, collaborators, version retention.
- Feature: live presenting sessions, PPTX import, brand kits, review workflow: bundle not meter.
- Keep FREE: authoring decks and slides, themes, templates, layouts, local deck intelligence (analyze/story/spine, already free in code), sharing by link, comments, reviews, PDF export of normal decks, local-rule outline fallback (already implemented), a modest daily/monthly AI allowance, small asset storage, version history for a recent window, presenting live.
- On exhaustion (PROPOSED, mostly implemented): AI falls back to local rules with a `degraded_reason` message (already done for outline and suggest); `generate-deck`/`generate-slide` currently return 429, suggest to switch them to the same degrade path; storage exhaustion blocks new uploads only (existing assets remain usable and downloadable); export exhaustion should offer a lower-cost route (PDF of up to 12 slides via fast path) rather than blocking, and never block download of already-produced exports; public shared decks keep working.

## 7. Cross-platform dependencies and shareable resources
- EXISTING: InfoDot SSO and entitlement only. Press is nearly self-contained (own assets, own mail, own AI).
- Shareable (PROPOSED): AI credits (wallet shared with Docs, Sheet, Projects), storage (assets and exports would sit in Dot.Files if integrated; today local disk, so NEEDS DEVELOPMENT), comms credits (move review/mention mail to Notify), seats (InfoDot).
- Platform-specific: export compute, live-present capacity, slides/decks capacity.

## 8. Gaps (NEEDS DEVELOPMENT)
- Team-level attribution: `ai_usage_logs` has no `team_id`; `decks` resolve team via `projects.team_id` which is nullable at schema level. Add team_id and make quota per business.
- Replace row-count quota with a ledger: units charged, tokens in/out, model, cost, outcome, idempotency key, reservation then settlement (today `ensureQuota` check then later write, and `generateDeck` reserves by counting rather than holding).
- Count what is charged only on success (check how failures and `blocked` rows count toward `usedToday`: `AiController::usedToday` counts all rows including failed and blocked; this penalises failures).
- Fix inconsistent provider/model defaults: `ai.anthropic.model` default is `claude-3-5-sonnet-latest`; confirm real model and rates before pricing.
- Storage metering: no per-business total (`SUM(assets.size)`, `deck_exports.size` with team join) and no enforcement in `AssetStore`/`AssetController@store`; versions and import sizes also uncounted.
- Export metering: `deck_exports` has timing but no team column; add team and job-seconds accounting.
- Entitlement client carrying quantities; email moved to a countable channel (Notify).
- Audit: admin-visible usage report, adjustment/refund records, reconciliation; tests exist for existing quota paths (verify) before charging.

# Dot.Design: monetisation fact audit (R-60, read-only)

Source basis: `origin/main` of /Users/sakhilebhayi/Dot/Dot.Design (tip 369b3fa, R-51 dashboard), extracted read-only with `git archive`. NOTE: the local working checkout is on `main` at 7437940, 65 commits behind `origin/main` (it predates R-45), so the checked-out files are NOT the deployed code. Paths below are relative to the repo root on `origin/main`. Tags: EXISTING = verified in code; PROPOSED; NEEDS DEVELOPMENT.

## 1. What it does
- EXISTING: a browser canvas studio for small businesses to make social posts, print pieces, menus, and documents (42 formats, `config/design_formats.php`; 58-64 starter templates, `docs/R45_BUSINESS.md`, `docs/R45_REPORT.md`).
- EXISTING: guided brief, then a deterministic template recommender and slot filler (`app/Services/Design/BriefRecommender.php`, `TemplateInstantiator.php`); no AI needed for this path.
- EXISTING: Brand Studio (kits with colours, fonts, logo, voice; "never-silent" brand updates with versioned apply) (`app/Models/BrandKit.php`, `DesignBrandUpdate.php`).
- EXISTING: collaboration: share links with public comments, reviews, versions, milestones, grants (`design_shares`, `design_reviews`, `design_versions`, `design_comments`, `design_project_grants`).
- EXISTING: an AI assistant (compose / improve / copy) via Anthropic that only selects and fills templates or proposes undoable edits (`app/Services/Design/Assistant/*`, `docs/R45_AI.md`). A design-system area (token sets, components, drift scan) also exists for operators.

## 2. Most valuable features (business value)
- Brief to finished, on-brand design in minutes (recommender + instantiator + brand kit). Measurable by projects created from a brief (`design_projects.brief`, `template_id`, `format_key`) and `design_templates.uses`.
- Brand consistency: kit applied across designs and re-applied on kit change (`design_brand_updates`, `design_projects.brand_kit_id`, `brand_version`).
- Export in many sizes (PNG/SVG/PDF/JPG/GIF; "Other sizes" resize via aspect families). Measurable: `design_exports` (format, pages, scale, bytes, user_id, created_at).
- Review/approval and share links for sign-off with customers or staff (`design_shares.view_count`, `design_reviews`).
- AI help for copy and layout fixes (most direct cost-bearing feature): `design_ai_proposals`.
- Filing exports into Dot.Files (`app/Services/Integrations/Files/ExportFiler.php`, `design_file_links`).

## 3. Existing limits, quotas, plans, entitlement, billing
- Entitlement: EXISTING boolean only. `app/Http/Middleware/EnsureCompanyEntitled.php` (registered in `routes/web.php` line ~68 on the signed-in group) calls `app/Services/InfoDot/CompanyEntitlement.php`, which does GET `{infodot}/api/ecosystem/companies/{uuid}/platforms?platform=design` with the service bearer secret and header `X-Dot-Platform`. It reads only `platforms[].key == design` and `entitled` (bool) plus `status` (string). Cached 10 min (success) / 60 s (failure). Fail-open: only an explicit `entitled:false` refuses (`app/Support/EntitlementRefusal.php`). Workspace without `teams.infodot_team_uuid` is not asked; a personal-workspace verdict is stored in session at sign-in (`EcosystemAuthController.php` ~179). Config: `config/ecosystem.php` (`entitlement.check`, `DESIGN_ENTITLEMENT_CHECK`). No quantities, allocations or balances are read from InfoDot.
- Quotas EXISTING (all config-driven, per user unless stated):
  - Asset storage 200 MB per user (`config/design.php` `assets.quota_bytes`, `DESIGN_ASSET_QUOTA_MB`), enforced in `app/Services/Assets/AssetUploader.php` ~77; read via `DesignAsset::usageFor()` (sums `design_assets.file_size` where `disk` not null). 8 MB per file, 40 MP, 20 uploads/min.
  - Pages per project 60, elements per page 300, document JSON 1 MB (`resources/design/document-schema.json` limits; enforced `PageStore.php` ~129 -> `PageLimitReached`).
  - AI calls per day: 60 per user, 300 per workspace, 12 per minute (`config/design_ai.php` `limits`; `app/Services/Design/Assistant/UsageGuard.php`). Refusal message keeps "automatic fixes still work" (graceful degradation already designed).
  - Share links: 20 active per project, max expiry 365 days; export throttle 60/min; versions: 50 automatic kept per project; templates saved: 100 per user.
- Plans / subscriptions / billing code: none in this repo (no plan, subscription, invoice or Paystack code found; `grep` of app/, config/, routes/, database/).
- Image generation: `config/ai_image.php` default driver `mock`; real providers not implemented (`AiImageGenerationService`, `ai_generation_logs.tokens_used`).

## 4. Resources consumed or stored, and where each can be metered today
| Resource | Meter source today | Status |
|---|---|---|
| Asset bytes | `design_assets.file_size`, `user_id` (+ `disk` not null); team derived via `DesignAccess::defaultTeamId` | EXISTING, per user not per company |
| Projects | `design_projects` (user_id, team_id column when migrated) | EXISTING |
| Pages | `design_canvases` count per project | EXISTING |
| Version snapshots (storage) | `design_versions.bytes`, `pages` json | EXISTING |
| Exports (count, bytes, format) | `design_exports` (browser makes the file; server only logs; 365-day retention `config/design.php` `retention.exports_days`) | EXISTING, but client-reported, not authoritative |
| AI calls and tokens | `design_ai_proposals` (`model` not null = a billed call; `tokens_in`, `tokens_out`, `mode`, `status`, `team_id`, `user_id`); `ai_generation_logs.tokens_used` | EXISTING; 90-day retention prune (`design:prune-ai-proposals`) would erase billing history unless rolled up first |
| Share-link views | `design_shares.view_count` | EXISTING |
| Notifications sent | no counter; goes through `NotifyGateway` to Dot.Notify | NEEDS DEVELOPMENT |
| Files filed to Dot.Files | `design_file_links` | EXISTING (count only; bytes live in Files) |
| Seats | Jetstream `team_user` (workspace members) | EXISTING |
| Platform-level analytics | `app/Services/Analytics/MetricsCollector.php` sends only `projects` and `assets` totals, filed under one operator company, not per customer | EXISTING, not usable for billing |

## 5. Third-party services and cost drivers
- Anthropic Messages API (`config/design_ai.php`, model default `claude-sonnet-5-5`): the only direct variable cost. `docs/R45_AI.md` s5 estimates (not measured against a bill): compose ~2 cents, improve ~1 cent, copy under half a cent at $2 in / $10 out per million tokens; worst case at caps ~$1.20 per person, ~$6 per workspace per day; prompt caching not used.
- Disk storage for `design_assets` (private disk `design_assets`) and `design_versions` JSON.
- Dot.Files (optional, `design_integrations.files`), Dot.Notify (optional, `design_integrations.notify`), Dot.Analytics (daily snapshot), InfoDot (identity/entitlement). Mail transport is the app's `MAIL_MAILER`; no direct mailables found (notifications go via Notify).
- Server CPU for GD image re-encode and server PNG/SVG export (`CanvasPngExporter`, 16 MP cap); queue jobs minimal (`SendAnalyticsSnapshot`).

## 6. Candidate purchase units (PROPOSED; no prices)
- Credit: AI assistant calls (compose / improve / copy). Reason: the only real variable cost, already counted per row, already refusable. Weighting by `mode` (compose > improve > copy) or by `tokens_in+tokens_out` is available from existing columns.
- Capacity: asset storage (bytes) and version history depth. Reason: cheap to meter, easy to explain.
- Capacity: active seats in a workspace and number of active share links (cheap to count).
- Feature gate (light touch): server-side PNG/PDF export at high resolution, brand-kit count beyond one, custom template library beyond N.
- Keep FREE (PROPOSED): the whole deterministic path (brief, recommender, 58+ templates, editor, brand kit with one default, basic exports in common formats, share links with comments, "automatic fixes", resize suggester), a small daily AI allowance (the current 60/day per user is likely far above what a free tier needs; propose cutting for free and keeping the existing per-minute throttle), and a modest storage floor (the existing 200 MB can be the free floor).
- On exhaustion (PROPOSED): AI refuses with the existing `AssistantLimitReached` message pattern ("automatic fixes still work"); upload refuses new files but never deletes or hides existing ones; designs remain viewable, editable and exportable. Never lock read access or exports of existing work. Storage over-quota = block new uploads only.

## 7. Cross-platform dependencies and shareable resources
- Depends on InfoDot (sign-in, `CompanyEntitlement`, membership webhook `design_integrations.membership`), Dot.Files (export filing; per-file max 25 MB), Dot.Notify (comment/review/share events; company must enable each `design.*` event), Dot.Analytics (daily counts).
- Shareable across ecosystem (PROPOSED): AI credits (one pool for Design, Pulse moderation, Engage and any other Anthropic consumer); storage (Design assets and exports could live in the Dot.Files quota instead of a Design-only 200 MB, since exports can already be filed there); comms credits (Notify); seats (InfoDot company licence).
- Platform-specific: canvas page/element limits (technical safety), template library, brand kit count.

## 8. Gaps to build (NEEDS DEVELOPMENT)
- A per-company allocation read: today InfoDot returns only `entitled` + `status`. Needs an allocation/balance contract (credits, storage, seats) from InfoDot or a wallet service; `CompanyEntitlement.php` must read it and expose `allows(resource, amount)`.
- Company-level (not user-level) metering: asset quota and AI caps are per user/workspace-id; billing needs `teams.infodot_team_uuid` as the key. Personal workspaces have no company to bill.
- A durable usage ledger: write an append-only `usage_events` row (company uuid, resource, units, source id, idempotency key) at the point of spend, so the 90-day prune of `design_ai_proposals` and 365-day prune of `design_exports` do not lose billing evidence.
- Backend enforcement: AI path already enforces server-side (`UsageGuard`); exports are produced in the browser and only logged afterwards (`ExportAuditController`), so an export quota would be honour-system unless the server issues a signed export grant first, or exports move server-side. Upload quota is enforced server-side.
- Reservation before call for AI (reserve tokens, then settle with actual `usage` from `AnthropicClient.php` ~193) to avoid overspend on concurrent calls.
- Audit: expose per-company usage report and reconciliation against the Anthropic invoice; record who/when for allocation changes; fail-open policy for entitlement (outage lets everyone in) must be decided separately for metered resources (fail-closed for spending credits, fail-open for viewing).
- Image-generation driver is `mock` only; if a real provider is added it needs its own credit weight and `ai_generation_logs` metering.

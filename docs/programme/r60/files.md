# Dot.Files: monetisation audit (read-only, R-60)

Evidence base. The local working copy `/Users/sakhilebhayi/Dot/Dot.Files` is on branch `feature/dot-doc-integration` at 9bc5b68 (Sep 2026), far behind. Everything below was read from `origin/main` a0be694 (2026-11-15, "R-52: 44px tap targets") exported with `git archive` into the scratchpad. Paths are repo-relative. No real `.env` read. Note: `README.md` is partly out of date (it says no versions, no sharing, no previews); the code and `docs/programme/*` are the truth. Tags: EXISTING / PROPOSED / NEEDS DEVELOPMENT.

## 1. What it does (core value)
- EXISTING: the company filing room: a recursive folder tree per team (`objects` adjacency list, `files`, `folders`), upload up to 100 MB, rename, move, trash with 30-day hold (`routes/web.php`, `app/Http/Livewire/FileBrowser.php`, `app/Console/Commands/PurgeTrash.php`).
- EXISTING: append-only version history with restore (`app/Services/FileVersions.php`, table `file_versions`, each version owns its own immutable blob).
- EXISTING: cross-company sharing with owner approval, expiry (max 90 days), recipient verified by email (`app/Services/FileShareService.php`, `FileShare::MAX_DAYS=90`, `MAX_ACTIVE_PER_FILE=25`).
- EXISTING: governance centre: review-only flags for suspicious activity (version churn, replace-then-trash), alert emails to owners/admins (`app/Services/FileGovernance.php`, `config/governance.php`).
- EXISTING: the ecosystem's central file API: other platforms file and fetch business documents server to server (`docs/programme/PLATFORM_FILE_API.md`, `routes/api.php`).

## 2. Most valuable features (measurable business value)
- EXISTING: central storage for other platforms (single place for company documents; consumers found on origin/main: Dot.Agents `app/Services/Integrations/DotFilesClient.php`, Dot.Design `Services/Integrations/Files/FilesVault.php`, Dot.Projects and Dot.Sheet `Services/Integrations/FilesClient.php`, Dot.Tasks `Services/Files/FilesClient.php`). Finance does NOT use it (attachments are Finance-local).
- EXISTING: version history + trash + restore (recovery value, audit trail, tamper evidence with sha-256 per version).
- EXISTING: governed external sharing with approvals and audit (`FileShare`, `/approvals`, `file_flags`).
- EXISTING: full-text search (Laravel Scout / TNTSearch, `Obj::searchable`) and an InfoDot header-search endpoint (`Api/EcosystemSearchController`, `POST /api/ecosystem/search`).
- EXISTING: previews (GD image thumbnail, Imagick PDF first page) (`app/Services/FilePreviewService.php`), inline view (`/files/{file}/inline`).

## 3. Existing limits, quotas, plans, entitlement checks, billing code
Entitlement consult (EXISTING):
- `app/Http/Middleware/EnsureCompanyHoldsPlatform.php` on the web group: asks `app/Services/InfoDot/EntitlementCheck.php` (`GET .../companies/{team_uuid}/platforms?platform=files`, bearer token, cached) whether the company holds the platform. BOOLEAN `entitled` + `status`. Unknown = let through (fail-open). Personal workspace or team without `infodot_team_uuid` is never asked. Exempt: login/logout/handoff, `shared*` (files other companies shared with a person), cookies/terms, csp-report (`EXEMPT` constants). The server-to-server `/api` routes never pass through it (comment in the middleware): so a company that lost the platform can still have another platform call the file API. NEEDS DEVELOPMENT: entitlement check on the platform API.
- Switch via config `ecosystem.infodot.*` (entitlement check enabled flag in `EntitlementCheck::enabled`).
- Membership: InfoDot pushes membership changes signed to `POST /api/ecosystem/membership` (`Api/EcosystemMembershipController`, 60/min).
Hard limits (EXISTING, technical not commercial):
- Upload 100 MB (`max:102400` in `FileController.php:94` and `FileBrowser.php:418`) + MIME allow-list; platform API 25 MB decoded per store/update/fetch (`PLATFORM_FILE_API.md`).
- Shares: max 90 days, max 25 active per file (`app/Models/FileShare.php`).
- Trash hold 30 days (`Obj::TRASH_DAYS`).
- Throttles: store/update/trash 60/min, fetch/meta/list/search 120/min (`routes/api.php`).
- Governance thresholds env-tunable (`config/governance.php`).
Quotas/plans/billing: NONE. EXISTING grep: no quota, plan, storage cap, subscription or payment code anywhere in `app/` or `config/`. Per-team storage is unlimited and unmetered.

## 4. Resources consumed or stored, and where to count them today
| Resource | Where counted (EXISTING) | Note |
|---|---|---|
| Stored bytes (current content) | `files.size` (bigint) by `files.team_id` | Raw column; `Obj` rows carry `team_id`, `trashed_at` |
| Stored bytes (history) | `file_versions.size` (+ `file_id`) | Every replace/restore adds a full immutable blob (`FileVersions.php:72`): history can multiply bytes. NOT included in `files.size`. |
| Trashed bytes (held) | `objs.trashed_at` joined to files | Still on disk for 30 days, then `files:purge-trash` |
| Preview thumbnails | `files.preview_path`/status (migration `2026_10_31_000001`) | Small JPEG per file |
| Files / folders count | `objects` (objectable_type file/folder) | |
| Seats / members | `team_user`, `teams.infodot_team_uuid` | Seats are InfoDot-licensed |
| Platform file API calls | `platform_file_events` (platform, team_id, file_uuid, event store/fetch/trash, created_at) | Fetch and store are countable per platform per company; `update`, `meta`, `list` are not obviously recorded (verify) |
| Shares | `file_shares` (active, expires_at, permission) | Cross-company reach |
| Outbound email | governance alert only (`Mail::to` in `FileGovernance.php:141`) | Low volume |
| Search index | TNTSearch files under storage (`storage/objects.index`) | Disk, not per team |
| Background jobs | Laravel queue, preview generation (`GenerateFilePreviews`), resource registration to InfoDot (`ResourceRegistry`) | |
| Bandwidth (downloads) | not counted | NEEDS DEVELOPMENT |
All storage is the local disk: `Storage::disk('local')` throughout (`File.php`, `FileVersions.php`, `FilePreviewService.php`); no S3 code (README is correct on this). Storage cost is therefore the host's disk, a hard capacity ceiling for the whole ecosystem, not elastic.

## 5. Third-party services and cost drivers
- None billed per use today: no AI, no SMS, no S3. Optional: Scout/TNTSearch (local), GD/Imagick (local CPU), SMTP mailer (low volume), Reverb (configured only).
- InfoDot is called for entitlement, redeem, pointer registration (`app/Services/InfoDot/ResourceRegistry.php` sends only uuid/path, never content).
- Cost drivers (PROPOSED reading): disk bytes (current + versions + trash + previews), backup size (backup cost scales with bytes), egress/download bandwidth, CPU for previews and search indexing, support/ops for restores. If S3 is adopted later, per-GB storage and egress become real marginal costs.

## 6. Candidate purchase units and free tier
- Primary unit (PROPOSED): STORAGE CAPACITY in GB per company (resource + capacity). It is the only truly scarce, monotonic, measurable thing here and maps to the hosting cost. Count `files.size + file_versions.size` (+ trashed, optionally excluded after purge).
- Secondary unit (PROPOSED): API/automation volume for the platform file API (store/fetch calls and bytes) only if it becomes a heavy path; start free, keep as a counted metric.
- Optional feature units (PROPOSED): extended version retention (keep N versions free; pay for unlimited history), longer trash retention, external-share volume (shares per month) and governance analytics. Keep the approval/audit mechanism free: it is a trust and safety feature.
- Free tier (PROPOSED): a useful free allowance per company (enough for a small business's documents: GB figure to be set from cost inputs, not here), full folder/search/preview/share-with-approval, version history with a capped number of versions, 30-day trash, and the file API for other Dot platforms within the allowance.
- Must stay free (PROPOSED): reading, downloading and exporting your own files at any time; restoring from trash; sign-in and company switching; anything needed to leave (data portability, POPIA access). The existing middleware already exempts `shared*` routes so recipients are not locked out when the owner company lapses.
- When the allocation runs out (PROPOSED): block NEW uploads, replaces, restores that add bytes and API `store`/`update` (return a clear 402/413-style `storage_full` error to the calling platform so e.g. Tasks/Projects degrade gracefully by saving locally or showing "attachment not stored"). Never block read, download, delete, trash/purge, or move; deleting must free quota. Add a warning band (for example 80% and 100%) and a grace window before refusal so a person's work is not lost mid-upload. Old data is never deleted for non-payment.

## 7. Cross-platform dependencies and shared resources
- Depends on InfoDot: identity/SSO, company uuid, entitlement, membership, header search (`config/ecosystem.php`).
- Provides to: Agents, Design, Projects, Sheet, Tasks (client code on origin/main). Their files are in the company's quota, attributed by `files.origin_platform`. This is the key shared-resource design: ONE storage wallet per company consumed by many platforms, attributable per platform through `origin_platform` and `platform_file_events`.
- PROPOSED shared across ecosystem: storage (Files is the natural owner; every consumer already speaks its API), seats (InfoDot), AI credits (not used here today), comms credits (not used here).
- Platform-specific: version-history retention, share volume, preview generation.

## 8. Gaps: meter, enforce, audit
- NEEDS DEVELOPMENT meter: a per-company usage figure that sums current + version + (trash) bytes. Today `files.size` excludes versions; `file_versions.size` exists, so a SQL view/command can be written without schema change, but a maintained counter (e.g. `team_storage_usage` updated in `FileVersions`, upload path, `PlatformFileController`, purge) is needed for fast enforcement. Also reconcile against real disk (`files:` command to detect orphan blobs).
- NEEDS DEVELOPMENT enforce on the backend: one choke point before `Storage::put` in the 3 write paths (UI upload `FileController.php`/`FileBrowser.php`, `FileVersions` replace/restore, `PlatformFileController::store/update`). Currently validation is only per-file size.
- NEEDS DEVELOPMENT: numeric entitlement from InfoDot (it returns boolean today) or a wallet/allocation API; `EnsureCompanyHoldsPlatform` does not cover `/api/platform/*`.
- NEEDS DEVELOPMENT audit: record usage changes (who/what/bytes/platform) append-only; `platform_file_events` is a start (extend with bytes and for update/meta/list); usage statement per company per month; alert via Notify before the cap.
- Risks to settle before charging: storage is a single local disk (capacity and backup size cap the whole ecosystem; S3/object store decision needed), version history can silently inflate usage (decide whether versions count), hard-deleted data and POPIA obligations on lapse, and the egress/backup cost not yet measured. Local working copy is stale: sync before building.

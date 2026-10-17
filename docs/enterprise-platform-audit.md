# InfoDot enterprise platform: audit, architecture and phased plan

Status: audit phase, 2026-10-17. Nothing in this document is implemented unless it says so. Sources: InfoDot code (main at e419d54), read-only audits of `Dot.Files` and `Dot.docs` (code, cited by file), the Dot.Brain governance docs, and the live-host facts gathered in the 2026-10 reliability audit.

## 1. What exists today

**InfoDot (this repo)**
- Works: sign-up/sign-in, Jetstream teams (roles: Administrator, Editor), Questions and Solutions with comments and likes, search, profile, analytics, the Dot platform launcher (`/auth/ecosystem` token handoff).
- Files and folders: `File`, `Folder` and `Obj` (tree) models and tables exist (`HasTeamScope`), but there are no routes, controllers, pages, upload code or tests. There is no file management in InfoDot.
- Not present: sharing, permissions beyond team role, versions, trash, approvals, activity or audit log, working notifications (the page is a placeholder), favourites, recents.
- Privacy: Questions, Solutions, comments and likes are global, visible to every signed-in user, not scoped per team. Private business content must never be stored in that path.

**Dot.Files** (repo audit; production runs `origin/main`)
- Stack: Laravel 12, one Livewire `FileBrowser`. Tables `objects` (tree), `files`, `folders`, all with `team_id`; file bytes on a private `files` disk; uploads 100 MB with a mime allow-list enforced only in the Livewire component.
- Works: upload with drag-and-drop, folders, rename, signed 10-minute previews, download with a policy, search (TNTSearch), a bulk-delete approval gate.
- Missing: versions, restore, trash (delete is hard and removes the bytes), sharing and links, favourites, recents, comments, audit trail, copy, a move UI, and any API (`routes/api.php` is only `GET /user`). No domain events.
- Risks: `HasTeamScope` applies no filter when a user has no current team; single delete skips `FilesService`'s authorization; download authorizes against the session's current team, not the file's team; search results are not team-filtered before the scope is applied.

**Dot.docs**
- Production is SQLite. Documents, versions (full snapshots), comments with mentions, collaborators (viewer/editor/admin), public links with password and expiry, soft delete, an access-only audit log, per-document webhooks, and co-editing phases 1-2 (poll plus block-level merge, no CRDT, no websocket in production).
- It already files documents into the shared `objects/files/folders` tree through shared tables, not an API. No document API, no favourites or recents, no trash UI. AI calls go direct to vendors, not through Dot.Agents or Dot.Brain.

**Dot.Brain** states InfoDot is the source of truth for ecosystem identity. It names no authoritative owner for notifications or for file ownership beyond the shared database.

## 2. The finding that decides the architecture

The ecosystem's SSO and its shared file tree were designed for **one shared PostgreSQL database** (Brain ADR-0013: a token minted in one app is verified by the same row in the same database). Production is not that:

| App | Production database |
|---|---|
| InfoDot | its own MySQL `infodotc_main` (own users, teams, tokens) |
| Dot.Files | MySQL `infodotc_files` (its own user rows) |
| Dot.docs | SQLite |

A token InfoDot mints cannot be found in another app's database, so the token handoff cannot sign anyone in there, and `objects/files/folders` cannot be shared between Dot.Files and Dot.docs in production. This is established from code and database facts. It has **not been tested end to end with a real login**, and doing that is the first task of Phase 0.

Consequence: integration cannot mean "point InfoDot at the Dot.Files tables". It has to go through a service contract.

## 3. Recommended architecture

- **Identity:** InfoDot is the identity authority. Satellites verify a handoff token by calling an InfoDot introspection endpoint (token, ability, expiry, user uuid, email, team memberships), then provision or match their local user. Tokens stay single use and five minutes.
- **Files:** Dot.Files is the single authority for files and folders. InfoDot is a client of a versioned **Dot.Files API** (`/api/v1`): tree, list, get, upload, rename, move, copy, delete to trash, restore, versions, shares, favourites, recents, activity. Every call is authorized in Dot.Files against the caller's team and per-object permissions. InfoDot never reads Dot.Files tables and stores only `file_uuid` references and its own workflow data.
- **Documents:** Dot.docs registers each document in Dot.Files through the same API (type `document`), so it is discoverable from both. Authorization stays in Dot.docs; Dot.Files shows a title only to people who may see it.
- **Approvals, tasks, workspace structure:** owned by InfoDot in its own database (an approval references a `file_uuid` and a version). 
- **Notifications:** Dot.Notify is not live. Phase 1 uses in-app notifications stored in InfoDot; Dot.Notify is added later behind the same interface.
- **Not integrated now (not live or no clear benefit):** Dot.HR, Dot.Finance, Dot.Analytics, Dot.Billing, Dot.Notify, Dot.Tasks. Dot.Agents and Dot.Brain only after permission-aware retrieval exists.

## 4. Phased plan

| Phase | Scope | Where | Depends on |
|---|---|---|---|
| 0 Identity foundation | Test the current handoff live; build the introspection endpoint and satellite redemption (Dot.Files, Dot.docs); tests for expiry, reuse, wrong ability | InfoDot, Dot.Files, Dot.docs | owner login to verify |
| 1 Brand, shell, design system | New corporate identity, application shell (restrained top bar, contextual side navigation, breadcrumbs), tables and lists, light and dark, remove social conventions | InfoDot | decision on Questions and Solutions |
| 2 Files API and document library | Dot.Files `/api/v1`, trash, versions, shares and secure links, favourites, recents, audit trail, fix the authorization gaps in section 1; InfoDot library UI over the API | Dot.Files, InfoDot | Phase 0 |
| 3 Workspaces and administration | Departments, projects and teams as workspaces, roles beyond Administrator and Editor, member administration, retention settings, audit view | InfoDot | Phase 2 |
| 4 Approvals and collaboration | Approval workflows, review assignment, mentions, in-app notifications, document locking states | InfoDot (+Dot.docs hooks) | Phases 2-3 |
| 5 Ecosystem | Dot.docs registration, Dot.Projects links, then Agents and Brain with permission-aware access | all | Phases 2-4 |

Each phase ships behind tests and a deploy; nothing is advertised until it is implemented and verified.

## 5. Not to be claimed yet
Real-time collaboration beyond Dot.docs's polling and block merge, version history on files, sharing, approvals, retention, compliance features, and any integration with a platform that is not live.

## 6. Open decisions (owner)
1. Questions and Solutions: keep as a business knowledge base without likes, move out of the primary navigation, or retire.
2. Files authority: Dot.Files via a new API (recommended), or InfoDot's own library.
3. Start order: Phase 0 and Phase 1 first (recommended), or the design alone.

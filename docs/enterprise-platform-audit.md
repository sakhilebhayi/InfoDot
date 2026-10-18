# InfoDot enterprise platform: audit, architecture and phased plan

Status: baseline audit written 2026-10-17; see the status table below for what has since been built. Nothing is implemented unless that table says so. Sources: InfoDot code (main at e419d54), read-only audits of `Dot.Files` and `Dot.docs` (code, cited by file), the Dot.Brain governance docs, and the live-host facts gathered in the 2026-10 reliability audit.

## Status as of 2026-10-18

The audit below is the original 2026-10-17 baseline and is kept as written, because it records what was true and why decisions were made. What has since been built, verified and deployed:

| Plan item | State | Where |
|---|---|---|
| Phase 0 identity: redeem endpoint, per-platform secrets, verified email, single-use audience-bound tokens | **Live** | InfoDot, Dot.Files, Dot.docs |
| Phase 1 brand, shell, design system | **Live**, then superseded by "The Yard" design language (top bar, area bands, owner codes and references) | InfoDot (#13, #16 to #19, #21) |
| Step A tenant identity: team uuid, satellites store `infodot_team_uuid`, sign-in sync of membership and roles, `infodot:link-team` | **Live** | InfoDot, Dot.Files, Dot.docs |
| Step B isolation gaps: private Knowledge with a public area, Files fail-closed scope, owner-checked delete and download, scoped search and analytics | **Live** | InfoDot, Dot.Files |
| Step C resource registry and resolver (`resources` table, registration API, `/open/{uuid}`) | **Live for files** | InfoDot (#20) |
| Step D deep-link contract, Dot.Files side (`/o/{uuid}`, redirect allow-list, backfill command) | **Live for files**; Dot.docs not yet | Dot.Files (#88) |
| Bridge services (Memory, Brain, Dopemine) never shown to people | **Live** in InfoDot; error-page fix merged in 14 platform repos | InfoDot (#22) |
| Step E tenant-aware cross-platform search (Files and Docs results in header search, signed, member-only, pointers InfoDot already holds) | **Live** | InfoDot, Dot.Files, Dot.docs |
| Instant revocation: signed webhook on member removal, role change and company deletion; the sign-in sync stays as the backstop | **Live** | InfoDot, Dot.Files, Dot.docs |
| Documents registered with D references, `/o/{uuid}` in Dot.docs | **Live** | Dot.docs |
| Step F cross-company sharing | **Proposal below, not built: needs owner decisions** | |

Owner decisions in section 6 are settled: Questions and Solutions are kept as a business knowledge base without likes (private to the company by default, public by choice); Dot.Files is the authority for files, reached through an API; identity first, then design.

The caveat in section 1 about the Dot.Files and Dot.docs working copies is historical: everything above was built and tested against each repo's `origin/main`.

## 1. What exists today

> Caveat: the Dot.Files and Dot.docs audits read the working copies at `/Users/sakhilebhayi/Dot`, which sit on feature branches (`Dot.Files` on `feature/dot-doc-integration`, three commits ahead of what production runs; `Dot.docs` on `chore/tailwind-v4`). Items that depend on those branches (the `document` node type, signed document viewer, `expires_at` token column) are not in production Dot.Files. Re-verify against `origin/main` before building on them.

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

## 7. Cross-platform routing and tenant isolation (second brief, audit findings)

Evidence is from code on `origin/main` of each repo (what production runs) unless noted.

### What "tenant" means today, per app
- **InfoDot:** a Jetstream team. Personal teams are one person; non-personal teams are companies. `Questions`, `Solutions`, comments and likes are **global, not team-scoped, by design** (`HasTeamScope` doc comment), and the header search reads them across all companies. So any signed-in person of any company can read every company's questions and solutions. This is the one confirmed cross-tenant read in the product today (small now, wrong for a business platform).
- **Dot.Files:** `team_id` on objects, files and folders, enforced by a global scope. Two weaknesses: the scope applies **no filter at all** when the user has no current team (fails open); `FileBrowser::deleteObject` looks up an id held in a public Livewire property with only that scope behind it (no policy check). Download authorises against the session's current team rather than the file's own team. Search runs through Scout; results are re-hydrated through the scope, but the index file holds every team's names.
- **Dot.docs:** `documents.owner_id` plus a nullable `team_id`; `DocumentPolicy` (owner, team role, collaborator, public link). Policy-based, so stronger than Files; the shared tree (`objects`) only works if both apps share a database, which production does not.

### The structural gap (more important than any single bug)
Phase 0 proves **who** a person is across apps (verified email) but not **which company** they act for. The redeem response already carries the person's teams and current team, but Dot.Files and Dot.docs ignore it: each creates its own personal team for a new person. So today:
- there is no shared tenant identifier across apps (InfoDot `teams.id`, Files `teams.id` and Docs `teams.id` are unrelated numbers);
- a person in two companies in InfoDot arrives in each satellite with only a personal team;
- membership changes in InfoDot (removed from a company) never reach the satellites;
- a deep link cannot name a company, so a destination cannot check "this resource belongs to the company the person is acting for".

Tenant-aware routing therefore needs a **tenant identity layer first**, not a router first.

### Plan (in order)
| Step | What | Where |
|---|---|---|
| A. Tenant identity | Stable `uuid` on InfoDot teams; satellites store `infodot_team_uuid` on their teams; on every SSO the redeem payload (teams, roles, active team) is applied: create or match the company team, sync membership and role, remove memberships InfoDot no longer lists; the active tenant for the session is validated against membership | InfoDot (migration), Files, Docs |
| B. Close the isolation gaps | Files: fail closed without a current team, policy check on every object action, authorise downloads against the file's own team, team-filtered search; InfoDot: make Questions, Solutions and search tenant-scoped (migration, backfill to the author's team); isolation tests with two synthetic companies on every path | Files, InfoDot |
| C. Resource registry and resolver | `resources` table in InfoDot holding only stable references (resource uuid, type, authoritative platform, tenant uuid, platform object uuid, canonical path), no content; `GET /open/{resource}` validates membership and tenant, mints a platform-bound token with a `redirect` to the exact object, and sends the person there; destination re-authorises | InfoDot |
| D. Platform deep-link contracts | Files and Docs accept `redirect` to an object path and answer 403 with a request-access screen; a small authenticated API for registration and rename, move and delete events so references stay current (no duplicates) | Files, Docs |
| E. Tenant-aware search | Cross-platform search only after A to D, querying each platform with the caller's token so each platform filters; no central copy of titles | all |
| F. Controlled cross-tenant sharing | Explicit, expiring, revocable share records owned by the resource's platform; revalidated on each open | Files, Docs, InfoDot |

### Routing registry (verified so far)
| Resource | Authoritative platform | Verified |
|---|---|---|
| Stored file or folder | Dot.Files | live; no API yet |
| Word-processing document | Dot.docs | live; document API absent |
| Question, solution | InfoDot (Knowledge) | live; currently global |
| Project, task, HR, finance, analytics, charts | Dot.Projects, Dot.Tasks, Dot.HR, Dot.Finance, Dot.Analytics, Dot.Charts | **not verified**; Tasks, HR, Finance, Analytics are not live; Projects and Charts are live but their resource models and routes have not been audited |

A document stored by Dot.Files and edited in Dot.docs is one resource with two roles (storage vs editor); the registry must record both, which is not possible until Files and Docs share a reference through an API rather than shared tables.

### Not to be claimed yet
Tenant isolation across platforms, cross-platform search, deep links to specific resources, cross-tenant sharing, audit of cross-platform access. Each arrives only with its step above and its tests.

## 8. Cross-company sharing: proposal, not built

Why this one waits: every other step moved data or checks between apps that already trusted each other. Sharing creates a *new way into a company's data for someone outside it*. How much access, for how long and who may grant it are product and risk decisions, so they are listed here to be answered, not guessed.

**What already exists.** Dot.docs has its own sharing: named collaborators (viewer, editor, admin, even from another company), public links with password and expiry. Dot.Files has none.

**Proposed v1 (Dot.Files first)**
- A share is a row owned by Dot.Files: `file_id`, recipient verified email, `expires_at`, `revoked_at`, `created_by`. InfoDot holds nothing.
- The recipient must sign in through InfoDot with that verified email; the share is checked on **every** open (`/o/{uuid}` and download), so revoking or expiring takes effect immediately and a forwarded link opens nothing for anyone else.
- Access is view and download of that one file. No folder listing, no company switch, no other file.
- Every create, open and revoke is written to an audit log the company's admins can read.
- The owner of the share can revoke at any time; deleting the file or removing the sharer from the company revokes it.

**Decisions needed**
1. Who may share: company owners and administrators only (recommended), or any editor?
2. Recipient: an existing InfoDot account by verified email only (recommended), or any email address via an emailed one-time link?
3. Longest allowed expiry: 30 days default, 90 maximum (recommended), or longer?
4. Should the recipient also be able to upload or comment, or strictly view and download (recommended)?
5. Should the sharer's company be notified by email when a share is opened for the first time?

Until these are answered nothing here is advertised or built. Dot.docs collaborators keep working as they do today.

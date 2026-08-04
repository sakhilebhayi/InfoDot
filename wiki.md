---
title: InfoDot — Platform Wiki
version: 1.0.0
status: active
owners: [InfoDot Platform Lead]
platform-id: infodot
last-review: 2026-08-04
---

# InfoDot

Purpose: this is InfoDot's own knowledge home — owned and maintained by the InfoDot team. It describes what this platform actually is, as implemented, and how it connects to the wider Dot Ecosystem.

---

## 1. What InfoDot Is

InfoDot is the hub of the Dot Ecosystem — the central identity provider that lets a user log in once and move between every connected Dot platform without re-authenticating (`App\Http\Controllers\Auth\EcosystemAuthController` receives the handoff, `App\Http\Controllers\Api\EcosystemTokenController` / `App\Livewire\EcosystemWidget` issue it). It also carries its own community/knowledge-base product: a public Q&A section (`Questions`), a public Solutions/how-to hub (`Solutions` + `Steps`), threaded comments and polymorphic likes on both, a lightweight social graph (`Associates`), and a team-scoped "Team Drive" storage layer (`Obj`/`File`/`Folder`) that is modeled and migrated but has no controller wired to it yet. Laravel 12, PHP 8.4/8.5, Livewire 3, Jetstream 5 with Teams enabled, PostgreSQL.

## 2. Architecture

| Layer | Technology | Notes |
|---|---|---|
| Framework | Laravel 12, PHP 8.4+ | |
| UI | Livewire 3, DaisyUI 5 | |
| Database | PostgreSQL | `DB_DATABASE=infodot` per `.env.example`, shared-instance ecosystem convention |
| Auth | Sanctum + Jetstream Teams (`Features::teams(['invitations' => true])` in `config/jetstream.php`) | Teams feature is genuinely on, but only the storage layer (`objects`/`files`/`folders`) actually has a `team_id` column — the community content (questions/solutions/comments/likes/associates) is deliberately global, not team-scoped (see §"Tenant Scope Audit") |
| Search | Laravel Scout (TNTSearch driver) | `Obj` and presumably `Comment`/other searchable models index into it |

## 3. Tenant Scope Audit (this pass)

Before adding any scope trait, every model in `app/Models/*.php` was checked against its migration for a `user_id`/`team_id` column and against its actual controllers/Livewire components for how it's queried:

| Model | Owns | Scoped this pass? | Why |
|---|---|---|---|
| `Obj`, `File`, `Folder` | `team_id` (`objects`/`files`/`folders` tables) | **Yes** — `HasTeamScope` | Genuine per-team private storage ("Team Drive"). Not yet wired to any controller, but the column and intent are real, and scoping it now means the first controller ever written against it is safe by default instead of depending on someone remembering `->where('team_id', ...)`. `Obj` already carried a `RelatesToTeams` trait with a `scopeForCurrentTeam()` local scope — but it's opt-in (must be called explicitly) and, per a repo-wide grep, is never actually called anywhere. Left in place untouched; `HasTeamScope` supersedes it as the real, automatic guard. |
| `Questions`, `Solutions`, `Steps`, `Comment`, `Like`, `Associates` | `user_id` (authorship) | **No** | These are genuinely public, shared community content — a Q&A feed and a Solutions/how-to hub every authenticated user browses regardless of who authored a given row (`QuestionsController::index()`/`SolutionsController::index()` intentionally list *everyone's* posts). `user_id` here records authorship for display and for the author's own edit/delete affordances, not tenant isolation — scoping these globally to the current user would break the product (a user would only ever see their own questions). This is the "legitimately global/shared" carve-out the pattern calls for, analogous to Dot.Notify's `NotifyPreference` exclusion, just for a different reason (public content vs. genuinely per-user settings). `Associates` (a follow/connection edge with both a `user_id` and an `associate_id`) is also excluded: `Livewire\Associates::connect()` deliberately queries both directions of the relationship (who I follow, and separately elsewhere who follows me), so a blanket `user_id = Auth::id()` scope would hide edges the app needs to read from the other side. |
| `Team`, `TeamInvitation`, `User`, `Membership` | n/a (Jetstream core) | No | Framework scaffolding, out of scope. |

## 4. HasTeamScope

`app/Models/Concerns/HasTeamScope.php` — an Eloquent global scope, applied via `use HasTeamScope;` to `Obj`, `File`, `Folder`. Mirrors Dot.Notify's `HasTeamScope` / Dot.Finance's `HasUserScope` exactly: `bootHasTeamScope()` registers a global scope that adds `where('<table>.team_id', Auth::user()->currentTeam->id)` whenever `Auth::check()` and the user has a current team. No controller changes were needed this pass — no controller currently queries `Obj`/`File`/`Folder` at all — but the trait is now load-bearing groundwork for whenever that storage layer is actually built out.

## 5. Testing

`tests/Feature/InfoDotTeamScopeTest.php::test_scope_alone_blocks_cross_team_access_even_without_an_explicit_where` proves the scope alone (no Policy, no explicit `where`) blocks cross-team reads on `File`: creates a file owned by team A, logs in as a user on team B, asserts `File::find($id)` is null and `File::query()->count()` is 0, then logs in as the owner and asserts both are non-null/1.

Full suite verified against real PostgreSQL (fresh `infodot_pilot` database, migrated clean): 93 tests carry a pre-existing PHP 8.5 deprecation notice (`PDO::MYSQL_ATTR_SSL_CA` referenced in `config/database.php`, unrelated to this pass, not touched) plus 1 clean unit test — **0 failures**, both before and after this pass's changes (92 deprecated/1 passed baseline → 93 deprecated/1 passed after adding the new regression test).

## 6. Static Analysis & Dependency Audit (this pass)

- **PHPStan/Larastan** (`larastan/larastan` was already a `require-dev` dependency; `phpstan.neon` already existed at level 5 with the Larastan extension) — `vendor/bin/phpstan analyse --memory-limit=1G` **ran successfully in this sandbox** (unlike every other platform in this rollout) and reported **no errors** against `app/`, including the new `HasTeamScope` trait and its three consumers.
- **`composer audit`** found 6 pre-existing `guzzlehttp/guzzle` advisories (1 high — CVE-2026-69246 noncanonical-host check bypass — plus 5 medium). Fixed via a clean transitive bump: `composer update guzzlehttp/guzzle guzzlehttp/psr7 guzzlehttp/promises --with-all-dependencies`. `composer.json`'s existing `^7.10` constraint already covered the patched versions, so only `composer.lock` changed. `composer audit` clean afterward; full test suite re-confirmed 0 failures post-update.

## Change Log

| Version | Date | Author | Change |
|---|---|---|---|
| 1.0.0 | 2026-08-04 | Platform-loop pass | Initial wiki. Architecture pass matching the Dot.Finance/Dot.Notify pattern (see Dot.Notify commit `e671436`, Dot.Finance commit `2f75bdb`): added `App\Models\Concerns\HasTeamScope`, an Eloquent global scope trait applied to `Obj`, `File`, and `Folder` (the only domain models genuinely owning a `team_id` column), scoping every query on those models to `Auth::user()->currentTeam->id` automatically. Deliberately **not** applied to `Questions`/`Solutions`/`Steps`/`Comment`/`Like`/`Associates` — real audit found these are genuinely global, publicly-browsed community content keyed by `user_id` for authorship display, not tenant isolation (see §3 for the full per-model reasoning, including why `Associates`' bidirectional follow-edge semantics rule it out too). No controller changes were needed — no controller currently queries the storage-layer models. Added `tests/Feature/InfoDotTeamScopeTest.php::test_scope_alone_blocks_cross_team_access_even_without_an_explicit_where`. No 403→404 assertion changes were needed: no Policy layer gates these models, and none were route-model-bound. Full suite re-run against real PostgreSQL after all changes: 93 deprecated (pre-existing, unrelated PHP 8.5 PDO constant notice) + 1 passed, 0 failed (up from 92/1/0 — the one new test). `vendor/bin/phpstan analyse --memory-limit=1G` (config already present from an earlier pass) ran successfully this time and reported no errors. `composer audit` found 6 pre-existing `guzzlehttp/guzzle` advisories (1 high, 5 medium); fixed via `composer update guzzlehttp/guzzle guzzlehttp/psr7 guzzlehttp/promises --with-all-dependencies`; audit clean afterward, full suite reconfirmed green. |

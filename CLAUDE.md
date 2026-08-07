# InfoDot — CLAUDE.md

**Project:** InfoDot — Ecosystem Hub for the Dot Ecosystem
**Stack (current, verified 2026-08-02):** Laravel 12 · PHP 8.4 · Livewire 3 · PostgreSQL 16 · Reverb 1.10 · Vite 6 · DaisyUI 5 · Alpine.js 3 · Sanctum 4 · Scout 10

---

## What InfoDot Is

InfoDot is the **hub** of the Dot Ecosystem — the central identity provider that lets a user log in once and move between every connected Dot platform without re-authenticating. It issues short-lived Sanctum handoff tokens (5 min TTL, `ecosystem:read` ability, one-time use) that satellite apps redeem via their own `/auth/ecosystem` endpoint.

**Core features already built:** Solutions hub · Q&A with threaded comments · polymorphic likes · user profiles · social graph · team management · real-time notifications (Reverb) · file storage · full-text search (Scout) · ecosystem SSO token issuance (`EcosystemTokenController`) and consumption (`EcosystemAuthController`) · a dashboard widget (`EcosystemWidget`) listing every registered platform, grouped by category, with one-click launch into any of them.

---

## Status: this file was stale, corrected 2026-08-02

Everything below the previous "Upgrade Phases" section described a migration plan as if not yet started. **That migration was already completed in an earlier session** (see `git log`: `feat: complete Laravel 12 upgrade — Livewire 3, Reverb, Vite, ecosystem SSO`, `feat: complete Phase 3 — Scout on Comment, Sentry, PHPStan level 5 clean`). This file simply was never updated to say so — exactly the kind of documentation drift the wider Dot Ecosystem's `os/12-README-Automation.md` was written to catch. Verified directly against the real code before writing this:

| Phase (of the original 5-phase plan) | Status |
|---|---|
| **1 — Stack Foundation** (Laravel 12, Vite, PostgreSQL, Reverb, Scout, DaisyUI 5, remove Vue) | ✅ Done. `composer.json`/`package.json` confirm target versions; zero `.vue` files; zero `@mix()` calls; zero `fullText()` migrations; `.env.example` targets `pgsql`; `laravel/reverb` v1.10.2 is in `composer.lock`. |
| **2 — Livewire 2 → 3 Rewrite** | ✅ Done. All 9 Livewire components use `#[Computed]`/`#[On]` attributes and typed properties — verified by reading `EcosystemWidget.php` directly. |
| **3 — Feature Completion** (Scout, Sentry, PHPStan L5, PHPUnit 11) | ✅ Done per commit history. Not independently re-verified this pass (no PHP available in the environment that wrote this update — see the Dot Ecosystem's `os/13-Engineering-State.md` §4 for why). |
| **4 — Ecosystem Hub Layer** | ✅ Done. `POST /api/ecosystem/token` (`EcosystemTokenController`), the platform-launcher widget (`EcosystemWidget`, not literally named `dot-switcher` as the original plan assumed, but functionally identical), and `config/ecosystem.php` all exist and work. |
| **5 — Dot.Files Integration** | ❌ Not done, and out of scope for the Dot Ecosystem work that produced this update — Dot.Files is a separate repo never touched by that session. |

## What changed 2026-08-02: platform registry reconciled against the real Dot Ecosystem

`config/ecosystem.php` previously listed 18 platforms, overlapping only partially with the actual 20-platform Dot Ecosystem tracked in `Dot.Brain/os/Appendix.md` (which itself is the authoritative registry — every one of those 20 platforms is a real, built Laravel/Jetstream app with its own `EcosystemAuthController` implementing the exact same token contract InfoDot expects, byte-for-byte verified). Nine real, built platforms were missing entirely: **Dot.Memory, Dot.Plug, Dot.Mines, Dot.Notify, Dot.Billing, Dot.Charts, Dot.Farms, Dot.HR, Dot.Dopemine**. All nine were added to `config/ecosystem.php` and grouped into `EcosystemWidget::groups()` (a new "Industry Verticals" category was added for Mines/Farms; the rest joined existing categories by domain fit).

The registry now has 27 entries: the 20 real Dot Ecosystem platforms, plus 7 (`Dot.Files`, `Dot.Docs`, `Dot.Forms`, `Dot.Sheet`, `Dot.Engage`, `Dot.Press`, `Dot.Tutor`) that are real separate repos from InfoDot's original, older plan but were never part of the Dot Ecosystem's build-out this session — kept in place rather than removed, per an explicit decision not to unilaterally drop them.

**Not yet verified:** whether those 7 repos actually implement the `/auth/ecosystem` receiving endpoint the way all 20 real Dot Ecosystem platforms do — they were never reviewed as part of this update. Treat their entries in the registry as aspirational until checked.

---

## Key Patterns (still accurate, kept from the original plan)

### Ecosystem SSO — receiving end (every satellite app has this)
```php
// routes/web.php
Route::get('/auth/ecosystem', [EcosystemAuthController::class, 'handle']);

// EcosystemAuthController
public function handle(Request $request): RedirectResponse
{
    $accessToken = PersonalAccessToken::findToken($request->query('token'));
    abort_if(
        ! $accessToken || ! $accessToken->can('ecosystem:read')
        || ($accessToken->expires_at && $accessToken->expires_at->isPast()),
        403
    );
    $user = $accessToken->tokenable;
    $accessToken->delete(); // one-time use
    Auth::login($user);
    return redirect()->route('dashboard');
}
```
Verified identical (modulo the final redirect route) between InfoDot's own copy and Dot.Billing's — this is the real, working contract every satellite app implements.

### Ecosystem SSO — issuing end (InfoDot only)
```php
// EcosystemWidget::launch() and EcosystemTokenController::issue()
$token = $user->createToken('ecosystem-handoff', ['ecosystem:read'], now()->addMinutes(config('ecosystem.handoff_ttl', 5)));
// redirect to: rtrim($platform['url'], '/') . '/auth/ecosystem?token=' . $token->plainTextToken
```

### Livewire 3 component pattern
```php
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ExampleComponent extends Component
{
    public string $query = '';

    #[Computed]
    public function results(): array
    {
        return Solution::search($this->query)->get()->toArray();
    }

    #[On('someEvent')]
    public function handleEvent(array $data): void
    {
        // handle it
    }
}
```

---

## Database

- **Driver:** PostgreSQL 16, `DB_DATABASE=infodot` — confirmed identical across InfoDot and every checked satellite app's `.env.example` (Dot.Billing, Dot.Ehail, Dot.Auction), so the shared-database assumption the SSO scheme depends on is structurally consistent, not just documented intent.
- **Shared tables:** `users`, `teams`, `team_user`, `personal_access_tokens`.
- Each satellite keeps its own domain tables but points at the same DB instance.
- **Caveat:** this confirms the *configuration* is consistent, not that a real shared Postgres instance has ever been stood up and migrated — no PHP/Postgres runtime existed in the environment that wrote this update. See `Dot.Brain/os/13-Engineering-State.md` §4.

## Testing

- **Framework:** PHPUnit 11, 31 test files present.
- **Target coverage:** 70%+ — commit history claims this was hit (`test: add coverage gap tests — hit 70%+ line coverage target`), not independently re-run.
- Run tests: `php artisan test` or `./vendor/bin/phpunit`
- Static analysis: `./vendor/bin/phpstan analyse --level=5`

## Dev Commands

```bash
php artisan serve              # Laravel on :8000
npm run dev                    # Vite on :5173
php artisan reverb:start       # WebSockets on :8080
php artisan queue:work         # Queue worker
php artisan migrate            # Run migrations
php artisan test               # Run test suite
./vendor/bin/phpstan analyse   # Static analysis
```

## Real next steps (not the old Phase 1–5 plan, which is done except Phase 5)

1. Actually stand up the shared PostgreSQL instance and run `composer install && php artisan migrate && php artisan test` for InfoDot and every satellite — nothing in this ecosystem has ever been executed against a real environment (see `os/13-Engineering-State.md` §4, ecosystem-wide, not InfoDot-specific).
2. Verify whether Dot.Files/Dot.Docs/Dot.Forms/Dot.Sheet/Dot.Engage/Dot.Press/Dot.Tutor actually implement the `/auth/ecosystem` receiving contract — currently just asserted, not checked.
3. Dot.Files integration (original Phase 5): shared-DB pointing, PR triage, `/auth/ecosystem` endpoint — still not started, still out of scope until someone reviews that repo directly.
4. None of the 20 real Dot Ecosystem platforms publish real DKP Knowledge Packs to Dot.Brain yet (`Dot.Brain/os/19-Knowledge-Packs.md`) — InfoDot itself has no DKP integration either; not this file's problem to solve, but worth knowing InfoDot inherits the same gap as everything else in the ecosystem.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- This project upgraded from Laravel 10 without migrating to the new streamlined Laravel file structure.
- This is perfectly fine and recommended by Laravel. Follow the existing structure from Laravel 10. We do not need to migrate to the new Laravel structure unless the user explicitly requests it.

## Laravel 10 Structure

- Middleware typically lives in `app/Http/Middleware/` and service providers in `app/Providers/`.
- There is no `bootstrap/app.php` application configuration in a Laravel 10 structure:
    - Middleware registration happens in `app/Http/Kernel.php`
    - Exception handling is in `app/Exceptions/Handler.php`
    - Console commands and schedule register in `app/Console/Kernel.php`
    - Rate limits likely exist in `RouteServiceProvider` or `app/Http/Kernel.php`

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>

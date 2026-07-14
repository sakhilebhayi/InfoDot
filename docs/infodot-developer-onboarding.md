# InfoDot Developer Onboarding Guide

## Purpose

This guide gets a new engineer from zero to a running local InfoDot development environment in one session. It also explains the project structure, branch strategy, and contribution workflow.

---

## What InfoDot Is

InfoDot is the ecosystem hub for the BluPin / SK Digital Dot platform suite. It handles identity, teams, knowledge sharing (solutions + Q&A), file storage, notifications, and real-time collaboration. Every other Dot platform — Dot.Files, Dot.Agents, Dot.Press, and others — authenticates users through InfoDot.

Read `docs/infodot-upgrade-plan.md` for the full context on the current stack and where it is heading.

---

## Prerequisites

Before starting, ensure you have the following installed:

- PHP 8.3 or 8.4
- Composer 2
- Node.js 20+ and npm 10+
- PostgreSQL 16 (local instance or Docker)
- Redis (local instance or Docker)
- Git

Optional but useful:
- Meilisearch (for search — TNTSearch is used as a fallback in dev)
- Laravel Herd or Valet for local HTTPS
- TablePlus or pgAdmin for database browsing

---

## Local Setup

### 1. Clone the repository

```bash
git clone https://github.com/sakhileb/InfoDot.git
cd InfoDot
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install JavaScript dependencies

```bash
npm install
```

### 4. Configure the environment

```bash
cp .env.example .env
php artisan key:generate
```

Then edit `.env` with your local values:

```env
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=infodot
DB_USERNAME=postgres
DB_PASSWORD=your-local-password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

SCOUT_DRIVER=tntsearch
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=infodot-local
REVERB_APP_KEY=infodot-local-key
REVERB_APP_SECRET=infodot-local-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

### 5. Create the database

```bash
# Using psql
createdb infodot
```

Or create it via your preferred PostgreSQL client.

### 6. Run migrations and seed

```bash
php artisan migrate
php artisan db:seed
```

### 7. Build front-end assets

```bash
npm run dev
```

This starts Vite's dev server with hot reload. Keep this terminal open during development.

---

## Running The Stack

You need up to four processes running locally for full functionality. Open a terminal for each:

```bash
# Terminal 1: Web server
php artisan serve

# Terminal 2: Vite (hot-reload front-end)
npm run dev

# Terminal 3: Reverb (WebSockets)
php artisan reverb:start

# Terminal 4: Queue worker
php artisan queue:work
```

Visit `http://localhost:8000`. Log in with a seeded user or register a new account.

---

## Project Structure

```
app/
  Actions/         # Single-purpose action classes (Fortify/Jetstream + domain)
  Console/         # Artisan commands
  Events/          # Domain events
  Http/
    Controllers/   # Thin controllers that delegate to actions
    Middleware/    # HTTP middleware
    Requests/      # Form request validation
    Resources/     # API resource transformers
  Livewire/        # Livewire components
  Models/          # Eloquent models
  Notifications/   # Laravel notification classes
  Policies/        # Authorization policies
  Providers/       # Service providers
  Support/         # Shared utilities and helpers

config/            # All configuration files, including config/ecosystem.php
database/
  factories/       # Model factories for testing and seeding
  migrations/      # Database schema migrations
  seeders/         # Development seed data

docs/              # All project documentation (you are here)

resources/
  css/             # Tailwind + DaisyUI styles
  js/              # Alpine.js, Echo, bootstrap.js
  views/           # Blade templates
    livewire/      # Blade views for Livewire components

routes/
  api.php          # API routes (Sanctum-authenticated)
  web.php          # Web routes
  channels.php     # Broadcasting channel authorization

tests/
  Feature/         # HTTP and Livewire feature tests
  Unit/            # Isolated unit tests
```

---

## Key Files To Read First

| File | Why it matters |
|---|---|
| `config/ecosystem.php` | All Dot platform URLs and configuration |
| `routes/api.php` | Ecosystem and API endpoints |
| `routes/web.php` | Web entry points |
| `app/Models/User.php` | Central model — touches most features |
| `app/Providers/AppServiceProvider.php` | Bootstrapping, bindings |
| `docs/infodot-upgrade-plan.md` | Full context on where the codebase is heading |
| `docs/infodot-architecture-guidelines.md` | How to structure new code |
| `docs/infodot-security-hardening.md` | Security rules to follow |

---

## Branch Strategy

| Branch | Purpose |
|---|---|
| `main` | Production-ready code. Direct commits are not allowed. |
| `upgrade/laravel-12` | Active upgrade branch for the Phase 1 stack migration |
| `feature/{description}` | New features |
| `fix/{description}` | Bug fixes |
| `chore/{description}` | Dependency updates, tooling, non-functional changes |

All work should branch from `main` (or `upgrade/laravel-12` during the active upgrade phase) and return via a pull request.

---

## Contribution Workflow

1. Pick up an issue or task.
2. Create a branch: `git checkout -b feature/ecosystem-dot-switcher`.
3. Write the code.
4. Write or update tests.
5. Run the full test suite locally: `php artisan test`.
6. Run static analysis: `./vendor/bin/phpstan analyse --level=5`.
7. Push and open a pull request against `main`.
8. Address review comments.
9. Merge after approval and CI green.

### Pull request requirements

- Tests pass.
- PHPStan passes at level 5.
- No new security vulnerabilities (`composer audit` clean).
- Description explains what changed and why.
- Migrations include a working `down()` method.
- New API endpoints follow `docs/infodot-api-standards.md`.

---

## Common Artisan Commands

```bash
php artisan serve                     # Start web server on :8000
php artisan migrate                   # Run pending migrations
php artisan migrate:rollback          # Roll back last migration batch
php artisan migrate:fresh --seed      # Drop and recreate with seed data
php artisan queue:work                # Process queue jobs
php artisan queue:restart             # Signal workers to restart after deploy
php artisan reverb:start              # Start WebSocket server on :8080
php artisan scout:import "App\Models\Solution"  # Re-index Scout model
php artisan tinker                    # Interactive REPL
php artisan test                      # Run test suite
php artisan test --filter=SolutionTest         # Run one test class
php artisan test --parallel           # Run tests in parallel
php artisan about                     # Show environment and config status
php artisan config:cache              # Cache config for production
php artisan route:list                # Show all registered routes
```

---

## Troubleshooting

### Assets not updating

Ensure Vite dev server is running: `npm run dev`. If production-style assets are cached, run `php artisan view:clear`.

### Database connection errors

Confirm PostgreSQL is running and the credentials in `.env` match. Run `php artisan migrate:status` to check the connection.

### Queue jobs not processing

Ensure Redis is running and `QUEUE_CONNECTION=redis` is set. Start the worker: `php artisan queue:work`.

### WebSocket connection failing

Ensure Reverb is running on port 8080 and `VITE_REVERB_APP_KEY` in `.env` matches `REVERB_APP_KEY`. Check browser console for connection errors.

### PHPStan errors on existing code

The codebase may have pre-existing PHPStan issues from before level-5 enforcement was added. Do not introduce new violations. Fixing pre-existing issues is welcome but not required in unrelated PRs.

### Tests failing due to database state

Run `php artisan migrate:fresh --seed` to reset the development database. Tests use `RefreshDatabase` so they are isolated from the dev DB.

---

## Getting Help

- Read the relevant guide in `docs/` first.
- Check the open issues and pull requests on GitHub for context.
- Ask in the team channel with a specific question and what you have already tried.

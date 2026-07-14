# InfoDot Database Conventions

## Purpose

InfoDot uses a shared PostgreSQL 16 database across all Dot platforms. Getting database conventions right early prevents name collisions, schema drift, and migration conflicts as the number of connected platforms grows.

---

## Shared Database Architecture

The InfoDot PostgreSQL instance is the single source of truth for identity, teams, and permissions. Every satellite Dot platform connects to the same instance.

**Tables owned by InfoDot (shared across all platforms):**

| Table | Owner | Purpose |
|---|---|---|
| `users` | InfoDot | All authenticated users across the ecosystem |
| `teams` | InfoDot | Team constructs shared across platforms |
| `team_user` | InfoDot | User-team membership and roles |
| `personal_access_tokens` | InfoDot | Sanctum tokens for all platforms |
| `password_reset_tokens` | InfoDot | Password reset flow |
| `sessions` | InfoDot | Web session storage |

**Each satellite platform owns its domain tables.** Satellite tables must not modify the shared tables above. Cross-platform relationships use foreign keys to `users.id` and `teams.id` only.

---

## Naming Conventions

### Tables

- Use `snake_case` and plural names: `solutions`, `team_invitations`, `file_objects`
- Pivot tables: `{table_a}_{table_b}` in alphabetical order: `solution_tag`, `team_user`
- Do not prefix tables with the platform name unless there is a genuine collision risk: use `files` not `dotfiles_files`

### Columns

- Use `snake_case`
- Primary key: `id` (bigint, auto-increment)
- Foreign keys: `{table_singular}_id` — for example `user_id`, `team_id`, `solution_id`
- Timestamps: `created_at`, `updated_at` (Laravel standard)
- Soft delete: `deleted_at`
- Boolean columns: prefix with `is_` or `has_`: `is_active`, `has_accepted`, `is_published`
- Status columns: use an enum or a string with application-level validation — avoid bare integers for status

### Indexes

- Name indexes explicitly: `{table}_{column}_index` or `{table}_{column}_unique`
- Primary keys are always named `{table}_pkey` by PostgreSQL automatically
- Foreign key indexes: `{table}_{column}_foreign`

---

## Migration Conventions

### One migration per logical change

Do not bundle multiple schema changes into one migration unless they are genuinely atomic. A migration that adds a column, creates an index, and seeds data is hard to roll back and hard to review.

### Always write the `down()` method

```php
public function up(): void
{
    Schema::table('solutions', function (Blueprint $table) {
        $table->string('slug')->unique()->after('solution_title');
    });
}

public function down(): void
{
    Schema::table('solutions', function (Blueprint $table) {
        $table->dropUnique(['slug']);
        $table->dropColumn('slug');
    });
}
```

Omitting `down()` makes rollbacks impossible and will fail CI checks.

### No data mutations in schema migrations

Migrations that modify schema structure must not also update data. Run data migrations as separate migrations or as seeder commands. This keeps schema history clean and reversible.

### Column ordering

Add new columns in this order:
1. Primary key
2. Foreign keys
3. Domain-specific fields
4. Boolean/status flags
5. `deleted_at` (if soft deletes)
6. `created_at`, `updated_at`

### Do not use MySQL-specific features

InfoDot has migrated from MySQL to PostgreSQL. The following are not available in PostgreSQL and must not be used:

- `$table->fullText()` — use Laravel Scout instead
- `$table->set()` — use string with validation
- `$table->mediumText()` or `$table->tinyText()` — use `$table->text()` (PostgreSQL `text` is unlimited)
- `$table->double()` — use `$table->decimal()` with explicit precision
- MySQL-specific index hints or storage engines

---

## Indexing Strategy

PostgreSQL is not MySQL. Over-indexing costs write performance. Under-indexing costs query performance. Design indexes around real, measured query patterns.

### Always index

- Foreign keys — PostgreSQL does not automatically index foreign keys
- Columns used in `WHERE` clauses on high-traffic tables
- Columns used in `ORDER BY` on paginated lists
- Columns used in `JOIN` conditions

### Consider indexing

- Columns used in Scout fallback queries (before Scout is fully deployed)
- `status` and `is_published` columns on large tables where filtered queries are common
- Composite indexes for queries that filter on two columns together frequently

### Do not over-index

- Columns that are rarely queried
- Boolean columns on small tables
- Columns on tables that change frequently (high write cost)

### Adding an index in a migration

```php
// Single column
$table->index('user_id');

// Composite
$table->index(['team_id', 'created_at']);

// Unique
$table->unique('slug');

// Named explicitly
$table->index('status', 'solutions_status_index');
```

---

## PostgreSQL-Specific Type Guidance

| Use case | Column type |
|---|---|
| Short string (names, slugs) | `string` → `varchar(255)` |
| Long text (bodies, descriptions) | `text` → PostgreSQL `text` |
| JSON data | `jsonb` — use `$table->jsonb()`, not `$table->json()` |
| Numeric IDs | `bigIncrements` / `unsignedBigInteger` |
| Amounts (money) | `decimal(19, 4)` — never `float` or `double` |
| Boolean | `boolean` |
| Timestamps | `timestamps()` for `created_at` / `updated_at` |
| IP addresses | `ipAddress()` |
| UUIDs | `uuid()` |

Prefer `jsonb` over `json` in PostgreSQL — it is indexed and queried more efficiently.

---

## Soft Deletes

Use soft deletes on all content models where audit history matters: `Solution`, `Question`, `Comment`, `Team`, `User`.

Do not use soft deletes on:
- pivot tables
- log and audit trail tables
- token tables

Soft-deleted records must never appear in public-facing queries. Ensure every relevant scope applies `withoutTrashed()` by default.

---

## Shared Table Contract

Any satellite Dot platform that reads or writes to InfoDot's shared tables must:

1. Only read from `users`, `teams`, `team_user`, `personal_access_tokens`.
2. Never write to these tables directly — all writes must go through InfoDot's API.
3. Reference `users.id` and `teams.id` as foreign keys in its own domain tables.
4. Not add columns to shared tables. Request additions through an InfoDot schema migration.

This contract exists to prevent platform-specific schema drift on tables that affect every platform.

---

## Search And Full-Text

Full-text search is handled by Laravel Scout. Do not add PostgreSQL `tsvector` columns, GIN indexes for text search, or `LIKE '%query%'` fallbacks to new code.

- Add the `Searchable` trait to models that need to be searched.
- Define `toSearchableArray()` with only the fields that should be indexed.
- Run `php artisan scout:import "App\Models\Solution"` after a bulk data change.
- In development, TNTSearch is the Scout driver (file-based).
- In production, Meilisearch is the Scout driver.

---

## Database Checklist For New Features

Before a migration reaches the main branch:

- [ ] Table and column names follow `snake_case` conventions
- [ ] Foreign keys have explicit indexes
- [ ] `down()` method is fully implemented
- [ ] No MySQL-specific types or functions
- [ ] No `fullText()` index declarations
- [ ] `jsonb` used instead of `json` where JSON storage is needed
- [ ] Shared tables are not modified without a cross-platform review
- [ ] New searchable content uses the `Searchable` trait, not LIKE queries

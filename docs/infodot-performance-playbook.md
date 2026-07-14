# InfoDot Performance Playbook

## Purpose

Performance work in InfoDot should focus on perceived speed, database efficiency, and stability under collaborative usage. The product already includes search, comments, notifications, files, and real-time behavior, which means performance issues will compound quickly if they are left to late-stage cleanup.

## Performance Priorities

1. Fast initial page rendering for authenticated screens.
2. Predictable query cost on list, search, and notification surfaces.
3. Efficient Livewire updates without excessive round-trips.
4. Stable background processing for notifications, indexing, and uploads.
5. Smooth ecosystem handoff between platforms.

## What To Measure First

Before optimizing, capture baseline numbers for these flows:

1. dashboard load time
2. solutions list load time
3. question detail render time
4. notification fetch time
5. file listing latency
6. ecosystem token issuance and handoff success time

Also capture:

- database query count per request
- slowest SQL statements
- queue backlog and processing time
- asset bundle size
- largest Livewire payloads

## Likely Hotspots In This Codebase

Given the current stack and feature set, the highest-risk hotspots are:

- N+1 queries in Livewire-rendered lists
- repeated search or filter queries during component updates
- over-fetching related models for comments, teams, and notifications
- large front-end bundles carried over from the Mix to Vite transition
- synchronous work during requests that should move to queues
- file and media operations blocking UI responses

## Optimization Rules

### 1. Make database access explicit

- Eager load relationships intentionally.
- Paginate lists that can grow without bound.
- Use selective columns for heavy list pages.
- Add indexes for frequent filters, sorts, and join keys.
- Review query plans for expensive pages after PostgreSQL migration.

### 2. Reduce Livewire render cost

- Avoid running expensive queries directly inside render when the result is stable enough to cache or derive once.
- Split overly broad components into smaller targeted components.
- Use debounced or lazy updates where real-time input is not essential.
- Avoid serializing large nested objects into component state.

### 3. Move slow side effects off the request path

Candidates for async execution:

- notifications
- search indexing
- file post-processing
- analytics events
- non-critical cross-platform sync work

### 4. Treat search as infrastructure

Do not let fallback database text matching become the default behavior.

- Use Scout consistently.
- Keep searchable payloads minimal and relevant.
- Re-index intentionally during migrations and large data changes.
- Track search latency separately from normal page requests.

### 5. Keep front-end delivery lean

- Audit asset bundles after the Vite migration.
- Remove dead Vue-era dependencies and code paths.
- Load page-specific assets only when needed.
- Keep icon and illustration usage disciplined.

## Caching Opportunities

Use caching where the staleness risk is low and invalidation is clear.

Good candidates:

- dashboard summary widgets
- frequently used navigation metadata
- team membership lookups
- platform directory or ecosystem config reads
- popular search suggestions

Avoid caching areas where correctness and immediacy are more important than speed unless invalidation is explicit.

## Queue And Background Work

Queue health becomes critical once notifications, indexing, uploads, and ecosystem activity all grow.

- Set target processing time budgets for common jobs.
- Monitor failed jobs and retry storms.
- Separate heavy and light jobs if the queue mix becomes uneven.
- Keep job payloads small and serializable.

## Release Performance Checklist

Before each release, verify:

- no obvious N+1 regressions on primary screens
- no unbounded lists without pagination or chunking
- slow queries are identified and either fixed or accepted knowingly
- assets are built and bundle sizes reviewed
- queue workers can keep up with expected activity
- real-time and notification paths do not block page responses

## Suggested Tooling And Practices

- Laravel Telescope or Pulse for request and query visibility in non-production environments
- PostgreSQL `EXPLAIN ANALYZE` for expensive queries after the DB switch
- application-level timing around ecosystem token issuance and handoff
- targeted load testing on search, notifications, and dashboard queries
- performance budgets captured in PR review for high-traffic surfaces

## Definition Of Done For Performance Changes

- A concrete bottleneck was measured before and after.
- The optimization improved either latency, query count, bundle size, or queue time.
- The change did not hide correctness issues behind caching or stale state.
- The result can be maintained by the next engineer without guesswork.
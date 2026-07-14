# InfoDot Architecture Guidelines

## Purpose

InfoDot is evolving from a single Laravel application into the identity and collaboration hub for a multi-platform ecosystem. That means the codebase needs stronger boundaries, more reusable domain logic, and cleaner rules around where behavior belongs.

## Architectural Goals

1. Keep domain rules independent from presentation details.
2. Make ecosystem integration explicit instead of incidental.
3. Reduce duplication between controllers, Livewire components, jobs, and notifications.
4. Prepare the codebase for Laravel 12, Livewire 3, PostgreSQL, and Reverb without introducing upgrade-specific chaos.
5. Keep high-change areas easy to test.

## Recommended Layering

Use a consistent structure for new or refactored features.

### Presentation layer

Contains:

- Blade views
- Livewire components
- controllers for HTTP entry points
- request validation objects

Rules:

- Keep this layer thin.
- Do not bury business rules in Blade or Livewire lifecycle methods.
- Use presentation code to orchestrate, not decide.

### Application layer

Contains:

- actions
- services with clear use-case boundaries
- jobs for async workflows
- query builders or read-model helpers where needed

Rules:

- Each action should represent a meaningful use case.
- Avoid "god services" with unrelated methods.
- Keep write operations and side effects easy to trace.

### Domain layer

Contains:

- models
- policies
- value-like objects where useful
- domain-specific enums or constants

Rules:

- Put invariants close to the model or domain action that owns them.
- Prefer explicit domain names over generic helper utilities.
- Keep authorization rules centralized in policies or dedicated guards.

### Infrastructure layer

Contains:

- search adapters
- storage integrations
- notification channels
- broadcasting configuration
- third-party ecosystem client logic

Rules:

- Hide vendor-specific behavior behind stable interfaces where the app depends on it.
- Keep config-driven integration details out of domain logic.

## Livewire 3 Migration Rules

Because Livewire 2 to 3 is a structural rewrite, use the migration to improve boundaries instead of copying old patterns forward.

- Type all public properties.
- Move complex state mutation into dedicated methods or actions.
- Replace event sprawl with explicit, documented event flows.
- Use computed properties only for derived values, not hidden queries with large cost.
- Keep components focused on one job: search, upload, navigation, list, detail, composer.

## Feature Design Pattern

For new features, prefer this shape:

1. Request or Livewire interaction receives input.
2. Validation object or component rules normalize input.
3. An action class executes the use case.
4. Models persist the result.
5. Events, notifications, and broadcasts fire from the application layer.
6. The UI renders read models or fresh state.

## Bounded Areas To Make Explicit

InfoDot should treat these as distinct domains even if they currently live in one repository:

- identity and authentication
- teams and memberships
- knowledge content: solutions, questions, comments
- notifications and activity
- files and storage metadata
- ecosystem platform switching and token handoff

Each area should have well-named actions, policies, tests, and query paths.

## Anti-Patterns To Reduce

- fat controllers that both validate and implement business rules
- Livewire components issuing multiple hidden database queries during render
- repeated authorization checks scattered across templates
- static helper methods that hide important write logic
- direct third-party API calls from UI components
- duplicated search logic across models and controllers

## Data And Persistence Guidance

With PostgreSQL as the target database, schema and query decisions should assume a relational database that rewards explicit indexing and query design.

- Design indexes around real query patterns, not assumptions.
- Prefer constrained, well-named columns over generic JSON where relational access matters.
- Use Scout consistently for search concerns instead of ad hoc database text matching.
- Keep migrations reversible and avoid framework-specific shortcuts that lock you to MySQL behavior.

## Testing Strategy For Architecture

Each refactor should improve test shape.

- Feature tests should prove end-to-end behavior.
- Action tests should cover business rules directly when flows are non-trivial.
- Policy tests should cover role and team boundaries.
- Performance-sensitive query paths should have targeted regression checks where possible.

## Refactor Priorities

1. High-traffic Livewire components
2. Authentication and ecosystem token flows
3. Search and content discovery
4. Notifications and real-time updates
5. Shared team and permission logic

## Definition Of Done For Architectural Changes

- The feature has a clear owning layer.
- Business rules are no longer trapped in UI code.
- Queries and side effects are easier to trace.
- The change reduced duplication or clarified a boundary.
- Tests cover the behavior at the correct level.
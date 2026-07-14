# InfoDot Improvement Index

This document groups the practical improvement guides for InfoDot. The goal is to make product, design, and engineering decisions easier to align while the platform is upgraded from the current stack to the target Laravel 12 ecosystem hub.

## Document Set

### Product and UX
- **UI design direction:** `infodot-ui-design-guide.md`
- **User experience improvements:** `infodot-ux-improvement-roadmap.md`

### Code Quality and Architecture
- **Code architecture and system design:** `infodot-architecture-guidelines.md`
- **Performance and scalability:** `infodot-performance-playbook.md`
- **API design standards:** `infodot-api-standards.md`
- **Database schema and migration conventions:** `infodot-database-conventions.md`
- **Testing strategy and coverage targets:** `infodot-testing-strategy.md`

### Security
- **Application security hardening (OWASP Top 10):** `infodot-security-hardening.md`
- **Ecosystem SSO and token security:** `infodot-ecosystem-auth-security.md`

### Operations and Engineering
- **Deployment guide:** `infodot-deployment-guide.md`
- **Developer onboarding:** `infodot-developer-onboarding.md`

## How To Use These Guides

1. Use the UI guide when designing or refactoring screens, components, and visual patterns.
2. Use the UX roadmap when prioritizing user-facing friction, onboarding, navigation, and workflow improvements.
3. Use the architecture guide before adding new platform features, integrations, or Livewire components.
4. Use the performance playbook during profiling, feature delivery, and release hardening.

## Suggested Working Rhythm

1. Pick one user journey each sprint: sign-in, finding a solution, asking a question, managing files, switching between Dot platforms.
2. Review that journey against all four guides.
3. Ship a narrow set of improvements that touch design, behavior, code structure, and performance together.
4. Capture before-and-after metrics: completion rate, latency, query count, support issues, and engagement.

## Improvement Principles

- Prioritize clarity over feature density.
- Prefer reusable primitives over one-off screens.
- Keep ecosystem behavior consistent across every Dot platform.
- Treat performance as part of product quality, not post-launch cleanup.
- Reduce cognitive load before adding new capabilities.
# Brand experience baselines: InfoDot (two demonstrations, one repository)

> **Advisory. Written by an AI reviewer. No profile is ratified.** This is a first baseline from the Brand Feeling Engine, run on InfoDot, in two demonstrations (the Knowledge area and the business-owner journeys). Nothing here blocks a merge or a deploy, nobody at the company has reviewed it yet, and the brand profile it is measured against is a draft that the owner has not ratified. Scores are honest and low on purpose: unmeasured checks count as zero and are listed, and a product cannot be `release_ready` without observed or customer evidence that does not exist yet.

This folder holds **two reports** for the same commit, because the Brand Feeling Engine rollout uses InfoDot for two different demonstrations. They read the same repository and the same `infodot` brand profile; they differ in the surface that was rendered, measured and assessed.

| | Knowledge area (mentoring surface) | Business-owner journeys (customer-facing) |
|---|---|---|
| Surfaces | Questions, Solutions, Library, Topics, moderation | Home gate, Platforms, People, Analytics, Profile, Points, and the first-contact pages (root, sign-in, register) |
| **Evidenced score** | **21.30 / 100** | **20.62 / 100** |
| Ceiling | 62.20 | 62.20 |
| Attainment | 34.24 % | 33.15 % |
| State | `not_ready` | `not_ready` |
| Confidence | Low | Low |
| Blockers | 1 | 1 |

The landing-page rebuild (roadmap R-10) is **out of scope** for both and was not assessed as work to do; the root page is counted only where a business owner's journey starts on it.

## Knowledge area (mentoring surface)

| Dimension | Status | Points earned / weight | Ceiling (this evidence) | AI expert level (0-4) |
|---|---|---|---|---|
| Emotional clarity and positioning | partially evidenced | 1.53 / 8 | 4.00 | 3 |
| Brand distinctiveness | partially evidenced | 1.38 / 7 | 4.20 | 3 |
| Usability and cognitive load | partially evidenced | 3.29 / 12 | 7.20 | 3 |
| Visual and interaction craftsmanship | partially evidenced | 1.09 / 9 | 7.20 | 3 |
| Functional completeness | partially evidenced | 6.13 / 10 | 9.00 | - |
| Trust and transparency | partially evidenced | 2.73 / 10 | 7.00 | 3 |
| Perceived and demonstrated value | not measured | 0.00 / 8 | 4.00 | - |
| Accessibility and inclusion | partially evidenced | 3.51 / 12 | 8.40 | 2 |
| Cross-platform consistency | partially evidenced | 0.38 / 6 | 4.80 | - |
| Reliability and recovery from errors | partially evidenced | 1.28 / 8 | 6.40 | - |
| Retention through continuing value | not measured | 0.00 / 5 | 0.00 | - |
| Measurable customer outcomes | not measured | 0.00 / 5 | 0.00 | - |

**Blockers recorded by the engine:**
- WCAG A failure of criterion 1.1.1. (`d8.labels_alt_lang.a1392d2471c9`, resources/views/livewire/solution-list.blade.php)

Main gaps: the solution search page removes outlines on 25 elements with no replacement (WCAG 2.4.7); one dark-theme focus ring is 1.3:1; reduced motion is only partly covered (4 of 49 motion declarations); the privacy text still lists "likes", which PRODUCT.md rules out, and does not mention the third-party font and icon services loaded on every page; `/up` answers OK without probing the database; `DESIGN.md` forbids side-stripe borders yet `infodot.css` has six. What is good: The Yard is applied consistently across questions, solutions, topics and library, every list, state and reference tells the truth (Waiting, Solved only after acceptance, who can see what), no counters or likes, no overflow on the 13 Knowledge pages at 375, 768 or 1280 px (measured), 835 tests pass.

## Business-owner journeys (customer-facing)

| Dimension | Status | Points earned / weight | Ceiling (this evidence) | AI expert level (0-4) |
|---|---|---|---|---|
| Emotional clarity and positioning | partially evidenced | 1.53 / 8 | 4.00 | 3 |
| Brand distinctiveness | partially evidenced | 1.07 / 7 | 4.20 | 2 |
| Usability and cognitive load | partially evidenced | 3.29 / 12 | 7.20 | 3 |
| Visual and interaction craftsmanship | partially evidenced | 1.09 / 9 | 7.20 | 3 |
| Functional completeness | partially evidenced | 6.13 / 10 | 9.00 | - |
| Trust and transparency | partially evidenced | 2.35 / 10 | 7.00 | 2 |
| Perceived and demonstrated value | not measured | 0.00 / 8 | 4.00 | - |
| Accessibility and inclusion | partially evidenced | 3.51 / 12 | 8.40 | 2 |
| Cross-platform consistency | partially evidenced | 0.38 / 6 | 4.80 | - |
| Reliability and recovery from errors | partially evidenced | 1.28 / 8 | 6.40 | - |
| Retention through continuing value | not measured | 0.00 / 5 | 0.00 | - |
| Measurable customer outcomes | not measured | 0.00 / 5 | 0.00 | - |

**Blockers recorded by the engine:**
- WCAG A failure of criterion 1.1.1. (`d8.labels_alt_lang.a1392d2471c9`, resources/views/livewire/solution-list.blade.php)

(Same blocker as above: it comes from the repository-wide static check, not from this surface.)

Main gaps: the first thing a business owner meets (root page, sign-in, register) is a generic marketing page and sign-in form in a different type system and layout from The Yard, which PRODUCT.md says InfoDot must not resemble, with 18 rendered low-contrast hits; the Home gate overflows by 11 px at 375 px (the stencil font was missing in the render, so treat as unverified); "25 of 25 platforms are live" comes from a configuration flag and was not and could not be checked against the hosts; points-lapse wording ("N points lapse within 30 days") and any reward mechanics should go through the Dopemine ethics gate, which this baseline does not replace. What is good: the Home gate names the company, the person's role and the people, with one door per area; Platforms is a plain register with one "Open signed in" action per row; Points states its rules and its unavailable and empty states honestly.

## Findings that are probably false positives or need care

- **The one blocker is real but unreachable.** `resources/views/livewire/solution-list.blade.php` has an `<img>` with no `alt` (and a hotlinked random stock image). The `SolutionList` component is never mounted anywhere, so no visitor can see it; deleting the component clears the blocker. The engine cannot tell a dead template from a live one.
- `d8.token_pair_contrast` (12 majors): most are focus rings compared against the page background although they sit on the search pill or the button; one is real (the Points checkbox ring is `--ix-signal`, 1.3:1 on the dark theme).
- Static checks cover the whole repository; the engine has no way to restrict them to one surface, so both InfoDot reports carry the same automated static evidence. What differs is the rendered-page evidence and the expert assessment.
- `d5.route_inventory_vs_claims` (16) is mostly useful here: `README_FEATURES.md` and `README.md` still list features such as "Like Answers" and "Social graph" (which PRODUCT.md rules out) and others no route implements. That is stale documentation, not missing features.
- Detector checks read 0.00 where counts pass the engine's fixed ceiling of 10.

## How this baseline was produced

- **Engine.** Dot.Brain brand engine 0.2.0 (Dot.Brain commit `2019312`), commands `analyze`, `evidence assemble`, `score`, `plan`. The engine reads files and reports, runs no tool itself and calls no network. Rubric BXQS 1.0.0 (sha256 `f5a4f8cb49b8`, **proposed, not ratified**). Profile `infodot@0.1.0` (**draft, not ratified**): the product has its own PRODUCT.md and DESIGN.md ("The Yard") but no ratified product profile, so the draft `infodot` brand profile is used. That profile records an unresolved conflict between InfoDot's two design guides (owner decision pending).
- **Checkout.** Commit `c5d15b5` of `main`, analysed untouched.
- **Run locally, not against any live host.** A separate clone, Laravel's own test kernel, SQLite `:memory:` only, a `.env` holding only the app key and the sqlite database lines, `PAO_DISABLE=1`, no MySQL or Postgres, no outbound requests. Synthetic company ("Northwind Finance", three people), four questions, two solutions, topic filing and two library entries. The Dot.Dopemine points service was replaced by the test suite's own fake (never the real service), so the Points figures are invented. The repository's own PHPUnit suite was also run on SQLite `:memory:` and its JUnit file given to the engine: 835 tests, 831 passed, 0 failed, 4 skipped.
- **Rendered-page evidence.** Synthetic-data pages were rendered to HTML, served from a local static server and opened in headless Chrome; details for both reports are in the paragraph below.
- **Not installed, so not measured:** axe-core and Lighthouse (no global tool was installed for this review). No assistive-technology test, no user session and no customer feedback exist, so dimensions 11 and 12 and every observed or customer metric are **not measured**.
- **Expert assessment.** Written by an **AI reviewer** (assessor `ai-reviewer-fresh-2026-10-23`, kind `ai`) that did not build this product in this run. It is AI-only expert evidence: the rubric counts it at no more than half. `built_by` is declared as `prior-ai-sessions-and-owner`; independence is declared, not proven. No user was observed and nobody was asked how they feel.

For the Business-owner journeys report the rendered pages were: home, root (signed in and signed out), sign-in, register, platforms, people, analytics, profile, profile edit, points and the Dot.Files door (12 pages). For the Knowledge report: questions, ask, two question views, solutions, create, a solution view, library, topics, a topic, solution search and two moderation pages (13 pages). The design detector ran on the Knowledge source files and shared layout/styles (9 hits), on the journeys source files and shared layout/styles (9 hits), and on the snapshots (27 and 52 hits after dropping 4 unresolved-background contrast hits from the journeys set). 138 external references (Google Fonts, the Material Symbols icon font, Font Awesome, Tailwind 1.0 from a CDN, a charting script, avatar service) were removed so nothing leaves the machine; icon-font glyph text was hidden; Livewire's JavaScript was served from the vendor folder. Overflow was measured on 33 pages at 375, 768 and 1280 px: only Home (+11 px) and the public contact page (+283 px, which depends on the removed Tailwind 1.0 stylesheet and so is unverified) overflow, both at 375 px.

## What is not measured and why (both reports)

| Automated check | Why it is not measured |
|---|---|
| `d1.product_md_consistency` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d1.purpose_statement_present` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d10.error_copy_lint` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d10.error_path_tests` | the repository does not tag tests as error-path tests, so the test adapter found none. |
| `d2.token_uniqueness` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d3.primary_action_per_screen` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d3.task_flow_step_counts` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d4.responsive_overflow` | the engine has no built-in check or adapter for it. The reviewer measured horizontal overflow separately (see the rendered-page paragraph above) but it is not scored. |
| `d4.spacing_type_scale_conformance` | the check ran and said it could not judge |
| `d6.consent_handling` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d6.honest_states` | the check ran and said it could not judge |
| `d7.empty_state_guidance` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d7.time_to_first_result` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d7.value_above_fold` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d8.axe_violations` | axe-core is not installed on the review machine and no global tool was installed, so no axe report exists. Not measured. |
| `d9.principle_diff` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |
| `d9.shared_components` | no built-in check yet (needs a browser run, scripted flows, or a check nobody has written) |

(The Journeys report lists the same checks; its detector and test evidence come from the same tools.)

## Unknowns

- The `infodot` profile is a draft with open questions that block ratification: which of the two design guides governs ("The Yard" or a control-center look), which segment is primary (small-business owners or all roles), how "belonging" is expressed without social counters, and what "opportunity" and a first meaningful result mean.
- Nothing is known about how business owners actually feel using InfoDot; no user was observed.
- Whether the platforms listed as live are live was not checked (no host access).

## Files in this folder

| File | What it is |
|---|---|
| `2026-10-23-c5d15b5-knowledge.json` / `2026-10-23-c5d15b5-journeys.json` | the BXQS score reports |
| `2026-10-23-c5d15b5-knowledge.evidence.json` / `2026-10-23-c5d15b5-journeys.evidence.json` | the evidence files (include the AI assessments) |
| `2026-10-23-c5d15b5-knowledge.analysis.json` / `2026-10-23-c5d15b5-journeys.analysis.json` | the analyses; baselines for `brand-engine verify --before` |
| `2026-10-23-c5d15b5-knowledge.plan.md` / `2026-10-23-c5d15b5-journeys.plan.md` | the ordered task lists; tasks that point at `rendered-snapshots/...` refer to pages rendered for this review |

Re-score: `node services/brand-engine/src/cli.js score --evidence <evidence file> --profile infodot --built-by prior-ai-sessions-and-owner` from a Dot.Brain checkout at or after commit `2019312`.

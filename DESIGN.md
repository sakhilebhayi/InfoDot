# Design: The Yard

Source of truth: `resources/css/infodot.css` (`ix-` classes, tokens in `:root`).

## Concept
A company yard. Owner code and reference on everything owned; area colours like container liveries. Standard navigation (top bar, tabs, breadcrumbs), no sidebar.

## Tokens
- Ground: concrete `#e3e6e7` (light), near-black (dark). Top bar graphite `#1b2124`.
- Areas: Knowledge blue, Files oxide red, Documents green, Platforms mustard, Analytics graphite, People steel. `body[data-area]` sets `--area`; the area band and the active tab use it.
- Stencil gold for owner codes on graphite.

## Type
- Big Shoulders Stencil Display: owner codes, references headers, big numerals, area codes. Never body text.
- Archivo: everything read.

## Components
- `ix-top` (owner code menu, tabs, search, Dot Apps, account), `ix-band` (AREACODE plus breadcrumbs), `ix-subnav` (Knowledge: Questions, Solutions).
- `ix-gate` (Home owner code), `ix-fieldblock`, `ix-door`, `ix-roster`.
- `ix-ref` (reference), `ix-copy` (copy reference), `ix-stencil-num`, `ix-stamp` (SOLVED, animated once, reduced-motion safe), `ix-kbd`.
- Existing form, table, panel and popover primitives share the same tokens.

## Page layouts
Each page is laid out for its job, not from one card template.
- Home: the gate (company name), a blue Knowledge field, Files and Documents doors with platform logos.
- Platforms: a register. An index of area plates beside a ledger, one platform per row (logo, host, LIVE or NOT LIVE YET stamp, open).
- People: a headcount slab (stencil numeral and role tally) beside the roster; roles are stamped plates.
- Analytics: a graphite scoreboard (stencil score, solve rate, solved) over stencil tallies, then trend and rankings.
- Settings (account, team): a numbered index beside title-over-form sections.
- Dot Apps: the yard map, a full-width panel of bays grouped under stencil plates (WORK, AUTO, COMM, FINA, LEARN, INFRA, VERT), live in colour and unavailable greyed with a SOON stamp.
- Contrast rule: mustard plates and the Platforms band carry ink (#1b2124), never white (white on mustard is 2.8:1).

## Behaviour
Search matches content, references (`CODE-Q|S-000014`, visibility-scoped) and quick actions (Ctrl/Cmd K). Page changes use the view-transition API on the area band. Below 1200px the tabs move to their own scrolling row; search stays reachable at every width.

## Don'ts
Sidebar, gradient text, side-stripe borders, identical card grids, decorative hero blocks, stencil for paragraphs.

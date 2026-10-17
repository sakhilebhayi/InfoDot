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

## Behaviour
Search matches content, references (`CODE-Q|S-000014`, visibility-scoped) and quick actions (Ctrl/Cmd K). Page changes use the view-transition API on the area band. Below 1200px the tabs move to their own scrolling row; search stays reachable at every width.

## Don'ts
Sidebar, gradient text, side-stripe borders, identical card grids, decorative hero blocks, stencil for paragraphs.

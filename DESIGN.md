# Design: The Yard

Source of truth: `resources/css/infodot.css` (`ix-` classes, tokens in `:root`).

## Concept
A company yard. Owner code and reference on everything owned; area colours like container liveries. Standard navigation (top bar, tabs, breadcrumbs), no sidebar.

## Tokens
- Ground: concrete `#e3e6e7` (light), near-black (dark). Top bar graphite `#1b2124`.
- Areas: Knowledge blue, Files oxide red, Documents green, Platforms mustard, Analytics graphite, People steel. `body[data-area]` sets `--area`; the area band and the active tab use it.
- Stencil gold for owner codes on graphite.

## Type
- Roboto (R-52, shared dot-ui package, `/dot-ui/css/dot-roboto.css`, self-hosted): everything, on every page including the way in and the error pages. Sizes are the dot-ui type scale (`--dot-text-*`); there are no literal pixel font sizes on text.
- Owner codes, reference headers, big numerals and area codes keep their plate treatment (capitals, tracking, weight 700) in Roboto, not in a display face. The old Archivo and Big Shoulders Stencil Display files are gone.
- Material Symbols Rounded is the only other font loaded (the icons).

## Components
- The top bar is the shared dot-ui header (`x-dot.header`, R-52): 56px on a desktop and one 52px row on a phone, logo, the search centred by a CSS grid, Home plus the groups Knowledge, Network and Platforms (and Admin for staff), Dot Apps, the notifications bell and the account menu (which also holds Inbox and the company switch); a real drawer holds every place on a phone. `ix-band` (AREACODE plus breadcrumbs), `ix-subnav` (Knowledge: Questions, Solutions).
- `ix-gate` (Home owner code), `ix-fieldblock`, `ix-door`, `ix-roster`.
- `ix-ref` (reference), `ix-copy` (copy reference), `ix-stencil-num`, `ix-stamp` (SOLVED, animated once, reduced-motion safe), `ix-kbd`.
- Existing form, table, panel and popover primitives share the same tokens.
- `ix-entry` (the way in: sign in, register, reset, verify, confirm, sign out): the shared top bar, a steel `ACCT` band and one panel. No photograph, no card of its own.
- `ix-start` (Home, first steps): a ledger of numbered rows with a stamped DONE plate, shown while steps remain.
- `ix-err` (403 to 503): the status as a stencil numeral beside what happened and what to do next.
- `ix-prose--ledger` (reading pages): each heading in a margin column beside its text, under one rule.

## Page layouts
Each page is laid out for its job, not from one card template.
- Home: the gate (company name), a blue Knowledge field, Files and Documents doors with platform logos.
- Platforms: the catalogue for the current company, as a register. An index of plates beside a ledger: the company's own platforms first (YOURS), then one section per catalogue category. One platform per row: its original logo, name and one-line purpose, the company's state in words (a chip, never a stencil stamp: states are phrases), and exactly one action. Search and two filters sit in one line above the ledger. A platform's own page is a header (logo and name beside the company's state under a mustard rule) over a ledger of headings in the margin column; switching on or off is a page of its own that lists what will happen.
- People: a headcount slab (stencil numeral and role tally) beside the roster; roles are stamped plates.
- Analytics: a graphite scoreboard (stencil score, solve rate, solved) over stencil tallies, then trend and rankings.
- Settings (account, team): a numbered index beside title-over-form sections.
- Dot Apps: the yard map, a full-width panel of bays grouped under the stencil plates of the catalogue's categories (BSNS, FINA, WORK, SALE, MRKT, DATA, CREW, LEARN, OPER, AUTO). It holds only what the current company has and the person can open; everything else is one link away on Platforms.
- Contrast rule: mustard plates and the Platforms band carry ink (#1b2124), never white (white on mustard is 2.8:1).
- The primary action (`--ix-signal`) is ink on the ground in both schemes: graphite by day, light ink by night. It never follows the area colour.
- Chips and secondary text are measured against every surface they sit on (panel, ground, sunken, hover), wash included: `--ix-warn`, `--ix-ok` and `--ix-text-3` are set so each passes 4.5:1 on all four.
- Touch: on a touch screen or below 769px every button, field, pill and icon control is at least 44px; a mouse keeps the 36px density.
- A panel, list item or note is marked with a top rule in its area colour, never a stripe down one side. The Home gate has no stripe either: its mark is the company's owner code, stencilled in gold at the far end of the gate (`ix-gate__code`).

## Behaviour
Search matches content, references (`CODE-Q|S-000014`, visibility-scoped) and quick actions (Ctrl/Cmd K). Page changes use the view-transition API on the area band. On a phone the search is a full-screen sheet opened from the bar, and the places are in the drawer; the search stays reachable at every width.

## Don'ts
Sidebar, gradient text, side-stripe borders, identical card grids, decorative hero blocks, stencil for paragraphs.

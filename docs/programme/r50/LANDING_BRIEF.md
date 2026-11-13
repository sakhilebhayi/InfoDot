# R-50: the authenticated landing experience, per platform

The owner's requirement: when a person arrives in a Dot platform from InfoDot, it must feel like entering a
specialist product that belongs to one carefully made ecosystem. Not a generic dashboard, not a marketing page.

## Composition (every platform)

1. The platform's ORIGINAL logo and official name. Never redraw, recolour, restyle or replace a logo; never
   generate one. Use the real asset in the repository (usually `public/images/logo.png` and `logo-light.png`).
   Presentation (size, placement, clear space, light or dark variant) may improve; the artwork may not change.
   If the app chrome draws an inline SVG "mark" instead of the real logo, the landing uses the real logo.
2. A welcome that uses the person's first name and the company's name, from real data.
3. One platform-specific headline and one or two sentences of supporting copy that say what this product is for.
4. EXACTLY ONE visually dominant primary action, leading to a real, working workflow in that app (verify the
   route exists and the page works for this role). Everything else on the landing is secondary and looks it.
5. Where it earns its place, a product visual made from the product itself (a real recent item, the real
   interface in miniature), never a stock illustration and never an invented chart.
6. Optional context from REAL data only: recently opened work, drafts, things waiting for this person. If there
   is nothing, say so plainly with the next step. Never fabricate counts, activity or recommendations.
7. A way back to InfoDot and the ecosystem (link to the InfoDot home and its Platforms page), consistent across
   platforms: same position, same wording.

The one-primary-action rule applies to this landing composition only. The application keeps the navigation and
controls its real work needs.

## Identity

Each platform already has its own recorded design direction (named in the repository: DESIGN.md, PRODUCT.md or
its docs). The landing is built INSIDE that direction, with its tokens, typefaces and components. Do not import
another platform's look, do not introduce a new typeface, and do not make the platforms look alike. What is
shared across the ecosystem is quality, not appearance: type hierarchy, spacing rhythm, contrast (4.5:1 body,
3:1 large, both themes), touch targets of 44px on narrow widths, visible focus, reduced-motion respected, no
sideways scroll, fast first paint.

Rejected by the owner, in any platform: generic AI-dashboard looks; four equal metric tiles; rounded cards
floating in gutters; a thick coloured border on one side of a card; gradient text; decorative glass; a kicker
label above a heading; motion on every element.

## Motion

One authored moment at most: a short entrance for the headline block and the primary action, transform and
opacity only, from an already-visible default (the page must be complete with JavaScript blocked or slow), none
when the person prefers reduced motion.

## Process (mandatory)

- Invoke the installed design skills before writing UI: `impeccable` (context, then its craft floor), and for a
  new composition `hallmark` and `design-taste-frontend` as taste checks. Read the skill instructions; do not
  claim a skill you did not load.
- Objective check: `node /Users/sakhilebhayi/Dot/impeccable/cli/bin/cli.js detect <rendered.html>` before and
  after, on HTML fetched from a local server; never end with more findings.
- Look at the result in a real browser at 1440 and 375 wide, light and dark, signed in as an owner with data and
  as a brand-new member with none.
- Tests: the landing renders for each role that can arrive; exactly one element carries the primary-action
  marker (`data-primary-action`); its target route exists and answers 200 for that role; the logo file exists and
  is the original (hash of the file unchanged); the empty state renders; no fabricated numbers (assert the page
  shows counts only from seeded records).

## Do not

Rebuild sign-in, tenancy, routing or business logic to improve appearance. Add a dependency without need. Name
any discontinued platform. Touch the app's sign-in handoff except to keep it working.

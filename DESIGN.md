# InfoDot design system (signed-in area)

Source of truth: `resources/css/infodot.css` (classes prefixed `ix-`). Product truth: `PRODUCT.md`.

## Idea
A calm, precise business workspace. Order comes from type, spacing and alignment, not decoration: neutral surfaces, thin borders, one restrained accent taken from the InfoDot logo blue, almost no shadow, no gradients. Information lives in tables and compact lists. It deliberately shares nothing with the dark control-room look of the sibling Dot platforms and is not a social interface.

## Colour
- Light: page `#f6f7f9`, surfaces `#ffffff`, sunken `#f0f2f5`, hairlines `#e2e6eb`; text `#111827`, secondary `#465264`, tertiary `#626f81`.
- Dark: page `#0d1117`, surfaces `#151b23`, hairlines `#242d39`; text `#e6edf3`, secondary `#a9b5c3`, tertiary `#8c9aab`.
- One accent: `#1b64b4` (light) / `#5ea1f0` (dark) for primary actions, current navigation and focus. Status colours only for meaning: amber for unsolved/needs attention, green for solved/live, red for errors. Themes follow `prefers-color-scheme` and keep the same contrast.

## Type
Hanken Grotesk only (400-700). Body 14px, page title 26px/650, section titles 14-16px/600, table headers 12px/600 in tertiary. Tabular figures in tables and readouts. Icons: Material Symbols Rounded at weight 350.

## Shell
Fixed 248px side navigation (white surface, hairline) grouped Work (Home, Files, Documents), Knowledge (Questions, Solutions), Ecosystem (Platforms, Analytics), Administration (team and members, API tokens; administrators only). Files and Documents open the platform signed in and appear only when live. 56px top bar: breadcrumbs left, search, Dot Apps, team menu, account. Content column max 1120px. Under 1024px the side navigation becomes a drawer.

## Components
Page head (title, one line, primary action), segmented filter, table with sticky header row (`ix-table`, stacks on phones), status chip (dot + words), panel with hairline head, form fields (36px, 6px radius, accent focus ring), popovers (12px blur shadow only), platform directory rows, steps with numbered markers, empty state with dashed border.

## Rules
No likes or engagement counters; no gradients or glass; no cards for lists that are tables; radius 6-8px; motion limited to 120ms colour transitions and the drawer; every control has hover, focus-visible (2px accent), active and disabled states.

# InfoDot design system (signed-in area)

Source of truth: `resources/css/infodot.css` (classes prefixed `ix-`). Product truth: `PRODUCT.md`.

## Idea
InfoDot is the parent of the Dot ecosystem, so its interface is drawn from its own logo: chunky rounded letters, a grey disc, a chevron. The one signature move is "the dot": counts, avatars, steps, status and platforms sit in circles, and the go-arrow is the logo chevron in a disc. It deliberately does not share the dark ruled control-room look of Dot.Memory, Dot.Central or Dot.Projects.

## Colour
- Brand blue `#1f78c8` (committed: top bar and band), deep blue `#195fa0` (hover, active tab text). Dark set: `#14508a` band, `#4aa3ee` actions.
- Paper ground `#f4f6f9`, white panels, ink `#14202c`, secondary text `#43566a`, tertiary `#586c80` (all >= 4.5:1 on their surface).
- Logo grey `#bcbec0` for discs, gold `#f0bc2e` only for the dot accent (unsolved state, the band action, selection, focus ring).
- Solved green `#17714b`, error `#c0362c`. Light is default; the dark set follows `prefers-color-scheme`.

## Type
Baloo 2 (700/800) for headings, row titles and every figure (tabular numerals); Nunito (400-800) for reading. Body 15px, band title 44px (34px phones), row title 20px.

## Shape and layout
Pills (999px) for buttons, tabs and filters; 20px radius panels; 12px inputs. Standard top navigation: blue bar with tabs (Questions, Solutions, Platforms, Analytics), Dot Apps, team, account; below it a blue band with the section title, one line of purpose, the search pill and the primary action; then paper. No side rail. Lists are single panels of ruled rows, not floating cards.

## Components
Lead circle (status), count discs, chevron disc, filter pills with counts, status chips, platform dots (blue = live, grey = coming), step discs, comment rows, empty state with the logo's disc and dot. Popovers: 20px radius, 12px blur shadow, 1px border.

## States
Every control has hover, focus-visible (3px gold ring), active (scale 0.97), disabled. Platforms that are not live are disabled and say "Coming".

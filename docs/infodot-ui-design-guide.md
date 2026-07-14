# InfoDot UI Design Guide

## Purpose

InfoDot is the hub of a larger ecosystem, so its interface needs to feel dependable, structured, and fast to scan. The design system should support knowledge work, navigation across multiple products, and collaboration-heavy screens without visual clutter.

## Visual Direction

Design for an interface that feels like a control center rather than a marketing site.

- **Tone:** focused, confident, operational
- **Primary qualities:** clarity, hierarchy, density with restraint, obvious affordances
- **Avoid:** oversized cards everywhere, weak contrast, inconsistent spacing, decorative gradients that obscure content

## Core UI Rules

### 1. Build around clear hierarchy

Every page should answer these questions within the first screen:

1. Where am I?
2. What can I do here?
3. What needs my attention first?

Required structure for most product screens:

- page title and one-line purpose
- primary actions aligned near the title
- filters or search directly under the header when relevant
- content grouped into distinct sections with stable spacing

### 2. Standardize layout primitives

Create a small set of repeatable primitives and use them everywhere:

- app shell
- section header
- stats strip
- searchable list
- activity feed
- empty state
- success and error state
- side panel or drawer
- modal with clear primary and secondary actions

These primitives should be implemented as Blade, Livewire, or Alpine-driven components instead of repeated page markup.

### 3. Use a predictable spacing system

Adopt a 4px or 8px spacing scale and stop improvising values.

Suggested spacing scale:

- 4: tight control spacing
- 8: related elements
- 12 or 16: standard field and list rhythm
- 24: section grouping
- 32 or 40: major page separation

### 4. Tighten typography

The current hub should optimize for reading and scanning, not display-heavy typography.

- Use one sans serif family for application UI.
- Reserve larger headings for page structure, not decoration.
- Keep line lengths narrow enough for solutions, comments, and questions to remain readable.
- Ensure body text, metadata, and helper text have clearly separated visual weights.

### 5. Reduce component drift

Buttons, inputs, badges, cards, tabs, and alerts should each have a small approved variant set.

Example button variants:

- primary
- secondary
- subtle
- destructive
- link-style

Every new variant should justify itself. If it does not support a repeated pattern, it should not exist.

## Navigation Design

InfoDot has a harder navigation problem than a single-product app because it is both a destination and a switchboard.

### Primary navigation should expose three layers

1. **Global hub navigation:** dashboard, solutions, questions, files, teams, notifications
2. **Context navigation:** filters, tabs, views inside the active area
3. **Ecosystem navigation:** the Dot switcher for cross-platform movement

### Navigation recommendations

- Keep the global nav stable across authenticated screens.
- Make the Dot switcher visually distinct from normal navigation so users understand they are leaving the current surface.
- Show current location with more than color alone.
- Avoid mixing search, filters, and cross-platform actions in one crowded header row.

## Screen-Specific Opportunities

### Dashboard

- Surface tasks, unread activity, recent work, and ecosystem shortcuts above generic summaries.
- Replace passive statistic cards with actionable widgets.
- Add a "continue where you left off" block.

### Solutions Hub

- Improve scanability with stronger title, tag, author, and activity hierarchy.
- Add saved filters and a clearer difference between trending, recent, and recommended content.
- Make list-to-detail movement faster with inline previews or side panels.

### Q&A

- Separate the accepted answer, high-signal replies, and low-signal replies more clearly.
- Highlight unresolved questions that need attention.
- Make author identity, timestamps, and engagement easier to parse.

### Profiles and Teams

- Present roles, expertise, activity, and team relationships in a structured summary.
- Reduce noise by collapsing rarely used profile fields.

## Accessibility Baseline

Accessibility work should be treated as default product quality.

- Use semantic headings in order.
- Ensure color contrast meets WCAG AA.
- Support keyboard navigation for search, dropdowns, modals, notifications, and the Dot switcher.
- Provide visible focus states for all interactive elements.
- Do not rely on color alone for status.
- Ensure form errors are tied to fields with clear text.

## Design System Work Queue

1. Inventory all current shared UI patterns.
2. Remove duplicate button, input, dropdown, and card styles.
3. Define tokens for color, spacing, radius, shadows, and typography.
4. Rebuild the most-used shell components first.
5. Refactor high-traffic screens to the new primitives before polishing lower-value pages.

## Definition Of Done For UI Changes

- The page uses approved layout and component primitives.
- Primary and secondary actions are obvious.
- Loading, empty, success, and error states are present.
- Keyboard and mobile behavior have been checked.
- The screen remains visually consistent with the rest of the hub.
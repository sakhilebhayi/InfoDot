# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users
Two groups share one product, per the owner: staff and team members at work, who open InfoDot between tasks and already use other Dot apps; and small business owners and the public, who look for practical how-to answers (for example how to register a business), often on a phone. Both arrive with a task in mind and leave once they have an answer or have handed one on.

## Product Purpose
InfoDot is the hub and parent of the Dot ecosystem: the identity provider that lets someone sign in once and move between every Dot platform, and a team knowledge base made of Questions (threads with replies and likes, markable as solved by the asker) and Solutions (step-by-step guides with a duration and tags). For a signed-in person the owner ranks the knowledge base first and the platform launcher second. Success is a person finding or posting an answer quickly, and finding the right Dot platform when they need it.

## Positioning
InfoDot is the origin of the Dot ecosystem ("the mother of all of them", in the owner's words), so its look and tone must be its own and must not read as a sibling of Dot.Memory, Dot.Central or Dot.Projects. A neighbouring knowledge tool cannot truthfully claim to be the front door and identity provider for a whole family of business platforms.

## Operating Context
Signed-in area of a Laravel/Livewire app on shared cPanel hosting. Sign-in hands a short-lived token to other Dot platforms (`/auth/ecosystem`). Public pages (welcome, about, contact, legal) already carry an established InfoDot look: paper background, charcoal and gold, Baloo 2 / DM Sans display and body, three-colour dot accents (gold, blue, coral, green).

## Capabilities and Constraints
Questions and Solutions are the two core objects and must stay (owner-confirmed binding). Live search, team and account menus, and a Dot Apps launcher that only opens live platforms must keep working. The 28-entry platform registry is `config/ecosystem.php`; only entries flagged `active` can be opened. Undecided: whether the hub mark in the rail stays (the real logo in `public/img/` is a rounded lowercase "info" wordmark with a grey disc "dot." and chevron).

## Brand Commitments
Name: InfoDot. Real logo assets exist in `public/img/` (logo-full.png: blue "info", grey "dot." disc and chevron; logo_white.png: gold-on-dark). Registry accent for InfoDot is blue (#2f8fd6) while the other platforms use gold.

## Evidence on Hand
Real content only through the app's own data; the only demonstration content is seeded local test data (synthetic). No testimonials, customer counts or benchmarks exist and none may be invented.

## Product Principles
- Knowledge first: finding or sharing an answer is never more than one action away.
- The parent speaks for itself: InfoDot carries its own identity, not the ecosystem's shared look.
- Honest about state: say what is live, solved or missing; never imply a platform can be opened when it cannot.
- Plain enough for a first-time business owner on a phone, fast enough for staff who use it daily.

## Accessibility & Inclusion
Public users include first-time and mobile-only visitors; keep text legible at 4.5:1, touch targets comfortable, and reading level plain. South African context (POPIA-aligned legal pages exist).

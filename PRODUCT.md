# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users
Business people working inside an organisation: owners and executives, department heads, project managers and team leaders, information and document managers, IT administrators and compliance officers, and employees at small businesses through to large enterprises. They arrive between tasks to find a document or an answer, open the right Dot platform, and administer who can see what. Confirmed by the owner (2026-10-17 brief): InfoDot must read as corporate, not social.

## Product Purpose
InfoDot is the hub and parent of the Dot ecosystem: the identity provider that signs a person in once and opens every Dot platform, and the business workspace around it. Today that means a Home page, a Knowledge section (Questions and Solutions, kept as a business knowledge base without likes), a directory of live platforms, analytics, and team administration. Files and Documents open Dot.Files and Dot.docs signed in. The roadmap (owner brief) adds workspaces, shared files through a Dot.Files API, approvals and richer administration; none of that is advertised until it is built and verified.

## Positioning
InfoDot is the origin of the ecosystem ("the mother of all of them", in the owner's words) and must have its own identity: calm, precise and corporate, not a sibling of the dark control-room look of Dot.Memory, Dot.Central or Dot.Projects, not a social platform, and not an imitation of SharePoint or Apple.

## Operating Context
Laravel/Livewire app on shared cPanel hosting; each Dot app has its own database. Sign-in hands a short-lived token to another platform, which proves it with InfoDot's redeem endpoint (Phase 0). Email is verified at sign-up because other apps trust InfoDot's email. Dot.Files is the intended single authority for files and folders; it has no API yet.

## Capabilities and Constraints
Questions and Solutions stay (owner-confirmed binding), without social counters. Only features that work appear in navigation (no Shared with me, Favourites or Approvals yet). Only live platforms can be opened. Permissions and organisation boundaries must be enforced server-side; Questions and Solutions are currently global across teams (see docs/enterprise-platform-audit.md) and must not hold private business documents.

## Brand Commitments
Name: InfoDot. Real logo assets in `public/img/` (`logo.png` for light, `logo_white.png` for dark). Accent derives from the logo blue. Corporate, minimal, premium; light and dark themes of equal quality.

## Evidence on Hand
Only the application's own data; demonstration content in screenshots and tests is synthetic. No customer counts, testimonials or compliance claims exist and none may be invented.

## Product Principles
- Clarity over decoration: every element informs or enables an action.
- Content first: documents, lists and search lead; chrome recedes.
- Honest navigation: show only what works today.
- Business, not social: accountability, ownership and state in words; no engagement counters.
- One system: every page uses the shared design system and tokens.

## Accessibility & Inclusion
Text at or above 4.5:1 in both themes, keyboard navigation, visible focus, responsive from 375px, plain language. South African context (POPIA-aligned legal pages exist).

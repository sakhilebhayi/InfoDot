# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users
Business owners and the people who work for them: owners and executives, department heads, project managers, information and document managers, IT administrators, and employees from small businesses through enterprises. They arrive between tasks to find an answer or a document, open the right Dot platform, and see who works under the company. The owner must be proud to put their company's code on screen.

## Product Purpose
InfoDot is the hub and parent of the Dot ecosystem: the identity provider that signs a person in once and opens every Dot platform, and the business workspace around it. Today that means a Home gate, a Knowledge area (Questions and Solutions, kept as a business knowledge base without likes), a directory of live platforms, People, and analytics. Files and Documents open Dot.Files and Dot.docs signed in. Roadmap items (shared files through a Dot.Files API, approvals) are not advertised until built and verified.

## Positioning
InfoDot is the origin of the ecosystem and has its own identity, "The Yard", drawn from shipping-container ownership: every company has a four-letter owner code and everything it owns carries a reference such as NWFN-Q-000014. It must not resemble the dark control-room look of Dot.Memory, Dot.Central or Dot.Projects, a generic SaaS sidebar-and-cards layout, or a social platform.

## Operating Context
Laravel/Livewire app on shared cPanel hosting; each Dot app has its own database. Sign-in hands a short-lived token to another platform, which proves it at InfoDot's redeem endpoint. Email is verified at sign-up because other apps trust it. Companies are non-personal Jetstream teams with a uuid and owner code.

## Capabilities and Constraints
Questions and Solutions stay, without social counters. Only features that work appear in navigation. Only live platforms can be opened. Visibility is enforced server-side: company by default, public only by explicit choice; unknown or hidden items return 404.

## Brand Commitments
Name: InfoDot. Real logo assets in `public/img/`. Light and dark themes of equal quality. Container colours mark areas: Knowledge blue, Files oxide red, Documents green, Platforms mustard, Analytics graphite, People steel.

## Evidence on Hand
Only the application's own data; demonstration content is synthetic. No customer counts, testimonials or compliance claims exist and none may be invented.

## Product Principles
- Ownership is the signature: owner code and reference on everything a company owns, and both are searchable.
- Each area has its own layout and container colour; no identical card grids.
- Stencil type for codes and big numerals only; everything read is set in Archivo.
- Honest navigation: show only what works today.
- Business, not social: state and accountability in words, a stencilled SOLVED stamp instead of counters.

## Accessibility & Inclusion
Text at or above 4.5:1 in both themes, keyboard navigation (Ctrl/Cmd K search), visible focus, reduced motion respected, responsive from 375px, plain language. South African context (POPIA-aligned legal pages).
